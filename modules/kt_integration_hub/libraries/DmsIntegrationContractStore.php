<?php

defined('BASEPATH') or exit('No direct script access allowed');

class DmsIntegrationContractStore
{
    private $ci;

    public function __construct()
    {
        $this->ci = &get_instance();
    }

    public function ensureSchema(): bool
    {
        $db = $this->db();
        $charset = $db->char_set ?: 'utf8mb4';
        $collation = stripos((string) $db->dbcollat, '0900') !== false ? 'utf8mb4_unicode_ci' : ($db->dbcollat ?: 'utf8mb4_unicode_ci');
        $prefix = db_prefix();
        $queries = [
            "CREATE TABLE IF NOT EXISTS `{$prefix}dms_integration_requests` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`tenant_id` INT UNSIGNED NOT NULL,`company_id` INT UNSIGNED NOT NULL,`request_id` VARCHAR(191) NOT NULL,`correlation_id` VARCHAR(191) NOT NULL,`idempotency_key` VARCHAR(191) NULL,`operation` VARCHAR(128) NOT NULL,`payload_checksum` CHAR(64) NOT NULL,`status` VARCHAR(32) NOT NULL DEFAULT 'processing',`http_status` SMALLINT UNSIGNED NULL,`response_json` MEDIUMTEXT NULL,`error_code` VARCHAR(64) NULL,`error_message` TEXT NULL,`created_at` DATETIME NULL,`updated_at` DATETIME NULL,PRIMARY KEY (`id`),UNIQUE KEY `tenant_request` (`tenant_id`,`company_id`,`request_id`),UNIQUE KEY `tenant_idempotency` (`tenant_id`,`company_id`,`operation`,`idempotency_key`),KEY `tenant_status` (`tenant_id`,`company_id`,`status`)) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
            "CREATE TABLE IF NOT EXISTS `{$prefix}dms_sync_health_ledger` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`tenant_id` INT UNSIGNED NOT NULL,`company_id` INT UNSIGNED NOT NULL,`entity_type` VARCHAR(64) NOT NULL,`source_id` VARCHAR(191) NOT NULL,`target_id` VARCHAR(191) NULL,`correlation_id` VARCHAR(191) NULL,`status` VARCHAR(32) NOT NULL,`last_error_code` VARCHAR(64) NULL,`last_error_message` TEXT NULL,`last_synced_at` DATETIME NULL,`created_at` DATETIME NULL,`updated_at` DATETIME NULL,PRIMARY KEY (`id`),UNIQUE KEY `tenant_entity_source` (`tenant_id`,`company_id`,`entity_type`,`source_id`),KEY `tenant_status` (`tenant_id`,`company_id`,`status`),KEY `tenant_synced` (`tenant_id`,`company_id`,`last_synced_at`)) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
            "CREATE TABLE IF NOT EXISTS `{$prefix}dms_mapping_issues` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`tenant_id` INT UNSIGNED NOT NULL,`company_id` INT UNSIGNED NOT NULL,`entity_type` VARCHAR(64) NOT NULL,`source_id` VARCHAR(191) NOT NULL,`target_id` VARCHAR(191) NULL,`correlation_id` VARCHAR(191) NULL,`issue_code` VARCHAR(64) NOT NULL,`status` VARCHAR(32) NOT NULL DEFAULT 'open',`retry_policy` VARCHAR(32) NOT NULL DEFAULT 'data_correction',`last_error_message` TEXT NULL,`resolved_at` DATETIME NULL,`created_at` DATETIME NULL,`updated_at` DATETIME NULL,PRIMARY KEY (`id`),UNIQUE KEY `tenant_issue` (`tenant_id`,`company_id`,`entity_type`,`source_id`,`issue_code`),KEY `tenant_status` (`tenant_id`,`company_id`,`status`)) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
        ];
        foreach ($queries as $sql) {
            if (!$db->query($sql)) {
                return false;
            }
        }
        return true;
    }

    public function beginRequest(int $tenantId, int $companyId, string $operation, string $requestId, string $idempotencyKey, array $payload): array
    {
        $this->assertScope($tenantId, $companyId);
        $checksum = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $table = db_prefix().'dms_integration_requests';
        if ($idempotencyKey !== '') {
            $existing = $this->db()->where(['tenant_id' => $tenantId, 'company_id' => $companyId, 'operation' => $operation, 'idempotency_key' => $idempotencyKey])->get($table)->row_array();
            if ($existing) {
                if (!hash_equals((string) $existing['payload_checksum'], $checksum)) {
                    throw new RuntimeException('IDEMPOTENCY_KEY_REUSED');
                }
                return ['duplicate' => true, 'record' => $existing, 'response' => json_decode((string) ($existing['response_json'] ?? ''), true) ?: []];
            }
        }
        $now = date('Y-m-d H:i:s');
        $this->db()->insert($table, ['tenant_id' => $tenantId, 'company_id' => $companyId, 'request_id' => $requestId, 'correlation_id' => $requestId, 'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null, 'operation' => $operation, 'payload_checksum' => $checksum, 'status' => 'processing', 'created_at' => $now, 'updated_at' => $now]);
        return ['duplicate' => false, 'id' => (int) $this->db()->insert_id()];
    }

    public function completeRequest(int $id, array $response, int $status = 200): void
    {
        $this->db()->where('id', $id)->update(db_prefix().'dms_integration_requests', ['status' => 'completed', 'http_status' => $status, 'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function recordHealth(int $tenantId, int $companyId, string $entityType, string $sourceId, string $status, ?string $targetId = null, ?string $code = null, ?string $message = null, ?string $correlationId = null): void
    {
        $this->assertScope($tenantId, $companyId);
        $table = db_prefix().'dms_sync_health_ledger';
        $key = ['tenant_id' => $tenantId, 'company_id' => $companyId, 'entity_type' => $entityType, 'source_id' => $sourceId];
        $row = $key + ['target_id' => $targetId, 'correlation_id' => $correlationId, 'status' => $status, 'last_error_code' => $code, 'last_error_message' => $message, 'last_synced_at' => in_array($status, ['synced','duplicate'], true) ? date('Y-m-d H:i:s') : null, 'updated_at' => date('Y-m-d H:i:s')];
        if ($this->db()->where($key)->count_all_results($table)) $this->db()->where($key)->update($table, $row); else { $row['created_at'] = date('Y-m-d H:i:s'); $this->db()->insert($table, $row); }
    }

    public function readiness(int $tenantId, int $companyId): array
    {
        $this->assertScope($tenantId, $companyId);
        $links = db_prefix().'kt_integration_entity_links';
        $count = function (string $type, bool $failed = false) use ($tenantId, $links): int {
            $q = $this->db()->where('tenant_id', $tenantId)->where('provider_code', 'salesflow_dms')->where('entity_type', $type);
            if ($failed && $this->db()->field_exists('sync_status', $links)) $q->where('sync_status', 'failed');
            return (int) $q->count_all_results($links);
        };
        $issues = count($this->issues($tenantId, $companyId, 1000));
        $health = (int) $this->db()->where(['tenant_id' => $tenantId, 'company_id' => $companyId, 'status' => 'failed'])->count_all_results(db_prefix().'dms_sync_health_ledger');
        $staffMappings = $count('staff');
        $customerStoreMappings = $count('customer');
        $productMappings = $count('product');
        $warehouseCatalogMappings = $count('warehouse');
        $warehouseStoreMappings = $count('warehouse_store') + $count('warehouse_store_mapping');
        $coreMappingsReady = $staffMappings > 0 && $customerStoreMappings > 0 && $productMappings > 0;
        $status = ($issues + $health) > 0 ? 'BLOCKED' : ($coreMappingsReady ? 'READY' : 'PARTIAL');
        $warehouseCatalogState = $warehouseCatalogMappings > 0 ? 'WAREHOUSE_CATALOG_READY' : 'WAREHOUSE_CATALOG_REQUIRED';
        $inventoryState = $warehouseStoreMappings > 0 ? 'WAREHOUSE_STORE_MAPPING_READY' : 'WAREHOUSE_STORE_MAPPING_REQUIRED';

        return [
            'status' => $status,
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'staff_mappings' => $staffMappings,
            'customer_store_mappings' => $customerStoreMappings,
            'product_mappings' => $productMappings,
            'warehouse_catalog_mappings' => $warehouseCatalogMappings,
            'warehouse_store_mappings' => $warehouseStoreMappings,
            'open_issues' => $issues,
            'failed_health_rows' => $health,
            'warehouse_policy' => 'W3_ONE_DMS_WAREHOUSE_MANY_CRM_STORES',
            'warehouse_catalog' => $warehouseCatalogState,
            'warehouse_catalog_readiness' => $warehouseCatalogState,
            'inventory' => $inventoryState,
            'inventory_readiness' => $inventoryState,
            'outbound_collection' => 'MANUAL_REVIEW_ONLY',
            'outbound_collection_readiness' => 'READY_MANUAL_REVIEW_ONLY',
            'delivery_projection' => 'STATUS_EVIDENCE_EXCEPTION_ONLY',
            'return_projection' => 'REQUEST_RECEIPT_ONLY',
            'supervisor_policy' => 'MONITOR_ONLY',
            'notifications' => 'NO_V1',
            'reports' => 'OPERATIONAL_VIEWS_ONLY',
        ];
    }

    public function issues(int $tenantId, int $companyId, int $limit = 100): array
    {
        $this->assertScope($tenantId, $companyId);
        $rows = $this->db()->select('id,entity_type,source_id,target_id,issue_code,status,retry_policy,last_error_message,updated_at')
            ->where(['tenant_id' => $tenantId, 'company_id' => $companyId, 'status' => 'open'])
            ->order_by('id', 'desc')->limit($limit)
            ->get(db_prefix().'dms_mapping_issues')->result_array();

        $remaining = max(0, $limit - count($rows));
        if ($remaining > 0 && $this->db()->table_exists(db_prefix().'kt_integration_sync_jobs')) {
            $jobs = $this->db()->select('id,entity_type,external_id,job_type,error_message,status,updated_at')
                ->where(['tenant_id' => $tenantId, 'provider_code' => 'salesflow_dms', 'status' => 'failed'])
                ->order_by('id', 'desc')->limit($remaining)
                ->get(db_prefix().'kt_integration_sync_jobs')->result_array();
            foreach ($jobs as $job) {
                $message = trim((string) ($job['error_message'] ?? ''));
                $businessConflict = preg_match('/NOT_MAPPED|TENANT_SCOPE|VALIDATION|IDEMPOTENCY/i', $message) === 1;
                $rows[] = [
                    'id' => 'job:'.(int) $job['id'],
                    'entity_type' => (string) ($job['entity_type'] ?: $job['job_type']),
                    'source_id' => (string) ($job['external_id'] ?: $job['id']),
                    'target_id' => null,
                    'issue_code' => $businessConflict ? 'DATA_CORRECTION_REQUIRED' : 'INFRASTRUCTURE_FAILURE',
                    'status' => 'open',
                    'retry_policy' => $businessConflict ? 'data_correction' : 'idempotent_retry',
                    'last_error_message' => $message,
                    'updated_at' => $job['updated_at'] ?? null,
                ];
            }
        }

        return $rows;
    }

    private function assertScope(int $tenantId, int $companyId): void
    {
        if ($tenantId <= 0 || $companyId <= 0) throw new InvalidArgumentException('TENANT_SCOPE_MISMATCH');
    }

    private function db()
    {
        $landlordDb = $this->ci->config->item('kt_saas_landlord_db');
        if ($landlordDb && $landlordDb->table_exists(db_prefix() . 'kt_saas_tenants')) {
            return $landlordDb;
        }

        if ($this->ci->db->table_exists(db_prefix() . 'kt_saas_tenants')) {
            $this->ci->config->set_item('kt_saas_landlord_db', $this->ci->db);
            return $this->ci->db;
        }

        $config = [
            'dsn'          => '',
            'hostname'     => APP_DB_HOSTNAME,
            'username'     => APP_DB_USERNAME,
            'password'     => APP_DB_PASSWORD,
            'database'     => 'khachtot',
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => false,
            'cache_on'     => false,
            'cachedir'     => '',
            'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre'     => '',
            'encrypt'      => defined('APP_DB_ENCRYPT') ? APP_DB_ENCRYPT : false,
            'compress'     => false,
            'stricton'     => false,
            'failover'     => [],
            'save_queries' => true,
        ];
        $db = $this->ci->load->database($config, true);
        $this->ci->config->set_item('kt_saas_landlord_db', $db);

        return $db;
    }
}
