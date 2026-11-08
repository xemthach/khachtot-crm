<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');
defined('KT_SAAS_MODULE') or define('KT_SAAS_MODULE', 'kt_saas');

if (!function_exists('module_dir_path')) {
    function module_dir_path($module, $concat = '')
    {
        $base = dirname(__DIR__);
        if ($module === KT_SAAS_MODULE) {
            $base = dirname(__DIR__) . '/../kt_saas';
        }

        return rtrim($base, '/\\') . '/' . ltrim((string) $concat, '/');
    }
}

require_once dirname(__DIR__) . '/../kt_saas/services/TenantEmailProviderService.php';
require_once dirname(__DIR__) . '/libraries/DmsMailBridgeService.php';

class FakeTenantEmailProviderService extends TenantEmailProviderService
{
    public array $lastEvent = [];

    public function __construct()
    {
    }

    public function sendRegisteredEventEmail($eventKey, array $context = [], array $options = [])
    {
        $this->lastEvent = [
            'event_key' => $eventKey,
            'context' => $context,
            'options' => $options,
        ];

        return [
            'success' => true,
            'provider' => 'tenant_custom',
            'message_id' => 'mail-bridge-test',
        ];
    }
}

$provider = new FakeTenantEmailProviderService();
class FakeDmsMailBridgeService extends DmsMailBridgeService
{
    public array $lastDelivery = [];

    protected function deliverBridgeMail(int $tenantId, string $recipient, string $subject, array $context, string $idempotencyKey): array
    {
        $this->lastDelivery = compact('tenantId', 'recipient', 'subject', 'context', 'idempotencyKey');

        return [
            'success' => true,
            'provider' => 'test',
            'message_id' => 'mail-bridge-test',
        ];
    }
}

$service = new FakeDmsMailBridgeService($provider);

$tenant = [
    'id' => 27,
    'tenant_code' => 'ABC',
    'company_name' => 'ABC Company',
];

$payload = [
    'tenant_id' => 27,
    'crm_tenant_id' => 27,
    'crm_company_id' => 27,
    'tenant_code' => 'ABC',
    'crm_staff_id' => 3,
    'to' => 'staff@example.com',
    'template' => 'dms_password_reset',
    'subject' => 'Đặt lại mật khẩu DMS',
    'data' => [
        'name' => 'Xem Mini',
        'reset_url' => 'https://salesrep.test/reset-password/token',
        'expires_in_minutes' => 60,
        'login_url' => 'https://salesrep.test/login',
        'support_email' => 'support@example.com',
        'app_name' => 'Salesflow DMS',
        'companyname' => 'ABC Company',
        'email_signature' => 'Support Team',
    ],
];

$result = $service->send($tenant, $payload);

if (empty($result['success'])) {
    throw new RuntimeException('Expected DMS mail bridge to accept a valid auth payload.');
}

if (($service->lastDelivery['recipient'] ?? null) !== 'staff@example.com') {
    throw new RuntimeException('Expected recipient_email to be forwarded to CRM mail provider.');
}

if (($service->lastDelivery['context']['action_url'] ?? null) !== 'https://salesrep.test/reset-password/token') {
    throw new RuntimeException('Expected reset_url to be forwarded as action_url.');
}

$mismatch = $service->send($tenant, array_merge($payload, [
    'tenant_code' => 'WRONG',
]));

if (!empty($mismatch['success']) || ($mismatch['code'] ?? null) !== 'TENANT_SCOPE_MISMATCH') {
    throw new RuntimeException('Expected tenant scope mismatch to be rejected.');
}

$invalidTemplate = $service->send($tenant, array_merge($payload, [
    'template' => 'not-allowed',
]));

if (!empty($invalidTemplate['success']) || ($invalidTemplate['code'] ?? null) !== 'INVALID_TEMPLATE') {
    throw new RuntimeException('Expected invalid template to be rejected.');
}

echo "DmsMailBridgeServiceTest passed.\n";
