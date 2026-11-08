<?php

$dbName = getenv('MISA_TEST_DB') ?: 'khachtot_misa_app_runtime_20260730';
$db = new mysqli('127.0.0.1', 'root', '', $dbName);
if ($db->connect_errno) throw new RuntimeException('Cannot connect to isolated landlord DB.');
$a = $db->query("SELECT db_name FROM tblkt_saas_tenants WHERE id=101")->fetch_assoc();
$b = $db->query("SELECT db_name FROM tblkt_saas_tenants WHERE id=202")->fetch_assoc();
if (!$a || !$b || $a['db_name'] === $b['db_name'] || $a['db_name'] === $dbName || $b['db_name'] === $dbName) throw new RuntimeException('Tenant databases are not isolated.');
echo "KtSaasTenantHostIsolationTest passed; tenant_databases_distinct=pass.\n";
