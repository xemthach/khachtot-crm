<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_sepay_run_scheduled_jobs()
{
    $CI = &get_instance();
    $CI->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
    $CI->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
    $CI->load->library(KT_SEPAY_MODULE . '/Kt_sepay_processor');

    foreach ($CI->Kt_sepay_model->get_pending_expired_payment_requests() as $request) {
        $CI->Kt_sepay_model->update_payment_request((int) $request['id'], ['status' => 'expired']);
    }

    foreach ($CI->Kt_sepay_model->get_settings_profiles(true) as $settings) {
        if (empty($settings['auto_reconcile_enabled'])) {
            continue;
        }

        $tenantId = isset($settings['tenant_id']) && $settings['tenant_id'] !== null ? (int) $settings['tenant_id'] : null;
        $lastRun = strtotime((string) ($settings['last_reconcile_at'] ?? ''));
        $interval = max((int) ($settings['reconcile_interval_minutes'] ?? 15), 1) * 60;
        if ($lastRun && (time() - $lastRun) < $interval) {
            continue;
        }

        require_once module_dir_path(KT_SEPAY_MODULE, 'libraries/Kt_sepay_api.php');
        $api = new Kt_sepay_api([
            'tenant_id'       => $tenantId,
            'fallback_global' => $tenantId === null,
        ]);

        $sinceId = (string) ($settings['last_reconcile_transaction_id'] ?? '');
        $query = ['transfer_type' => 'in', 'per_page' => 100];
        if ($sinceId !== '') {
            $query['since_id'] = $sinceId;
        }

        $apiResult = $api->listTransactions($query);
        $errors = 0;
        $matched = 0;
        $processed = 0;
        $rows = [];
        $lastId = $sinceId;

        if (!empty($apiResult['success']) && is_array($apiResult['data'])) {
            $rows = $apiResult['data'];
            foreach ($rows as $row) {
                $payload = [
                    'id'              => $row['id'] ?? '',
                    'gateway'         => $row['bank_brand_name'] ?? '',
                    'transactionDate' => $row['transaction_date'] ?? '',
                    'accountNumber'   => $row['account_number'] ?? '',
                    'code'            => $row['code'] ?? '',
                    'content'         => $row['transaction_content'] ?? '',
                    'transferType'    => ((int) ($row['amount_in'] ?? 0)) > 0 ? 'in' : 'out',
                    'transferAmount'  => (int) (($row['amount_in'] ?? 0) > 0 ? $row['amount_in'] : ($row['amount_out'] ?? 0)),
                    'referenceCode'   => $row['reference_number'] ?? '',
                ];

                $result = $CI->kt_sepay_processor->processIncomingTransaction($payload, ['source' => 'cron_reconcile', 'reprocess_existing' => true]);
                $resultStatus = (string) ($result['status'] ?? '');
                if (in_array($resultStatus, ['matched', 'processed', 'duplicate'], true) || (!empty($result['success']) && !in_array($resultStatus, ['unmatched', 'ignored'], true))) {
                    $matched++;
                }
                if (!empty($result['success']) && $resultStatus !== 'unmatched') {
                    $processed++;
                } elseif ($resultStatus !== 'duplicate' && $resultStatus !== 'unmatched' && $resultStatus !== 'ignored') {
                    $errors++;
                }

                if (!empty($row['id'])) {
                    $lastId = (string) $row['id'];
                }
            }
        } else {
            $errors = 1;
            if (!function_exists('kt_saas_send_email_event')) {
                require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
            }
            if (function_exists('kt_saas_send_email_event') && function_exists('kt_saas_landlord_ops_email')) {
                $reason = 'list_transactions_failed';
                $dedupeKey = 'provider_connection_failed|kt_sepay|cron|' . date('Y-m-d') . '|' . ($tenantId === null ? 'landlord' : (string) $tenantId);
                kt_saas_send_email_event('provider_connection_failed', [
                    'tenant_id' => $tenantId,
                    'recipient_email' => kt_saas_landlord_ops_email(),
                    'owner_name' => 'Operations',
                    'tenant_name' => $tenantId === null ? 'Landlord' : ('Tenant #' . $tenantId),
                    'provider_name' => 'SePay',
                    'module_name' => KT_SEPAY_MODULE,
                    'error_message' => 'SePay listTransactions failed.',
                    'webhook_url' => site_url('admin/kt_sepay/cron'),
                    'job_id' => (string) app_generate_hash(),
                    'related_type' => 'cron',
                    'related_id' => (string) $lastId,
                    'dedupe_key' => $dedupeKey,
                ], [
                    'event_key' => 'provider_connection_failed',
                    'dedupe_key' => $dedupeKey,
                ]);
            }
        }

        $localReprocess = $CI->kt_sepay_processor->reprocessUnmatchedTransactions($tenantId, 100);
        $matched += (int) ($localReprocess['matched'] ?? 0);
        $processed += (int) ($localReprocess['processed'] ?? 0);
        $errors += (int) ($localReprocess['errors'] ?? 0);

        $CI->Kt_sepay_model->save_settings([
            'environment'                => $settings['environment'] ?? 'sandbox',
            'bank_code'                  => $settings['bank_code'] ?? '',
            'account_number'             => $settings['account_number'] ?? '',
            'account_name'               => $settings['account_name'] ?? '',
            'qr_template'                => $settings['qr_template'] ?? 'compact',
            'reference_prefix_invoice'   => $settings['reference_prefix_invoice'] ?? 'KTINV',
            'reference_prefix_subscription' => $settings['reference_prefix_subscription'] ?? 'KTSAAS',
            'reference_prefix_manual'    => $settings['reference_prefix_manual'] ?? 'KTPAY',
            'auto_reconcile_enabled'     => !empty($settings['auto_reconcile_enabled']) ? 1 : 0,
            'reconcile_interval_minutes' => $settings['reconcile_interval_minutes'] ?? 15,
            'payment_request_expiry_minutes' => $settings['payment_request_expiry_minutes'] ?? 60,
            'last_reconcile_transaction_id' => $lastId !== '' ? $lastId : $sinceId,
            'last_reconcile_at'          => kt_sepay_now(),
            'allow_partial_payment'      => !empty($settings['allow_partial_payment']) ? 1 : 0,
            'is_active'                  => !empty($settings['is_active']) ? 1 : 0,
        ], $tenantId);

        $CI->Kt_sepay_model->create_reconciliation_log([
            'tenant_id'       => $tenantId,
            'run_id'          => app_generate_hash(),
            'environment'     => (string) ($settings['environment'] ?? 'sandbox'),
            'from_time'       => null,
            'to_time'         => kt_sepay_now(),
            'total_fetched'   => count($rows),
            'total_matched'   => $matched,
            'total_processed' => $processed,
            'total_errors'    => $errors,
            'metadata_json'   => kt_sepay_json_encode([
                'last_id' => $lastId,
                'api'     => empty($apiResult['success']) ? $apiResult : null,
                'local_reprocess' => $localReprocess,
            ]),
        ]);
    }
}
