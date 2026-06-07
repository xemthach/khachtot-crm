<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Quota Service
 *
 * Kiểm tra hạn mức phát hành HĐĐT theo entitlement của gói SaaS.
 * Kết hợp TenantEntitlementService (plan features) + quota usage thực tế.
 */
class KtEinvoiceQuotaService
{
    /** @var Kt_einvoice_model */
    private $model;

    /** @var TenantEntitlementService */
    private $entitlementService;

    public function __construct()
    {
        $CI = &get_instance();

        if (!isset($CI->Kt_einvoice_model)) {
            $CI->load->model('kt_einvoice/Kt_einvoice_model');
        }
        $this->model = $CI->Kt_einvoice_model;

        // Load TenantEntitlementService từ kt_saas
        if (!class_exists('TenantEntitlementService')) {
            require_once APPPATH . '../modules/kt_saas/services/TenantEntitlementService.php';
        }
        $this->entitlementService = new TenantEntitlementService();
    }

    /**
     * Kiểm tra tenant có thể phát hành thêm HĐĐT không
     *
     * @return array{allowed: bool, reason: string, remaining: int|null}
     */
    public function canIssue(int $tenantId, string $environment = 'production'): array
    {
        // 1. Kiểm tra feature flag (gói có bật eInvoice không)
        $isEnabled = $this->entitlementService->getFeatureValue($tenantId, KT_EINVOICE_FEATURE_ENABLED);
        if (!$isEnabled) {
            return [
                'allowed'   => false,
                'reason'    => _l('kt_einvoice_error_not_entitled'),
                'remaining' => 0,
            ];
        }

        // 2. Lấy monthly quota từ plan features
        $monthlyQuota = (int) $this->entitlementService->getFeatureValue($tenantId, KT_EINVOICE_FEATURE_MONTHLY_QUOTA);

        // 0 = không giới hạn (enterprise)
        if ($monthlyQuota === 0) {
            return [
                'allowed'   => true,
                'reason'    => '',
                'remaining' => null, // null = unlimited
            ];
        }

        // 3. Lấy số lượng đã dùng trong tháng này
        $year  = (int) date('Y');
        $month = (int) date('n');
        $usage = $this->model->getQuotaUsage($tenantId, $environment, $year, $month);
        $used  = (int) ($usage['used_count'] ?? 0);
        $remaining = $monthlyQuota - $used;

        if ($remaining <= 0) {
            return [
                'allowed'   => false,
                'reason'    => _l('kt_einvoice_error_quota_exceeded'),
                'remaining' => 0,
            ];
        }

        return [
            'allowed'   => true,
            'reason'    => '',
            'remaining' => $remaining,
        ];
    }

    /**
     * Kiểm tra feature riêng lẻ
     */
    public function hasFeature(int $tenantId, string $featureKey): bool
    {
        return (bool) $this->entitlementService->getFeatureValue($tenantId, $featureKey);
    }

    /**
     * Lấy max batch size cho tenant
     */
    public function getMaxBatchSize(int $tenantId): int
    {
        $maxSize = (int) $this->entitlementService->getFeatureValue($tenantId, KT_EINVOICE_FEATURE_MAX_BATCH_SIZE);
        return $maxSize > 0 ? $maxSize : KT_EINVOICE_BATCH_DEFAULT_MAX;
    }

    /**
     * Gọi sau khi phát hành thành công — tăng used_count
     */
    public function incrementUsage(int $tenantId, string $environment = 'production'): void
    {
        $this->model->incrementQuotaUsage($tenantId, $environment);
    }

    /**
     * Lấy tóm tắt quota tháng hiện tại
     */
    public function getUsageSummary(int $tenantId, string $environment = 'production'): array
    {
        $year  = (int) date('Y');
        $month = (int) date('n');

        $monthlyQuota = (int) $this->entitlementService->getFeatureValue($tenantId, KT_EINVOICE_FEATURE_MONTHLY_QUOTA);
        $usage        = $this->model->getQuotaUsage($tenantId, $environment, $year, $month);
        $used         = (int) ($usage['used_count'] ?? 0);
        $failed       = (int) ($usage['failed_count'] ?? 0);

        $unlimited = ($monthlyQuota === 0);
        $remaining = $unlimited ? null : max(0, $monthlyQuota - $used);

        return [
            'unlimited'     => $unlimited,
            'plan_quota'    => $monthlyQuota,
            'used'          => $used,
            'failed'        => $failed,
            'remaining'     => $remaining,
            'period'        => "$month/$year",
        ];
    }

    /**
     * Sync hạn mức từ SePay API → cache vào DB
     */
    public function syncFromSepay(int $tenantId, string $environment, SepayEinvoiceApiClient $apiClient): void
    {
        try {
            $usageData      = $apiClient->checkUsage();
            $quotaRemaining = $usageData['data']['remaining'] ?? null;
            $this->model->updateQuotaCache($tenantId, $environment, $quotaRemaining);
        } catch (Exception $e) {
            log_message('error', "[kt_einvoice] Quota sync failed for tenant $tenantId: " . $e->getMessage());
        }
    }
}
