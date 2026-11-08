<?php

$controllers = [
    dirname(__DIR__) . '/controllers/Kt_misa_callback.php',
    dirname(__DIR__) . '/controllers/Kt_misa_amis_accounting.php',
];
foreach ($controllers as $file) {
    $source = file_get_contents($file);
    if (strpos($source, "defined('KT_MISA_AMIS_ACCOUNTING_MODULE') or define") === false) throw new RuntimeException('Controller constant guard missing: ' . $file);
    if (strpos($source, 'module_dir_path(') !== false) throw new RuntimeException('Controller has include-time module_dir_path dependency: ' . $file);
}
echo "MisaControllerBootstrapTest passed.\n";
