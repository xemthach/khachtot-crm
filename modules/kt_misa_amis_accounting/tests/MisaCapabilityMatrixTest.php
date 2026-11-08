<?php
$source = file_get_contents(dirname(__DIR__) . '/controllers/Kt_misa_amis_accounting.php');
foreach (['requireAnyCapability', 'kt_misa_configure', 'kt_misa_test_connection', 'kt_misa_preview', 'kt_misa_retry', 'kt_misa_cancel', 'kt_misa_view_payload', 'kt_misa_view_sensitive_metadata'] as $needle) {
    if (strpos($source, $needle) === false) throw new RuntimeException('Missing capability guard: ' . $needle);
}
echo "MisaCapabilityMatrixTest passed; server_side_guards=present.\n";
