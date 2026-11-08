<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';
require_once dirname(__DIR__) . '/libraries/StaffPayloadProducer.php';

$producer = new StaffPayloadProducer();

$tenant = [
    'id' => 1001,
    'landlord_client_id' => 77,
    'tenant_code' => 'ABC',
];

$staff = [
    'staffid' => 345,
    'email' => 'sale1@example.com',
    'firstname' => 'An',
    'lastname' => 'Nguyen',
    'phonenumber' => '0909000111',
    'admin' => 0,
    'role' => 2,
    'active' => 1,
    'default_language' => 'vi',
    'datecreated' => '2026-06-01 10:00:00',
    'last_login' => '2026-06-08 09:00:00',
    'last_activity' => '2026-06-08 09:05:00',
    'last_password_change' => '2026-06-07 08:00:00',
];

$role = [
    'roleid' => 2,
    'name' => 'Field Sales',
];

$payload = $producer->buildPayload($tenant, $staff, $role);

if (($payload['tenant']['crm_tenant_id'] ?? null) !== 1001) {
    throw new RuntimeException('Expected tenant id to be mapped into payload.');
}

if (($payload['tenant']['crm_company_id'] ?? null) !== 77 || array_key_exists('compatibility_crm_company_id', $payload['tenant'])) {
    throw new RuntimeException('Expected one canonical company identity without compatibility fallback.');
}

if (($payload['staff']['crm_staff_id'] ?? null) !== 345) {
    throw new RuntimeException('Expected crm_staff_id to match tblstaff.staffid.');
}

if (($payload['staff']['role_code'] ?? null) !== 'field_sales') {
    throw new RuntimeException('Expected role name to normalize to field_sales.');
}

$idempotencyKey = $producer->buildIdempotencyKey($payload);
if (strpos($idempotencyKey, 'staff-v2-345-') !== 0) {
    throw new RuntimeException('Expected idempotency key to include crm_staff_id prefix.');
}

echo "StaffPayloadProducerTest passed.\n";
