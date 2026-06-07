<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Status Checker Cron
 * Chạy mỗi 2 phút — poll trạng thái tất cả records đang pending
 */
class KtEinvoiceStatusChecker
{
    /** @var Kt_einvoice_model */
    private $model;

    /** @var KtEinvoiceService */
    private $service;

    public function __construct()
    {
        $this->_requireDependencies();

        $CI = &get_instance();
        if (!isset($CI->Kt_einvoice_model)) {
            $CI->load->model('kt_einvoice/Kt_einvoice_model');
        }
        $this->model   = $CI->Kt_einvoice_model;
        $this->service = new KtEinvoiceService();
    }

    public function run(): void
    {
        $startTime = microtime(true);
        $stats     = ['processed' => 0, 'updated' => 0, 'errors' => 0, 'details' => []];

        // Lấy records cần poll (tối đa 100 mỗi lần)
        $pendingStatuses = [KT_EINVOICE_STATUS_PENDING_CREATE, KT_EINVOICE_STATUS_PENDING_ISSUE, KT_EINVOICE_STATUS_PENDING_CANCEL];
        $records         = $this->model->getPendingRecords($pendingStatuses, 100);

        if (empty($records)) {
            $this->_logCron('kt_einvoice_status_checker', $stats, $startTime);
            return;
        }

        // Group theo tenant + environment để tái sử dụng API client
        $grouped = [];
        foreach ($records as $record) {
            $key = $record['tenant_id'] . ':' . $record['environment'];
            $grouped[$key][] = $record;
        }

        foreach ($grouped as $key => $tenantRecords) {
            [$tenantId, $environment] = explode(':', $key);
            $tenantId = (int) $tenantId;

            try {
                $client = $this->service->buildApiClient($tenantId, $environment);
            } catch (Exception $e) {
                log_message('error', "[kt_einvoice_cron] Cannot build client for tenant $tenantId: " . $e->getMessage());
                $stats['errors'] += count($tenantRecords);
                continue;
            }

            foreach ($tenantRecords as $record) {
                $stats['processed']++;
                try {
                    $statusBefore = $record['status'];

                    if ($record['status'] === KT_EINVOICE_STATUS_PENDING_CREATE) {
                        $this->service->pollCreateStatus($tenantId, (int) $record['id'], $environment, $client);
                    } elseif ($record['status'] === KT_EINVOICE_STATUS_PENDING_ISSUE) {
                        $this->service->pollIssueStatus($tenantId, (int) $record['id'], $environment, $client);
                    } elseif ($record['status'] === KT_EINVOICE_STATUS_PENDING_CANCEL) {
                        $this->_pollCancelStatus($tenantId, $record, $environment, $client);
                    }

                    // Kiểm tra xem status có thay đổi không
                    $updated = $this->model->getRecord((int) $record['id'], $tenantId);
                    if ($updated && $updated['status'] !== $statusBefore) {
                        $stats['updated']++;
                        $stats['details'][] = "Record #{$record['id']}: {$statusBefore} → {$updated['status']}";

                        // Auto-issue nếu cần (khi create xong → draft)
                        $this->_checkAutoIssue($tenantId, $updated, $environment, $client);
                    }

                } catch (Exception $e) {
                    $stats['errors']++;
                    log_message('error', "[kt_einvoice_cron] Error on record #{$record['id']}: " . $e->getMessage());
                }

                // Delay nhỏ giữa mỗi call để tránh rate limit
                usleep(200000); // 200ms
            }
        }

        $this->_logCron('kt_einvoice_status_checker', $stats, $startTime);
    }

    /**
     * Poll trạng thái hủy hóa đơn
     */
    private function _pollCancelStatus(int $tenantId, array $record, string $environment, $client): void
    {
        if (empty($record['sepay_cancel_tracking'])) return;

        try {
            $response = $client->checkStatus($record['sepay_cancel_tracking']);
            $status   = $response['data']['status'] ?? '';

            if ($status === 'success' || $status === 'cancelled') {
                $this->model->updateRecord((int) $record['id'], [
                    'status'       => KT_EINVOICE_STATUS_CANCELLED,
                    'cancelled_at' => date('Y-m-d H:i:s'),
                ]);
            } elseif ($status === 'failed') {
                // Rollback về issued nếu hủy thất bại
                $this->model->updateRecordStatus((int) $record['id'], KT_EINVOICE_STATUS_ISSUED, $response['data']['message'] ?? 'Hủy thất bại.');
            }
        } catch (Exception $e) {
            log_message('error', "[kt_einvoice_cron] pollCancelStatus error #{$record['id']}: " . $e->getMessage());
        }
    }

    /**
     * Nếu record vừa chuyển sang draft và có flag auto_issue → issue luôn
     */
    private function _checkAutoIssue(int $tenantId, array $record, string $environment, $client): void
    {
        if ($record['status'] !== KT_EINVOICE_STATUS_DRAFT) return;

        $meta = json_decode($record['metadata_json'] ?? '{}', true);
        if (empty($meta['auto_issue'])) return;

        try {
            $this->service->issueInvoice($tenantId, (int) $record['id'], $environment);
        } catch (Exception $e) {
            log_message('error', "[kt_einvoice_cron] auto_issue error #{$record['id']}: " . $e->getMessage());
        }
    }

    private function _logCron(string $cronName, array $stats, float $startTime): void
    {
        $duration = (int) ((microtime(true) - $startTime) * 1000);
        $this->model->insertCronLog([
            'cron_name'       => $cronName,
            'tenant_id'       => null,
            'status'          => $stats['errors'] > 0 ? 'partial' : 'success',
            'total_processed' => $stats['processed'],
            'total_updated'   => $stats['updated'],
            'total_errors'    => $stats['errors'],
            'details_json'    => json_encode($stats['details']),
            'started_at'      => date('Y-m-d H:i:s', (int) (microtime(true) - $duration / 1000)),
            'finished_at'     => date('Y-m-d H:i:s'),
            'duration_ms'     => $duration,
        ]);
    }

    private function _requireDependencies(): void
    {
        $base = APPPATH . '../modules/kt_einvoice/';
        require_once $base . 'config/kt_einvoice_config.php';
        require_once $base . 'services/KtEinvoiceEncryptionService.php';
        require_once $base . 'services/SepayEinvoiceApiClient.php';
        require_once $base . 'services/KtEinvoiceMapperService.php';
        require_once $base . 'services/KtEinvoiceQuotaService.php';
        require_once $base . 'services/KtEinvoiceService.php';
    }
}
