<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_matbao_invoice_webhook extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_MATBAO_INVOICE_MODULE . '/kt_matbao_invoice');
        $this->load->model(KT_MATBAO_INVOICE_MODULE . '/Kt_matbao_invoice_model');
    }

    public function invoice()
    {
        $this->handleWebhook('invoice');
    }

    public function signing()
    {
        $this->handleWebhook('signing');
    }

    private function handleWebhook($type)
    {
        if (strtolower((string) $this->input->method()) !== 'post') {
            return $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $expectedSecret = trim((string) get_option('kt_matbao_invoice_webhook_secret'));
        if ($expectedSecret !== '') {
            $providedSecret = trim((string) ($this->input->get_request_header('X-KT-MatBao-Secret', true) ?: $this->input->get_request_header('X-Webhook-Secret', true)));
            if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
                return $this->json(['success' => false, 'message' => 'Webhook unauthorized'], 401);
            }
        }

        $body = file_get_contents('php://input');
        $payload = json_decode((string) $body, true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $provider = 'matbao_' . $type;
        if ($this->Kt_matbao_invoice_model->is_duplicate_webhook($provider, $payload)) {
            return $this->json(['success' => true, 'matched' => false, 'duplicate' => true], 200);
        }

        $record = $this->Kt_matbao_invoice_model->find_record_for_webhook($payload, $type);
        $id = $this->Kt_matbao_invoice_model->log_webhook([
            'provider' => $provider,
            'payload' => $payload,
            'inv_id' => $payload['InvID'] ?? null,
            'document_id' => $payload['DocumentId'] ?? ($payload['document_id'] ?? null),
            'ma_so_hdon' => $payload['MaSoHDon'] ?? null,
            'ma_tra_cuu' => $payload['MaTraCuu'] ?? ($payload['Fkey'] ?? null),
            'status_code' => $payload['MaTTHDon'] ?? null,
            'status_name' => $payload['TenTTHDon'] ?? null,
            'processed' => !empty($record),
            'record_id' => $record['id'] ?? null,
            'tenant_id' => $record['tenant_id'] ?? null,
            'error_message' => $record ? '' : 'unmatched',
        ]);

        if ($record) {
            if ($type === 'signing') {
                $this->Kt_matbao_invoice_model->update_record_status_from_signing_webhook((int) $record['id'], $payload);
            } else {
                $this->Kt_matbao_invoice_model->update_record_status_from_webhook((int) $record['id'], $payload);
            }
            $this->Kt_matbao_invoice_model->mark_webhook_processed($id, (int) $record['id']);
        }

        return $this->json(['success' => true, 'matched' => !empty($record), 'webhook_log_id' => $id], 200);
    }

    private function json(array $payload, $code)
    {
        set_status_header((int) $code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
