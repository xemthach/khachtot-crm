<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_022 extends CI_Migration
{
    public function up()
    {
        if (!defined('KT_SAAS_MODULE')) {
            define('KT_SAAS_MODULE', 'kt_saas');
        }

        require_once dirname(__DIR__) . '/install.php';
    }

    public function down()
    {
        // Forward-only.
    }
}
