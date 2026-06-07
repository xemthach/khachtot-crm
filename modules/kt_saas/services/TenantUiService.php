<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TenantUiService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Lọc danh sách menu sidebar
     */
    public function filterSidebarMenu($items)
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            return $items;
        }

        require_once __DIR__ . '/TenantEntitlementService.php';
        $service = new TenantEntitlementService();
        $profile = kt_saas_current_profile();
        if (!$profile) {
            $profile = $service->getRuntimeProfile($tenant);
        }

        $filteredItems = [];
        foreach ($items as $slug => $item) {
            $moduleCode = strtolower(trim((string) $slug));
            if (is_dir(APP_MODULES_PATH . $moduleCode)) {
                if (!$service->canUseModule($tenant['id'], $moduleCode)) {
                    continue;
                }
            }
            $filteredItems[$slug] = $item;
        }

        $this->promoteTenantEntitledModules($filteredItems, $profile);

        return $filteredItems;
    }

    public function filterSetupMenu($items)
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            return $items;
        }

        return [];
    }

    /**
     * Lọc quyền hiển thị các nút thao tác chi tiết
     */
    public function filterActionButtons($actionName, $context): bool
    {
        $tenant = kt_saas_current_tenant();
        if (!$tenant) {
            return true;
        }

        require_once __DIR__ . '/TenantEntitlementService.php';
        $service = new TenantEntitlementService();
        return $service->canUseFeature($tenant['id'], $context['module'], $actionName);
    }

    protected function promoteTenantEntitledModules(array &$items, array $profile): void
    {
        $moduleCodes = array_map('strtolower', (array) ($profile['module_codes'] ?? []));
        if (empty($moduleCodes)) {
            return;
        }

        $promotions = [
            'goals' => [
                'slug'            => 'goals',
                'name'            => 'goals',
                'href'            => admin_url('goals'),
                'icon'            => 'fa-solid fa-bars-progress',
                'position'        => 30.1,
                'utility_child'   => 'goals-tracking',
            ],
            'einvoice' => [
                'slug'            => 'einvoice',
                'name'            => 'einvoice_module_bulk_export',
                'href'            => admin_url('einvoice/tenant_settings'),
                'icon'            => 'fa-regular fa-file-text',
                'position'        => 30.2,
                'utility_child'   => 'einvoice_module_bulk_export',
            ],
        ];

        foreach ($promotions as $moduleCode => $item) {
            if (!in_array($moduleCode, $moduleCodes, true)) {
                continue;
            }

            if (!kt_saas_is_tenant_safe_module($moduleCode)) {
                continue;
            }

            if (!isset($items[$moduleCode])) {
                $items[$moduleCode] = [
                    'slug'       => $item['slug'],
                    'name'       => $item['name'],
                    'href'       => $item['href'],
                    'icon'       => $item['icon'],
                    'position'   => $item['position'],
                    'children'   => [],
                    'collapse'   => false,
                ];
            }

            if ($item['utility_child'] !== '') {
                $this->removeUtilityChild($items, $item['utility_child']);
            }
        }
    }

    protected function removeUtilityChild(array &$items, string $childSlug): void
    {
        if (!isset($items['utilities']['children']) || !is_array($items['utilities']['children'])) {
            return;
        }

        $items['utilities']['children'] = array_values(array_filter(
            $items['utilities']['children'],
            static function ($child) use ($childSlug) {
                return (string) ($child['slug'] ?? '') !== $childSlug;
            }
        ));
    }
}
