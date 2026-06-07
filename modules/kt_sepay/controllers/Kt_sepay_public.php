<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay_public extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_SEPAY_MODULE . '/kt_sepay');
        $this->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
    }

    public function pay($requestId = 0, $token = '')
    {
        $request = $this->Kt_sepay_model->get_payment_request_by_token((int) $requestId, (string) $token);
        if (!$request) {
            show_404();
        }

        $data['title'] = 'Thanh toán qua SePay';
        $data['request'] = $request;
        $data['settings'] = $this->Kt_sepay_model->get_request_settings($request);
        $data['status_url'] = site_url('kt_sepay/status/' . (int) $request['id'] . '/' . rawurlencode((string) $request['access_token']));
        $this->load->view(KT_SEPAY_MODULE . '/payment/qr', $data);
    }

    public function status($requestId = 0, $token = '')
    {
        $request = $this->Kt_sepay_model->get_payment_request_by_token((int) $requestId, (string) $token);
        if (!$request) {
            $this->jsonResponse(['success' => false, 'message' => 'Request not found.'], 404);
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'status'  => $request['status'],
            'request' => $request,
        ]);
    }

    protected function jsonResponse(array $payload, $statusCode = 200)
    {
        set_status_header((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
