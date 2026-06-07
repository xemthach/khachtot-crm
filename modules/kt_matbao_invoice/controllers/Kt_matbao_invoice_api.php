<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_matbao_invoice_api extends App_Controller
{
    public function ping()
    {
        set_status_header(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'module' => KT_MATBAO_INVOICE_MODULE]);
    }
}
