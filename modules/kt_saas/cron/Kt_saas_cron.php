<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_saas_run_scheduled_jobs()
{
    $CI = &get_instance();
    $CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');

    try {
        if (!$CI->db->table_exists(db_prefix() . 'kt_saas_provision_jobs')) {
            return;
        }

        $CI->Kt_saas_model->repair_provisioning_state_consistency();

        kt_saas_verify_domains_readiness();
        kt_saas_run_recurring_billing();
        kt_saas_recalculate_usage_snapshots();
        kt_saas_cleanup_usage_snapshots();
        kt_saas_cleanup_expired_backups();

        require_once module_dir_path(KT_SAAS_MODULE, 'provisioning/ProvisioningJobRunner.php');
        $runner = new ProvisioningJobRunner();
        $jobs = $CI->Kt_saas_model->get_due_provision_jobs(20);
        foreach ($jobs as $job) {
            $runningJob = $CI->Kt_saas_model->mark_provision_job_running((int) $job['id']);
            if (!$runningJob) {
                continue;
            }

            $result = $runner->execute($runningJob);
            if (!empty($result['success'])) {
                $CI->Kt_saas_model->mark_provision_job_done((int) $runningJob['id'], $result);
                continue;
            }

            $CI->Kt_saas_model->mark_provision_job_failed((int) $runningJob['id'], $result['message'] ?? 'Unknown provisioning error.', $result);
        }
    } catch (Throwable $e) {
        log_message('error', 'KT SaaS cron failed: ' . $e->getMessage());
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path(KT_SAAS_MODULE, 'helpers/kt_saas_helper.php');
        }
        if (function_exists('kt_saas_send_email_event') && function_exists('kt_saas_landlord_ops_email')) {
            $dedupeKey = 'cron_failed|' . date('Y-m-d');
            kt_saas_send_email_event('cron_failed', [
                'tenant_id' => null,
                'recipient_email' => kt_saas_landlord_ops_email(),
                'owner_name' => 'Operations',
                'tenant_name' => 'Landlord',
                'provider_name' => 'KT SaaS',
                'module_name' => KT_SAAS_MODULE,
                'error_message' => $e->getMessage(),
                'job_id' => app_generate_hash(),
                'related_type' => 'cron',
                'related_id' => 'kt_saas_run_scheduled_jobs',
                'dedupe_key' => $dedupeKey,
            ], [
                'event_key' => 'cron_failed',
                'dedupe_key' => $dedupeKey,
            ]);
        }
    }
}

function kt_saas_recalculate_usage_snapshots($limit = 100)
{
    require_once module_dir_path(KT_SAAS_MODULE, 'services/UsageSnapshotRunner.php');

    $runner = new UsageSnapshotRunner();
    return $runner->recalculateAll($limit);
}

function kt_saas_cleanup_usage_snapshots($days = null)
{
    require_once module_dir_path(KT_SAAS_MODULE, 'services/UsageRetentionService.php');

    $service = new UsageRetentionService();
    return $service->cleanupOldSnapshots($days);
}

function kt_saas_run_recurring_billing($limit = 100)
{
    require_once module_dir_path(KT_SAAS_MODULE, 'billing/RecurringBillingRunner.php');

    $runner = new RecurringBillingRunner();
    return $runner->run($limit);
}

function kt_saas_verify_domains_readiness($limit = 25, $staleHours = 12)
{
    $CI = &get_instance();
    $CI->load->model(KT_SAAS_MODULE . '/Kt_saas_model');

    if (!$CI->db->table_exists(db_prefix() . 'kt_saas_domains')) {
        return ['checked' => 0, 'ready' => 0, 'attention' => 0];
    }

    require_once module_dir_path(KT_SAAS_MODULE, 'services/DomainVerificationService.php');

    $service = new DomainVerificationService();
    $domains = $CI->Kt_saas_model->get_domains_needing_verification($limit, $staleHours);
    $result = ['checked' => 0, 'ready' => 0, 'attention' => 0];

    foreach ($domains as $domain) {
        $verification = $service->verify($domain);
        $CI->Kt_saas_model->save_domain_verification((int) $domain['id'], $verification);
        $result['checked']++;

        if (($verification['readiness_status'] ?? '') === 'ready') {
            $result['ready']++;
        } else {
            $result['attention']++;
        }
    }

    return $result;
}

function kt_saas_cleanup_expired_backups($days = null)
{
    require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantBackupService.php');

    $service = new TenantBackupService();
    return $service->cleanupExpiredBackups($days);
}
