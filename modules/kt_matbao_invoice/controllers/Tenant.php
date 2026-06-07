<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!defined('KT_MATBAO_INVOICE_MODULE')) {
    define('KT_MATBAO_INVOICE_MODULE', 'kt_matbao_invoice');
}
if (!defined('KT_MATBAO_INVOICE_VERSION')) {
    define('KT_MATBAO_INVOICE_VERSION', '0.1.0');
}

require_once __DIR__ . '/Kt_matbao_invoice_tenant.php';

class Tenant extends Kt_matbao_invoice_tenant
{
    public function __construct()
    {
        try {
            parent::__construct();
        } catch (Throwable $e) {
            log_message('error', 'KT MatBao Tenant bridge bootstrap failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            show_error('KT MatBao tenant bootstrap failed: ' . html_escape($e->getMessage()), 500);
        }
    }
}
