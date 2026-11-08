<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('KT_INTEGRATION_HUB_MODULE') or define('KT_INTEGRATION_HUB_MODULE', 'kt_integration_hub');

require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';
require_once dirname(__DIR__) . '/libraries/CustomerPayloadProducer.php';

$producer = new CustomerPayloadProducer();

$tenant = [
    'id' => 1001,
    'landlord_client_id' => 2001,
    'tenant_code' => 'ABC',
];

$client = [
    'userid' => 9001,
    'company' => 'ABC Distributor',
    'vat' => '0101234567',
    'phonenumber' => '0909000111',
    'address' => '123 Nguyen Hue',
    'city' => 'Quan 1',
    'state' => 'TP HCM',
    'active' => 1,
    'datecreated' => '2026-06-01 10:00:00',
];

$primaryContact = [
    'id' => 7001,
    'firstname' => 'Tran',
    'lastname' => 'Van A',
    'email' => 'buyer@abc.vn',
    'phonenumber' => '0909000111',
    'is_primary' => 1,
];

$payload = $producer->buildPayload($tenant, $client, $primaryContact, [$primaryContact]);

if (($payload['tenant']['crm_tenant_id'] ?? null) !== 1001) {
    throw new RuntimeException('Expected tenant id to be mapped into customer payload.');
}

if (($payload['customer']['crm_customer_id'] ?? null) !== 9001) {
    throw new RuntimeException('Expected crm_customer_id to match tblclients.userid.');
}

if (($payload['customer']['customer_code'] ?? null) !== 'CRM-CLIENT-9001') {
    throw new RuntimeException('Expected fallback customer_code to be derived from userid when no dedicated field exists.');
}

$payloadWithoutContact = $producer->buildPayload($tenant, $client, null, []);
if (!array_key_exists('primary_contact', $payloadWithoutContact['customer']) || $payloadWithoutContact['customer']['primary_contact'] !== null) {
    throw new RuntimeException('Expected contact data to remain optional.');
}

try {
    $producer->buildPayload($tenant, [], null, []);
    throw new RuntimeException('Expected invalid customer payload to throw.');
} catch (RuntimeException $exception) {
    if (strpos($exception->getMessage(), 'valid customer id') === false) {
        throw $exception;
    }
}

echo "CustomerPayloadProducerTest passed.\n";
