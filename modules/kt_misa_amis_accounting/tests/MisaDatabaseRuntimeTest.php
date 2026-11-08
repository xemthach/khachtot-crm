<?php

$dbName = getenv('MISA_TEST_DB') ?: 'khachtot_misa_runtime_20260730';
$db = new mysqli(getenv('MISA_TEST_HOST') ?: 'localhost', getenv('MISA_TEST_USER') ?: 'root', getenv('MISA_TEST_PASS') ?: '', $dbName);
if ($db->connect_error) throw new RuntimeException('Database connection failed: ' . $db->connect_error);
$db->set_charset('utf8mb4');
$p = 'tbl';
$tables = ['kt_misa_audit_logs','kt_misa_attempts','kt_misa_request_items','kt_misa_requests','kt_misa_callback_inbox','kt_misa_mappings','kt_misa_connections','kt_misa_pull_cursors'];
foreach ($tables as $table) $db->query('DELETE FROM `' . $p . $table . '`');

function db_runtime_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function db_runtime_insert($db, $sql, $params = [], $types = '') {
    $stmt = $db->prepare($sql); if (!$stmt) throw new RuntimeException($db->error);
    if ($params) $stmt->bind_param($types, ...$params);
    try { $ok = $stmt->execute(); $error = $stmt->error; } catch (Throwable $e) { $ok = false; $error = $e->getMessage(); }
    $id = $stmt->insert_id; $stmt->close();
    return [$ok, $error, $id];
}

$connSql = "INSERT INTO {$p}kt_misa_connections (tenant_id,company_id,name,environment,app_id,org_company_code,status,certification_level,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())";
[$ok,, $connA] = db_runtime_insert($db, $connSql, [10,100,'A','mock','app-a','org-a','enabled','CODE_READY'], 'iissssss'); db_runtime_assert($ok, 'Connection A insert failed.');
[$ok,, $connB] = db_runtime_insert($db, $connSql, [10,200,'B','mock','app-a','org-b','enabled','CODE_READY'], 'iissssss'); db_runtime_assert($ok, 'Connection B insert failed.');
[$ok,, $connC] = db_runtime_insert($db, $connSql, [20,100,'C','mock','app-c','org-a','enabled','CODE_READY'], 'iissssss'); db_runtime_assert($ok, 'Connection C insert failed.');
[$ok,$error] = db_runtime_insert($db, $connSql, [10,100,'A-duplicate','mock','app-a','org-a','enabled','CODE_READY'], 'iissssss'); db_runtime_assert(!$ok && $error !== '', 'Duplicate scoped connection must fail.');

$row = $db->query("SELECT id FROM {$p}kt_misa_connections WHERE id=" . (int) $connB . " AND tenant_id=10 AND company_id=100")->fetch_assoc(); db_runtime_assert(!$row, 'Company A must not read Company B connection under scope query.');
$row = $db->query("SELECT id FROM {$p}kt_misa_connections WHERE id=" . (int) $connC . " AND tenant_id=10")->fetch_assoc(); db_runtime_assert(!$row, 'Tenant A must not read Tenant B connection under scope query.');

$mapSql = "INSERT INTO {$p}kt_misa_mappings (tenant_id,company_id,connection_id,object_type,local_id,local_stable_key,remote_id,mapping_status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'mapped',NOW(),NOW())";
[$ok] = db_runtime_insert($db, $mapSql, [10,100,$connA,'product',1,'product:sku-1','remote-1'], 'iiisiss'); db_runtime_assert($ok, 'Mapping A insert failed.');
[$ok] = db_runtime_insert($db, $mapSql, [10,200,$connB,'product',1,'product:sku-1','remote-1'], 'iiisiss'); db_runtime_assert($ok, 'Same mapping key in another company must be allowed.');
[$ok,$error] = db_runtime_insert($db, $mapSql, [10,100,$connA,'product',1,'product:sku-1','remote-2'], 'iiisiss'); db_runtime_assert(!$ok && $error !== '', 'Duplicate local mapping must fail.');

$requestSql = "INSERT INTO {$p}kt_misa_requests (tenant_id,company_id,connection_id,operation,object_type,local_stable_key,idempotency_key,correlation_id,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'draft',NOW(),NOW())";
[$ok,, $requestId] = db_runtime_insert($db, $requestSql, [10,100,$connA,'upsert','product','product:sku-1','idem-a','corr-a'], 'iiisssss'); db_runtime_assert($ok, 'Request insert failed.');
[$ok,$error] = db_runtime_insert($db, $requestSql, [10,100,$connA,'upsert','product','product:sku-1','idem-a','corr-a-duplicate'], 'iiisssss'); db_runtime_assert(!$ok && $error !== '', 'Duplicate idempotency key must fail.');

$itemSql = "INSERT INTO {$p}kt_misa_request_items (request_id,tenant_id,company_id,org_refid,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())";
[$ok] = db_runtime_insert($db, $itemSql, [$requestId,10,100,'org-ref-1'], 'iiis'); db_runtime_assert($ok, 'Request item insert failed.');
[$ok,$error] = db_runtime_insert($db, $itemSql, [$requestId,10,100,'org-ref-1'], 'iiis'); db_runtime_assert(!$ok && $error !== '', 'Duplicate request item must fail.');

 $callbackSql = "INSERT INTO {$p}kt_misa_callback_inbox (tenant_id,company_id,connection_id,app_id,org_company_code,data_type,signature,signature_valid,payload_hash,raw_data,processing_status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,?,?, 'received',NOW(),NOW(),NOW())";
[$ok] = db_runtime_insert($db, $callbackSql, [10,100,$connA,'app-a','org-a','1','sig-a','hash-a','[]'], 'iiissssss'); db_runtime_assert($ok, 'Callback insert failed.');
[$ok] = db_runtime_insert($db, $callbackSql, [10,100,$connA,'app-a','org-a','1','sig-a','hash-a','[]'], 'iiissssss'); db_runtime_assert($ok, 'Duplicate callback evidence must be appendable.');
$callbackCount = (int) $db->query("SELECT COUNT(*) AS c FROM {$p}kt_misa_callback_inbox WHERE payload_hash='hash-a'")->fetch_assoc()['c']; db_runtime_assert($callbackCount === 2, 'Duplicate callback evidence must be retained.');

$attemptSql = "INSERT INTO {$p}kt_misa_attempts (tenant_id,company_id,connection_id,request_id,attempt_number,outcome,started_at,created_at) VALUES (?,?,?,?,?,'started',NOW(),NOW())";
[$ok] = db_runtime_insert($db, $attemptSql, [10,100,$connA,$requestId,1], 'iiiii'); db_runtime_assert($ok, 'Attempt insert failed.');
[$ok,$error] = db_runtime_insert($db, $attemptSql, [10,100,$connA,$requestId,1], 'iiiii'); db_runtime_assert(!$ok && $error !== '', 'Duplicate attempt must fail.');

$before = (int) $db->query("SELECT COUNT(*) AS c FROM {$p}kt_misa_requests")->fetch_assoc()['c'];
$db->begin_transaction(); $db->query("INSERT INTO {$p}kt_misa_requests (tenant_id,company_id,connection_id,operation,object_type,idempotency_key,correlation_id,status,created_at,updated_at) VALUES (10,100," . (int) $connA . ",'rollback','product','rollback-idem','rollback-corr','draft',NOW(),NOW())"); $db->rollback();
$after = (int) $db->query("SELECT COUNT(*) AS c FROM {$p}kt_misa_requests")->fetch_assoc()['c']; db_runtime_assert($before === $after, 'Transaction rollback must remove staged request.');

foreach ($tables as $table) $db->query('DELETE FROM `' . $p . $table . '`');
echo "MisaDatabaseRuntimeTest passed on {$dbName}.\n";
