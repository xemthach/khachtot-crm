<?php

$db = new mysqli(getenv('MISA_TEST_HOST') ?: 'localhost', getenv('MISA_TEST_USER') ?: 'root', getenv('MISA_TEST_PASS') ?: '', 'khachtot_misa_tenant_a_20260730');
if ($db->connect_errno) throw new RuntimeException('Tenant A fixture DB unavailable.');
$expected = ['full_admin','view','configure','test_connection','preview','retry','cancel','payload','sensitive_metadata','unauthorized'];
foreach ($expected as $role) {
    $email = 'phase106_tenant_a_' . $role . '@example.test';
    $row = $db->query("SELECT staffid,admin,active FROM tblstaff WHERE email='" . $db->real_escape_string($email) . "'")->fetch_assoc();
    if (!$row || (int) $row['admin'] !== 0 || (int) $row['active'] !== 1) throw new RuntimeException('Invalid isolated role fixture: ' . $role);
}
echo "MisaRoleFixtureTest passed; admin_override=0.\n";
