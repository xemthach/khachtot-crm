<?php

defined('BASEPATH') or exit('No direct script access allowed');

class DmsProductWritebackService
{
    public function write(array $tenant, array $product, array $scope): array
    {
        if (empty($scope['product_writeback_enabled'])) {
            throw new RuntimeException('CRM_PRODUCT_WRITEBACK_DISABLED');
        }
        $tenantId = (int) ($tenant['id'] ?? 0);
        $companyId = (int) ($product['crm_company_id'] ?? $tenantId);
        $dmsProductId = (int) ($product['dms_product_id'] ?? 0);
        $sku = trim((string) ($product['sku'] ?? ''));
        $name = trim((string) ($product['name'] ?? ''));
        if ($tenantId <= 0 || $companyId !== $tenantId) throw new RuntimeException('TENANT_SCOPE_MISMATCH');
        if ($dmsProductId <= 0 || $sku === '' || $name === '') throw new RuntimeException('VALIDATION_ERROR');

        $db = $this->tenantDb($tenant);
        $items = db_prefix().'items';
        if (!$db->table_exists($items)) throw new RuntimeException('INTEGRATION_NOT_CONFIGURED');

        $CI = &get_instance();
        $links = db_prefix().'kt_integration_entity_links';
        $mapping = $CI->db->where(['tenant_id' => $tenantId, 'provider_code' => 'salesflow_dms', 'entity_type' => 'product', 'external_id' => (string) $dmsProductId])->get($links)->row_array();
        $itemId = (int) ($mapping['local_id'] ?? 0);
        $skuColumn = null;
        foreach (['sku_code', 'commodity_code', 'sku'] as $candidate) {
            if ($db->field_exists($candidate, $items)) {
                $skuColumn = $candidate;
                break;
            }
        }
        if ($itemId <= 0) {
            $existing = $skuColumn !== null ? $db->where($skuColumn, $sku)->get($items)->row_array() : null;
            if (!$existing && $db->field_exists('description', $items)) $existing = $db->where('description', $name)->get($items)->row_array();
            $itemId = (int) ($existing['id'] ?? 0);
        }

        $record = [];
        if ($db->field_exists('description', $items)) $record['description'] = $name;
        if ($db->field_exists('long_description', $items)) $record['long_description'] = trim((string) ($product['description'] ?? ''));
        if ($skuColumn !== null) $record[$skuColumn] = $sku;
        if ($db->field_exists('rate', $items) && array_key_exists('base_price', $product)) $record['rate'] = max(0, (float) $product['base_price']);
        if ($db->field_exists('unit', $items) && trim((string) ($product['unit'] ?? '')) !== '') $record['unit'] = trim((string) $product['unit']);

        if ($itemId > 0) {
            $db->where('id', $itemId)->update($items, $record);
        } else {
            $db->insert($items, $record);
            $itemId = (int) $db->insert_id();
        }
        if ($itemId <= 0) throw new RuntimeException('CRM_PRODUCT_WRITEBACK_FAILED');

        $link = ['tenant_id' => $tenantId, 'provider_code' => 'salesflow_dms', 'entity_type' => 'product', 'local_id' => $itemId, 'external_id' => (string) $dmsProductId, 'external_hash' => hash('sha256', json_encode($record)), 'last_synced_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($mapping) $CI->db->where('id', $mapping['id'])->update($links, $link); else { $link['created_at'] = date('Y-m-d H:i:s'); $CI->db->insert($links, $link); }

        return ['crm_product_id' => $itemId, 'dms_product_id' => $dmsProductId, 'mapping_status' => 'synced'];
    }

    private function tenantDb(array $tenant)
    {
        $CI = &get_instance();
        $password = !empty($tenant['db_password_encrypted']) ? (string) $CI->encryption->decrypt($tenant['db_password_encrypted']) : '';
        $config = ['dsn' => '', 'hostname' => (string) ($tenant['db_host'] ?? '127.0.0.1'), 'username' => (string) ($tenant['db_user'] ?? ''), 'password' => $password, 'database' => (string) ($tenant['db_name'] ?? ''), 'dbdriver' => 'mysqli', 'dbprefix' => db_prefix(), 'pconnect' => false, 'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'swap_pre' => '', 'encrypt' => false, 'compress' => false, 'stricton' => false, 'failover' => [], 'save_queries' => true, 'port' => (int) ($tenant['db_port'] ?? 3306)];
        if ($config['database'] === '' || $config['username'] === '') throw new RuntimeException('INTEGRATION_NOT_CONFIGURED');
        $db = $CI->load->database($config, true);
        if (!$db || !$db->initialize()) throw new RuntimeException('INTEGRATION_NOT_CONFIGURED');
        return $db;
    }
}
