<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_integration_cron extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper(KT_INTEGRATION_HUB_MODULE . '/kt_integration_hub');
        $this->load->model(KT_INTEGRATION_HUB_MODULE . '/Kt_integration_model');
    }

    public function process_jobs($key = '')
    {
        if (!is_cli()) {
            $expectedKey = defined('APP_CRON_KEY') ? (string) APP_CRON_KEY : '';
            if ($expectedKey === '' || !hash_equals($expectedKey, (string) $key)) {
                show_error('Unauthorized', 401);
                return;
            }
        }

        $result = $this->Kt_integration_model->process_due_jobs(50);

        if (is_cli()) {
            echo json_encode($result) . PHP_EOL;
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

}
