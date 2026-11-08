<?php

define('BASEPATH', true);
define('KT_MISA_AMIS_ACCOUNTING_MODULE', 'kt_misa_amis_accounting');
define('KT_MISA_AMIS_ACCOUNTING_VERSION', '0.1.1');
require_once dirname(__DIR__) . '/helpers/kt_misa_amis_accounting_helper.php';
require_once dirname(__DIR__) . '/libraries/MisaScope.php';
require_once dirname(__DIR__) . '/libraries/MisaSecretVault.php';
require_once dirname(__DIR__) . '/libraries/MisaMockTransport.php';
require_once dirname(__DIR__) . '/libraries/MisaTokenService.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackVerifier.php';
require_once dirname(__DIR__) . '/libraries/MisaPayloadValidator.php';
require_once dirname(__DIR__) . '/libraries/MisaPayloadBuilders.php';
require_once dirname(__DIR__) . '/libraries/MisaDryRunService.php';
require_once dirname(__DIR__) . '/libraries/MisaMappingService.php';
require_once dirname(__DIR__) . '/libraries/MisaRequestStateMachine.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackInboxService.php';
require_once dirname(__DIR__) . '/libraries/MisaApiClient.php';
require_once dirname(__DIR__) . '/libraries/MisaMockDispatcher.php';
require_once dirname(__DIR__) . '/libraries/MisaRequestLedgerService.php';

function misa_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$vault = new MisaSecretVault(function ($value) { return 'enc:' . $value; }, function ($value) { return substr($value, 4); });
$scopeA = new MisaScope(10, 100);
$scopeB = new MisaScope(10, 200);
misa_assert($scopeA->key() !== $scopeB->key(), 'Company scopes must differ.');

$token = new MisaTokenService(new MisaMockTransport(), $vault);
$connected = $token->connect(['app_id' => 'mock-app-id', 'access_code' => 'mock-access', 'org_company_code' => 'mock-company']);
misa_assert(!empty($connected['success']), 'Mock connect should pass.');
misa_assert(strpos($connected['token_encrypted'], 'enc:') === 0, 'Token must be encrypted by injected vault.');
misa_assert($token->isExpired(['token_expires_at' => '2099-01-01 00:00:00'], strtotime('2026-01-01')) === false, 'Future token must not be expired.');
misa_assert($token->isExpired(['token_expires_at' => '2000-01-01 00:00:00'], strtotime('2026-01-01')) === true, 'Expired token must be detected.');

$verifier = new MisaCallbackVerifier();
$data = '[{"org_refid":"mock-ref-1","success":true}]';
$signature = hash_hmac('sha256', $data, 'mock-app-id');
misa_assert($verifier->verify($data, strtoupper($signature), 'mock-app-id'), 'Callback HMAC must accept uppercase hex.');
misa_assert(!$verifier->verify($data . 'x', $signature, 'mock-app-id'), 'Altered callback data must fail.');
misa_assert(!$verifier->verify($data, $signature, 'wrong-app-id'), 'Wrong callback key must fail.');
$inbox = new MisaCallbackInboxService($verifier);
$record = $inbox->validateEnvelope(['app_id' => 'mock-app-id', 'org_company_code' => 'mock-company', 'data_type' => 1, 'signature' => $signature], $data, $scopeA, ['id' => 1, 'app_id' => 'mock-app-id', 'org_company_code' => 'mock-company']);
misa_assert($record['signature_valid'] === 1 && $record['tenant_id'] === 10 && $record['company_id'] === 100, 'Callback inbox must preserve verified scope.');

$client = new MisaApiClient(new MisaMockTransport(), false);
misa_assert(!empty($client->connect(['app_id' => 'mock-app-id', 'access_code' => 'mock-access', 'org_company_code' => 'mock-company'])['Success']), 'Mock client must work when external flag is off.');
$dispatcher = new MisaMockDispatcher();
misa_assert($dispatcher->dispatch(['status' => 'queued'], 'accepted')['status'] === 'awaiting_callback', 'Accepted mock request must await callback.');
misa_assert($dispatcher->dispatch(['status' => 'queued'], 'partial')['status'] === 'partially_succeeded', 'Partial mock request must produce partial state.');
misa_assert($dispatcher->dispatch(['status' => 'queued'], 'timeout')['status'] === 'retryable_failed', 'Timeout mock request must be retryable.');
$ledger = new MisaRequestLedgerService();
$draft = $ledger->draft($scopeA, 1, 'product', ['code' => 'SKU-1'], 'hash-a');
misa_assert($draft['tenant_id'] === 10 && $draft['company_id'] === 100 && $draft['status'] === 'draft', 'Request ledger draft must be scoped.');
misa_assert($ledger->transition($draft, 'queued')['status'] === 'queued', 'Request ledger transition must be explicit.');

$dryRun = new MisaDryRunService(new MisaPayloadBuilders(), new MisaPayloadValidator());
$valid = $dryRun->run('product', ['code' => 'SKU-1', 'name' => 'Mock product', 'unit' => 'piece']);
misa_assert($valid['valid'] && !$valid['external_call'] && !$valid['remote_mapping_created'], 'Valid product dry-run must be local-only.');
$invalid = $dryRun->run('product', ['code' => 'SKU-2', 'name' => 'Missing unit']);
misa_assert(!$invalid['valid'] && count($invalid['errors']) === 1, 'Missing product unit must be machine-readable validation failure.');

$mapping = new MisaMappingService();
$keyA = $mapping->idempotencyKey($scopeA, 1, 'product', ['code' => 'SKU-1'], 'upsert', 'hash-a');
$keyB = $mapping->idempotencyKey($scopeB, 1, 'product', ['code' => 'SKU-1'], 'upsert', 'hash-a');
misa_assert($keyA !== $keyB, 'Idempotency must include company scope.');

$states = new MisaRequestStateMachine();
misa_assert($states->canTransition('draft', 'queued'), 'draft -> queued must be valid.');
misa_assert(!$states->canTransition('accepted_by_misa', 'succeeded'), 'Acceptance must not imply final success.');
misa_assert($states->canTransition('awaiting_callback', 'partially_succeeded'), 'Partial callback result must be valid.');

echo "MisaFoundationContractTest passed.\n";
