<?php

defined('BASEPATH') or exit('No direct script access allowed');

defined('KT_MISA_AMIS_ACCOUNTING_MODULE') or define('KT_MISA_AMIS_ACCOUNTING_MODULE', 'kt_misa_amis_accounting');

require_once dirname(__DIR__) . '/libraries/MisaScope.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackVerifier.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackInboxService.php';
require_once dirname(__DIR__) . '/libraries/MisaCallbackProcessor.php';

// Public callback endpoint: keep acknowledgement independent from admin
// authentication/language/session bootstrapping and do only scoped inbox work.
class Kt_misa_callback extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_MISA_AMIS_ACCOUNTING_MODULE . '/kt_misa_amis_accounting');
        $this->load->model(KT_MISA_AMIS_ACCOUNTING_MODULE . '/Kt_misa_amis_accounting_model');
    }

    public function index()
    {
        if (!kt_misa_feature_enabled('callback_enabled')) { $this->jsonResponse(['Success' => false, 'ErrorCode' => 'CALLBACK_DISABLED', 'ErrorMessage' => 'MISA callback processing is disabled.'], 503); return; }
        if (strtolower((string) $this->input->method()) !== 'post') { $this->jsonResponse(['Success' => false, 'ErrorCode' => 'METHOD_NOT_ALLOWED'], 405); return; }
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > 2 * 1024 * 1024) { $this->jsonResponse(['Success' => false, 'ErrorCode' => 'PAYLOAD_TOO_LARGE'], 413); return; }
        $envelope = json_decode($raw, true);
        if (!is_array($envelope) || !is_string($envelope['data'] ?? null)) { $this->jsonResponse(['Success' => false, 'ErrorCode' => 'INVALID_PAYLOAD'], 400); return; }
        $matches = $this->Kt_misa_amis_accounting_model->find_callback_connections($envelope['app_id'] ?? '', $envelope['org_company_code'] ?? '');
        if (count($matches) !== 1) { $this->jsonResponse(['Success' => false, 'ErrorCode' => 'UNKNOWN_OR_AMBIGUOUS_CONNECTION'], 404); return; }
        $connection = $matches[0];
        try {
            $scope = new MisaScope($connection['tenant_id'], $connection['company_id']);
            $inbox = new MisaCallbackInboxService(new MisaCallbackVerifier());
            $record = $inbox->validateEnvelope($envelope, $envelope['data'], $scope, $connection);
            $existing = $this->db->where('tenant_id', $scope->tenantId())->where('company_id', $scope->companyId())->where('connection_id', (int) $connection['id'])->where('payload_hash', $record['payload_hash'])->get(db_prefix() . 'kt_misa_callback_inbox')->row_array();
            if (empty($record['signature_valid'])) { $this->jsonResponse(['Success' => false, 'ErrorCode' => 'InvalidParam', 'ErrorMessage' => 'Signature invalid'], 401); return; }
            if ($existing) {
                $record['duplicate_of'] = (int) $existing['id'];
                $record['processing_status'] = 'duplicate';
                $this->Kt_misa_amis_accounting_model->store_callback($record);
                $this->jsonResponse(['Success' => true, 'Data' => 'duplicate']);
                return;
            }
            $record['id'] = $this->Kt_misa_amis_accounting_model->store_callback($record);
            $processing = (new MisaCallbackProcessor($this->db))->process($record, $scope, (int) $connection['id']);
            $this->jsonResponse(['Success' => true, 'Data' => $processing['status'], 'ProcessedItems' => (int) $processing['processed_items'], 'UnknownItems' => (int) $processing['unknown_items'], 'ErrorMessage' => '']);
        } catch (Throwable $e) {
            $this->jsonResponse(['Success' => false, 'ErrorCode' => 'INVALID_CALLBACK', 'ErrorMessage' => 'Callback rejected.'], 400);
        }
    }

    private function jsonResponse(array $payload, $status = 200)
    {
        $this->output->set_status_header((int) $status)->set_content_type('application/json')->set_output(json_encode($payload));
    }
}
