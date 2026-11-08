<?php
declare(strict_types=1);

// Runtime-only seeder. Passwords are read from environment variables and never printed or persisted in artifacts.
$host = getenv('MISA_PHASE13C_DB_HOST') ?: '127.0.0.1';
$user = getenv('MISA_PHASE13C_DB_USER') ?: 'root';
$password = getenv('MISA_PHASE13C_DB_PASSWORD');
$dbs = ['khachtot_misa_tenant_a_20260730', 'khachtot_misa_tenant_b_20260730'];
$roles = [
    'full_admin' => ['view','configure','test_connection','preview','retry','cancel','view_payload','view_sensitive_metadata','resolve_conflict'],
    'view' => ['view'], 'configure' => ['configure'], 'test_connection' => ['test_connection'], 'preview' => ['preview'],
    'retry' => ['retry'], 'cancel' => ['cancel'], 'payload' => ['view_payload'], 'sensitive' => ['view_sensitive_metadata'], 'sensitive_metadata' => ['view_sensitive_metadata'], 'resolve_conflict' => ['resolve_conflict'], 'unauthorized' => [], 'inactive' => [],
];
$caps = ['kt_misa_view','kt_misa_configure','kt_misa_test_connection','kt_misa_preview','kt_misa_retry','kt_misa_cancel','kt_misa_view_payload','kt_misa_view_sensitive_metadata','kt_misa_resolve_conflict'];
$result = ['seeded' => [], 'databases' => $dbs, 'passwords_from_env' => true];
foreach ($dbs as $dbName) {
    $pdo = new PDO("mysql:host={$host};dbname={$dbName};charset=utf8mb4", $user, $password === false ? '' : $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach ($roles as $role => $allowed) {
        $prefix = $dbName === $dbs[0] ? 'a' : 'b';
        $email = "phase13c_tenant_{$prefix}_{$role}@example.test";
        $env = 'MISA_PHASE13C_' . strtoupper($role) . '_PASSWORD';
        $plain = getenv($env);
        if ($plain === false || $plain === '') continue;
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('SELECT staffid FROM tblstaff WHERE email=?'); $stmt->execute([$email]); $id = (int) $stmt->fetchColumn();
        $active = $role === 'inactive' ? 0 : 1;
        if ($id) $pdo->prepare('UPDATE tblstaff SET firstname=?,lastname=?,password=?,admin=0,active=?,is_not_staff=0 WHERE staffid=?')->execute(['Phase13C', ucfirst($role), $hash, $active, $id]);
        else { $pdo->prepare('INSERT INTO tblstaff (email,firstname,lastname,password,datecreated,admin,active,is_not_staff,hourly_rate) VALUES (?,?,?,?,NOW(),?,0,0,0)')->execute([$email,'Phase13C',ucfirst($role),$hash,$active]); $id=(int)$pdo->lastInsertId(); }
        $pdo->prepare('DELETE FROM tblstaff_permissions WHERE staff_id=? AND feature=?')->execute([$id,'kt_misa_amis_accounting']);
        foreach ($allowed as $cap) $pdo->prepare('INSERT INTO tblstaff_permissions (staff_id,feature,capability) VALUES (?,?,?)')->execute([$id,'kt_misa_amis_accounting','kt_misa_'.$cap]);
        $result['seeded'][] = ['database'=>$dbName,'role'=>$role,'email'=>$email,'staff_id'=>$id,'capabilities'=>array_map(static fn($c) => 'kt_misa_'.$c, $allowed)];
    }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
