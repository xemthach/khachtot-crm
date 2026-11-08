<?php

$dbName = getenv('MISA_TEST_DB') ?: 'khachtot_misa_app_runtime_20260730';
$db = new mysqli('127.0.0.1', 'root', '', $dbName);
if ($db->connect_errno) throw new RuntimeException('Cannot connect to isolated landlord DB.');
$rows = $db->query("SELECT t.id,t.db_name,t.status,t.provisioning_status,d.domain FROM tblkt_saas_tenants t INNER JOIN tblkt_saas_domains d ON d.tenant_id=t.id WHERE t.id IN (101,202) ORDER BY t.id")->fetch_all(MYSQLI_ASSOC);
if (count($rows) !== 2 || $rows[0]['domain'] !== 'tenant-a.khachtot-misa.test' || $rows[1]['domain'] !== 'tenant-b.khachtot-misa.test') throw new RuntimeException('Isolated tenant registry contract failed.');
foreach ($rows as $row) if ($row['status'] !== 'active' || $row['provisioning_status'] !== 'done' || $row['db_name'] === $dbName) throw new RuntimeException('Tenant fixture is not active or points to landlord DB.');
echo "KtSaasTenantRegistryTest passed on {$dbName}.\n";
