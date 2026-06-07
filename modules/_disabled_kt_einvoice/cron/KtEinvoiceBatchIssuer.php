<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Batch Issuer Cron
 * Chạy mỗi 5 phút — phát hành hàng loạt từ batch sessions đang pending
 */
class KtEinvoiceBatchIssuer
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

        // Lấy batch sessions đang pending/processing (tối đa 5 batch mỗi lần)
        $sessions = $this->model->db
            ->where_in('status', ['pending', 'processing'])
            ->order_by('created_at', 'ASC')
            ->limit(5)
            ->get(db_prefix() . 'kt_einvoice_batch_sessions')
            ->result_array();

        foreach ($sessions as $session) {
            $tenantId   = (int) $session['tenant_id'];
            $environment = $session['environment'];
            $batchId    = (int) $session['id'];

            // Đánh dấu session đang processing
            if ($session['status'] === 'pending') {
                $this->model->updateBatchSession($batchId, [
                    'status'     => 'processing',
                    'started_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Build API client
            try {
                $client = $this->service->buildApiClient($tenantId, $environment);
            } catch (Exception $e) {
                log_message('error', "[kt_einvoice_batch] Cannot build client for tenant $tenantId: " . $e->getMessage());
                $this->model->updateBatchSession($batchId, [
                    'status'        => 'failed',
                    'error_summary' => $e->getMessage(),
                    'finished_at'   => date('Y-m-d H:i:s'),
                ]);
                $stats['errors']++;
                continue;
            }

            // Lấy items chưa xử lý (10 items mỗi lần chạy cron)
            $items = $this->model->getPendingBatchItems($batchId, 10);

            if (empty($items)) {
                // Tất cả items đã xử lý → đóng session
                $this->_closeBatchSession($batchId);
                continue;
            }

            $successCount = 0;
            $failCount    = 0;

            foreach ($items as $item) {
                $stats['processed']++;

                try {
                    // Bước 1: Tạo draft
                    $draftResult = $this->service->createDraft(
                        $tenantId,
                        (int) $item['perfex_invoice_id'],
                        $environment,
                        0 // system
                    );

                    if (!$draftResult['success'] && !($draftResult['idempotent'] ?? false)) {
                        throw new RuntimeException($draftResult['message']);
                    }

                    $recordId = (int) ($draftResult['record_id'] ?? 0);

                    // Bước 2: Nếu đã là draft → issue ngay
                    if ($recordId) {
                        // Đợi ngắn cho SePay xử lý create (nếu cần)
                        // Với idempotent và status = draft thì issue luôn
                        $record = $this->model->getRecord($recordId, $tenantId);
                        if ($record && $record['status'] === 'draft') {
                            $issueResult = $this->service->issueInvoice($tenantId, $recordId, $environment);
                        }
                    }

                    $this->model->updateBatchItem((int) $item['id'], [
                        'status'       => 'success',
                        'record_id'    => $recordId,
                        'processed_at' => date('Y-m-d H:i:s'),
                    ]);
                    $successCount++;
                    $stats['updated']++;

                } catch (Exception $e) {
                    $this->model->updateBatchItem((int) $item['id'], [
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'processed_at'  => date('Y-m-d H:i:s'),
                    ]);
                    $failCount++;
                    $stats['errors']++;
                    $stats['details'][] = "Batch #{$batchId} item #{$item['id']}: " . $e->getMessage();
                }

                // Delay giữa mỗi item trong batch
                usleep(KT_EINVOICE_BATCH_ITEM_DELAY_MS * 1000);
            }

            // Cập nhật count trong session
            $this->model->db
                ->where('id', $batchId)
                ->set('success_count', 'success_count + ' . $successCount, false)
                ->set('failed_count',  'failed_count + ' . $failCount, false)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->update(db_prefix() . 'kt_einvoice_batch_sessions');

            // Kiểm tra xem session đã hoàn thành chưa
            $this->_closeBatchSession($batchId);
        }

        $this->_logCron('kt_einvoice_batch_issuer', $stats, $startTime);
    }

    /**
     * Kiểm tra xem session đã xử lý hết items chưa → đóng
     */
    private function _closeBatchSession(int $batchId): void
    {
        $remaining = $this->model->db
            ->where('batch_id', $batchId)
            ->where('status', 'queued')
            ->count_all_results(db_prefix() . 'kt_einvoice_batch_items');

        if ($remaining === 0) {
            $this->model->updateBatchSession($batchId, [
                'status'      => 'completed',
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
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
