<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_021 extends CI_Migration
{
    public function up()
    {
        if (!defined('KT_SAAS_MODULE')) {
            define('KT_SAAS_MODULE', 'kt_saas');
        }

        if (!defined('KT_SAAS_VERSION')) {
            define('KT_SAAS_VERSION', '0.2.1');
        }

        require_once dirname(__DIR__) . '/install.php';
    }

    public function down()
    {
        // KT SaaS migrations are forward-only for now.
    }
}
