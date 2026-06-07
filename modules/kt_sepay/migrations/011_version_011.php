<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_011 extends CI_Migration
{
    public function up()
    {
        if (!defined('KT_SEPAY_MODULE')) {
            define('KT_SEPAY_MODULE', 'kt_sepay');
        }

        if (!defined('KT_SEPAY_VERSION')) {
            define('KT_SEPAY_VERSION', '0.1.1');
        }

        require_once dirname(__DIR__) . '/install.php';
        kt_sepay_run_install();
    }

    public function down()
    {
        // KT SePay migrations are forward-only for now.
    }
}
