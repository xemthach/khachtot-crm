<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        return dirname(__DIR__) . '/' . ltrim((string) $concat, '/');
    }
}
if (!function_exists('kt_integration_hub_json_encode')) {
    function kt_integration_hub_json_encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
if (!function_exists('kt_integration_hub_json_decode')) {
    function kt_integration_hub_json_decode($json, $default = [])
    {
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : $default;
    }
}
if (!function_exists('kt_integration_hub_encrypt_value')) {
    function kt_integration_hub_encrypt_value($value): string
    {
        return base64_encode((string) $value);
    }
}
if (!function_exists('kt_integration_hub_decrypt_value')) {
    function kt_integration_hub_decrypt_value($value): string
    {
        return (string) base64_decode((string) $value, true);
    }
}

require_once dirname(__DIR__) . '/libraries/SyncCustomerToDmsJob.php';

class SyncCustomerToDmsJobSpy extends SyncCustomerToDmsJob
{
    public array $captured = [];

    protected function dispatchRequest($url, array $headers, $rawBody): array
    {
        $this->captured = [
            'url' => $url,
            'headers' => $headers,
            'body' => $rawBody,
        ];

        return [
            'status_code' => 200,
            'body' => json_encode([
                'success' => true,
                'code' => 'CUSTOMER_UPSERTED',
                'message' => 'Customer synchronized',
                'data' => [
                    'crm_customer_id' => 9001,
                    'dms_customer_ref_id' => 601,
                    'mapping_status' => 'synced',
                ],
            ]),
        ];
    }
}

$job = new SyncCustomerToDmsJobSpy();

$tenant = [
    'tenant_code' => 'ABC',
];

$connection = [
    'public_key' => 'bridge-token',
    'webhook_secret_encrypted' => kt_integration_hub_encrypt_value('bridge-secret'),
    'settings_json' => json_encode(['base_url' => 'https://salesflow.test']),
];

$payload = [
    'tenant' => [
        'crm_tenant_id' => 1001,
        'crm_company_id' => 1001,
        'tenant_code' => 'ABC',
    ],
    'customer' => [
        'crm_customer_id' => 9001,
        'customer_code' => 'CRM-CLIENT-9001',
        'name' => 'ABC Distributor',
        'status' => 'active',
        'updated_at' => '2026-06-08T13:05:00+07:00',
    ],
];

$result = $job->handle($tenant, $connection, [
    'payload_json' => json_encode($payload),
    'request_id' => 'req_customer_sync_1',
    'idempotency_key' => 'customer-9001-test',
]);

if (($result['status'] ?? null) !== 'synced') {
    throw new RuntimeException('Expected customer sync job to return synced status.');
}

$headers = implode("\n", $job->captured['headers'] ?? []);
if (strpos($headers, 'Authorization: Bearer bridge-token') === false) {
    throw new RuntimeException('Expected outbound customer sync request to include bearer token.');
}

if (strpos($headers, 'X-Signature: ') === false) {
    throw new RuntimeException('Expected outbound customer sync request to include signature header.');
}

if (($job->captured['url'] ?? '') !== 'https://salesflow.test/api/v1/integration/customers/upsert') {
    throw new RuntimeException('Expected outbound customer sync request to target customer upsert endpoint.');
}

echo "SyncCustomerToDmsJobTest passed.\n";
