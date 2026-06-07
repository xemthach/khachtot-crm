<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_matbao_invoice_tenant extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(KT_MATBAO_INVOICE_MODULE . '/kt_matbao_invoice');
        $this->load->model(KT_MATBAO_INVOICE_MODULE . '/Kt_matbao_invoice_model');
        $this->load->library(KT_MATBAO_INVOICE_MODULE . '/Matbao_invoice_client');
        $this->load->library(KT_MATBAO_INVOICE_MODULE . '/Matbao_sign_client');
    }

    private function guardAccess()
    {
        if (!kt_matbao_invoice_tenant_can_access()) {
            access_denied('MatBao Invoice entitlement required');
        }
    }

    private function tenant()
    {
        return function_exists('kt_saas_current_tenant') ? (kt_saas_current_tenant() ?: []) : [];
    }

    private function requireTenantFeature($tenantId, $featureKey, $fallback = false)
    {
        if (!$this->Kt_matbao_invoice_model->tenant_feature_allowed((int) $tenantId, (string) $featureKey, $fallback)) {
            access_denied('MatBao Invoice feature denied: ' . $featureKey);
        }
    }

    private function requirePost()
    {
        if (strtoupper((string) $this->input->method()) !== 'POST') {
            set_alert('warning', 'Invalid request method. Please submit from the action buttons.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }
    }

    private function dispatchPhase3DEmail($eventKey, array $context = [], array $options = [])
    {
        if (!function_exists('kt_saas_send_email_event')) {
            require_once module_dir_path('kt_saas', 'helpers/kt_saas_helper.php');
        }

        if (!function_exists('kt_saas_send_email_event')) {
            return;
        }

        kt_saas_send_email_event($eventKey, $context, $options);
    }

    private function pick_matbao_payload(array $response)
    {
        $root = $response['Data'] ?? ($response['data'] ?? $response);
        if (is_array($root) && isset($root[0]) && is_array($root[0])) {
            $first = $root[0];
            if (isset($first['data']) && is_array($first['data'])) {
                return $first['data'];
            }
            if (isset($first['Data']) && is_array($first['Data'])) {
                return $first['Data'];
            }
            return $first;
        }
        return is_array($root) ? $root : [];
    }

    private function pick_val(array $data, array $keys, $default = '')
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                return $data[$k];
            }
        }
        return $default;
    }

    private function is_identifier_mismatch($localValue, $remoteValue)
    {
        $local = trim((string) $localValue);
        $remote = trim((string) $remoteValue);
        if ($local === '' || $remote === '') {
            return false;
        }
        return strcasecmp($local, $remote) !== 0;
    }

    private function should_accept_remote_identity(array $record, $remoteInvId, $remoteMaSo, $remoteMaTra)
    {
        $localMaTra = trim((string) ($record['ma_tra_cuu'] ?? ''));
        $remoteMaTra = trim((string) $remoteMaTra);
        $localInvId = trim((string) ($record['inv_id'] ?? ''));
        $remoteInvId = trim((string) $remoteInvId);

        // If MaTraCuu matches, treat remote identity as authoritative and self-heal local IDs.
        if ($localMaTra !== '' && $remoteMaTra !== '' && strcasecmp($localMaTra, $remoteMaTra) === 0) {
            return true;
        }

        // Demo/detail APIs may return missing MaTraCuu even when lookup is correct.
        // In this case, don't fail hard if local MaTraCuu exists and remote MaTraCuu is blank.
        if ($localMaTra !== '' && $remoteMaTra === '') {
            return true;
        }

        // When local InvID is missing and remote returns one, allow self-heal.
        if ($localInvId === '' && $remoteInvId !== '') {
            return true;
        }

        $localMaSo = $this->normalize_maso_hdon($record['ma_so_hdon'] ?? '');
        if ($this->is_identifier_mismatch($localInvId, $remoteInvId)) {
            return false;
        }
        if ($this->is_identifier_mismatch($localMaSo, $remoteMaSo)) {
            return false;
        }
        if ($this->is_identifier_mismatch($localMaTra, $remoteMaTra)) {
            return false;
        }
        return true;
    }

    private function looks_like_encoded_blob($value)
    {
        $v = trim((string) $value);
        if ($v === '' || strlen($v) < 40) {
            return false;
        }
        // Typical broken value we saw: very long base64-like token ending with '='.
        return (bool) preg_match('/^[A-Za-z0-9+\/=]{40,}$/', $v);
    }

    private function normalize_maso_hdon($value)
    {
        $v = trim((string) $value);
        if ($this->looks_like_encoded_blob($v)) {
            return '';
        }
        return $v;
    }

    private function stream_remote_file($url, $ext, $recordId)
    {
        $target = trim((string) $url);
        if ($target === '') {
            return false;
        }

        $ch = curl_init($target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $bin = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $httpCode < 200 || $httpCode >= 300 || !is_string($bin) || $bin === '') {
            return false;
        }

        $filename = 'matbao_' . (int) $recordId . '.' . $ext;
        header('Content-Type: ' . ($ext === 'xml' ? 'application/xml' : 'application/pdf'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $bin;
        return true;
    }

    private function extract_maso_hdon_from_response(array $response, array $payload)
    {
        $candidates = [
            $this->pick_val($payload, ['MaSoHDon', 'maSoHDon', 'ma_so_hdon'], ''),
            $this->pick_val($response, ['MaSoHDon', 'maSoHDon', 'ma_so_hdon'], ''),
        ];

        $root = $response['Data'] ?? ($response['data'] ?? null);
        if (is_array($root)) {
            $candidates[] = $this->pick_val($root, ['MaSoHDon', 'maSoHDon', 'ma_so_hdon'], '');
            if (isset($root[0]) && is_array($root[0])) {
                $candidates[] = $this->pick_val($root[0], ['MaSoHDon', 'maSoHDon', 'ma_so_hdon'], '');
                if (isset($root[0]['data']) && is_array($root[0]['data'])) {
                    $candidates[] = $this->pick_val($root[0]['data'], ['MaSoHDon', 'maSoHDon', 'ma_so_hdon'], '');
                }
                if (isset($root[0]['Data']) && is_array($root[0]['Data'])) {
                    $candidates[] = $this->pick_val($root[0]['Data'], ['MaSoHDon', 'maSoHDon', 'ma_so_hdon'], '');
                }
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize_maso_hdon($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    public function index()
    {
        $this->guardAccess();
        $tenant = $this->tenant();
        $data['title'] = _l('kt_matbao_invoice_overview');
        $data['tenant'] = $tenant;
        $data['records'] = $this->Kt_matbao_invoice_model->get_records((int) $tenant['id'], 20);
        $data['logs'] = $this->Kt_matbao_invoice_model->get_logs((int) $tenant['id'], 20);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/tenant/overview', $data);
    }

    public function settings()
    {
        $this->guardAccess();
        if (!kt_matbao_invoice_tenant_can_configure()) {
            access_denied('Tenant config not allowed by plan');
        }

        $tenant = $this->tenant();
        if ($this->input->post()) {
            $this->Kt_matbao_invoice_model->save_settings($this->input->post(), (int) $tenant['id'], 'tenant');
            set_alert('success', 'Saved tenant MatBao settings.');
            redirect(admin_url('kt_matbao_invoice/tenant/settings'));
        }

        $data['title'] = _l('kt_matbao_invoice_settings');
        $data['settings'] = $this->Kt_matbao_invoice_model->get_settings((int) $tenant['id'], 'tenant');
        $data['ca_settings'] = $this->Kt_matbao_invoice_model->get_ca_account((int) $tenant['id'], 'tenant');
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/tenant/settings', $data);
    }

    public function test_connection()
    {
        $this->guardAccess();
        if (!kt_matbao_invoice_tenant_can_configure()) {
            access_denied('Tenant config not allowed by plan');
        }
        $this->requirePost();

        $tenant = $this->tenant();
        $settings = $this->Kt_matbao_invoice_model->get_settings((int) $tenant['id'], 'tenant');
        if (!$settings) {
            set_alert('warning', 'No tenant settings to test.');
            redirect(admin_url('kt_matbao_invoice/tenant/settings'));
        }

        $result = $this->matbao_invoice_client->login($settings, (int) ($tenant['id'] ?? 0));
        $status = !empty($result['success']) ? 'success' : 'error';
        $message = !empty($result['success']) ? 'Connection success.' : ('Connection failed: ' . ($result['error'] ?: 'HTTP ' . (int) ($result['http_code'] ?? 0)));
        $this->Kt_matbao_invoice_model->update_test_result((int) $settings['id'], $status, $message);
        set_alert(!empty($result['success']) ? 'success' : 'warning', $message);
        redirect(admin_url('kt_matbao_invoice/tenant/settings'));
    }

    public function test_ca_connection()
    {
        $this->guardAccess();
        if (!kt_matbao_invoice_tenant_can_configure()) {
            access_denied('Tenant config not allowed by plan');
        }
        $this->requirePost();

        $tenant = $this->tenant();
        $ca = $this->Kt_matbao_invoice_model->get_ca_account((int) $tenant['id'], 'tenant');
        if (!$ca || empty($ca['is_active'])) {
            $resolved = $this->Kt_matbao_invoice_model->resolve_tenant_effective_ca_account($tenant);
            $ca = is_array($resolved['settings'] ?? null) ? $resolved['settings'] : null;
        }
        if (!$ca) {
            set_alert('warning', 'No CA/HSM settings to test.');
            redirect(admin_url('kt_matbao_invoice/tenant/settings'));
        }

        $result = $this->matbao_sign_client->login($ca, (int) ($tenant['id'] ?? 0));
        $message = !empty($result['success']) ? 'CA/HSM connection success.' : ('CA/HSM connection failed: ' . ($result['error'] ?: 'HTTP ' . (int) ($result['http_code'] ?? 0)));
        set_alert(!empty($result['success']) ? 'success' : 'warning', $message);
        redirect(admin_url('kt_matbao_invoice/tenant/settings'));
    }

    public function invoices()
    {
        $this->guardAccess();
        $tenant = $this->tenant();
        $data['title'] = _l('kt_matbao_invoice_invoices');
        $data['tenant'] = $tenant;
        $data['records'] = $this->Kt_matbao_invoice_model->get_records((int) $tenant['id'], 200);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/tenant/invoices', $data);
    }

    public function usage()
    {
        $this->guardAccess();
        $tenant = $this->tenant();
        $data['title'] = _l('kt_matbao_invoice_usage');
        $data['summary'] = $this->Kt_matbao_invoice_model->get_tenant_addon_usage_summary((int) ($tenant['id'] ?? 0));
        $data['addons'] = $this->Kt_matbao_invoice_model->get_tenant_addons((int) ($tenant['id'] ?? 0));
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/tenant/usage', $data);
    }

    public function logs()
    {
        $this->guardAccess();
        $tenant = $this->tenant();
        $data['title'] = _l('kt_matbao_invoice_logs');
        $data['logs'] = $this->Kt_matbao_invoice_model->get_logs((int) $tenant['id'], 300);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/tenant/logs', $data);
    }

    public function addons()
    {
        $this->guardAccess();
        $tenant = $this->tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $data['title'] = _l('kt_matbao_invoice_tenant_addons');
        $data['packages'] = $this->Kt_matbao_invoice_model->get_active_reseller_packages_for_tenant();
        $data['orders'] = $this->Kt_matbao_invoice_model->get_orders($tenantId);
        $data['addons'] = $this->Kt_matbao_invoice_model->get_tenant_addons($tenantId);
        $data['summary'] = $this->Kt_matbao_invoice_model->get_tenant_addon_usage_summary($tenantId);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/tenant/addons', $data);
    }

    public function buy_addons()
    {
        $this->guardAccess();
        $this->requirePost();
        $tenant = $this->tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);

        $selected = $this->input->post('items');
        $items = [];
        if (is_array($selected)) {
            foreach ($selected as $packageId => $qty) {
                $items[] = [
                    'package_id' => (int) $packageId,
                    'quantity' => (float) $qty,
                ];
            }
        }

        $result = $this->Kt_matbao_invoice_model->create_tenant_addon_order($tenantId, $items);
        if (empty($result['success'])) {
            set_alert('warning', (string) ($result['message'] ?? 'Unable to create add-on order.'));
            redirect(admin_url('kt_matbao_invoice/tenant/addons'));
        }

        set_alert('success', 'Created add-on order ' . (string) ($result['order_code'] ?? '') . '. Please proceed payment.');
        redirect(admin_url('kt_matbao_invoice/tenant/order/' . (int) ($result['order_id'] ?? 0)));
    }

    public function buy_package()
    {
        return $this->buy_addons();
    }

    public function checkout_addon()
    {
        return $this->buy_addons();
    }

    public function order($orderId)
    {
        $this->guardAccess();
        $tenant = $this->tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $order = $this->Kt_matbao_invoice_model->get_order_by_tenant((int) $orderId, $tenantId);
        if (!$order) {
            show_404();
        }

        $data['title'] = 'Đơn dịch vụ bổ sung #' . (int) $order['id'];
        $data['order'] = $order;
        $data['items'] = $this->Kt_matbao_invoice_model->get_order_items((int) $order['id']);
        $this->load->view(KT_MATBAO_INVOICE_MODULE . '/tenant/order', $data);
    }

    public function pay_order($orderId)
    {
        $this->guardAccess();
        $this->requirePost();

        $tenant = $this->tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $order = $this->Kt_matbao_invoice_model->get_order_by_tenant((int) $orderId, $tenantId);
        if (!$order) {
            show_404();
        }

        if ((string) ($order['payment_status'] ?? '') === 'paid') {
            set_alert('success', 'Đơn hàng này đã được thanh toán.');
            redirect(admin_url('kt_matbao_invoice/tenant/order/' . (int) $order['id']));
        }

        try {
            $this->load->helper('kt_sepay/kt_sepay');
            $this->load->model('kt_sepay/Kt_sepay_model');
            $this->load->library('kt_sepay/Kt_sepay_gateway');

            if (!$this->Kt_sepay_model->is_active(null, false)) {
                set_alert('warning', 'Thanh toán trực tuyến hiện chưa khả dụng. Vui lòng liên hệ bộ phận hỗ trợ.');
                redirect(admin_url('kt_matbao_invoice/tenant/order/' . (int) $order['id']));
            }

            $requestId = $this->kt_sepay_gateway->createMatbaoOrderRequest($order, $tenant);
            $request = $requestId > 0 ? $this->Kt_sepay_model->get_payment_request($requestId) : null;
            if (!$request || empty($request['access_token'])) {
                throw new RuntimeException('Unable to create MatBao SePay payment request.');
            }

            redirect(site_url('kt_sepay/pay/' . (int) $request['id'] . '/' . rawurlencode((string) $request['access_token'])));
        } catch (Throwable $e) {
            log_message('error', 'KT MatBao Invoice SePay checkout failed for order ' . (int) $orderId . ': ' . $e->getMessage());
            set_alert('warning', 'Chưa thể khởi tạo thanh toán. Vui lòng thử lại hoặc liên hệ hỗ trợ.');
            redirect(admin_url('kt_matbao_invoice/tenant/order/' . (int) $order['id']));
        }
    }

    public function create_draft($invoiceId)
    {
        $tenant = $this->tenant();
        $this->requireTenantFeature((int) ($tenant['id'] ?? 0), 'matbao_invoice.create_draft', true);
        return $this->createOrIssue($invoiceId, 0);
    }

    public function issue($invoiceId)
    {
        $tenant = $this->tenant();
        $this->requireTenantFeature((int) ($tenant['id'] ?? 0), 'matbao_invoice.issue_invoice', true);
        return $this->createOrIssue($invoiceId, 1);
    }

    public function sync_status($recordId)
    {
        $this->guardAccess();
        $this->requirePost();
        $tenant = $this->tenant();
        $record = $this->Kt_matbao_invoice_model->get_record((int) $recordId);
        if (!$record || (int) ($record['tenant_id'] ?? 0) !== (int) $tenant['id']) {
            show_404();
        }

        $resolved = $this->Kt_matbao_invoice_model->resolve_tenant_effective_settings($tenant);
        if ($resolved['scope'] === 'none' || empty($resolved['settings'])) {
            set_alert('warning', 'Chưa có cấu hình KT Mắt Bão đang hoạt động.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $detailPayload = [];
        $khmshdon = trim((string) ($record['khmshdon'] ?? ''));
        $khhdon = trim((string) ($record['khhdon'] ?? ''));
        if ($khmshdon !== '') {
            $detailPayload['KHMSHDon'] = $khmshdon;
        }
        if ($khhdon !== '') {
            $detailPayload['KHHDon'] = $khhdon;
        }
        $maSo = trim((string) ($record['ma_so_hdon'] ?? ''));
        $maTra = trim((string) ($record['ma_tra_cuu'] ?? ''));
        if ($maSo === '' && $maTra === '') {
            set_alert('warning', 'Record has no MaSoHDon/MaTraCuu to sync.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }
        if ($maSo !== '') {
            $detailPayload['MaSoHDon'] = $maSo;
        }
        if ($maTra !== '') {
            $detailPayload['MaTraCuu'] = $maTra;
        }
        $result = $this->matbao_invoice_client->getInvoiceDetail($resolved['settings'], $detailPayload, (int) ($tenant['id'] ?? 0));
        if (empty($result['success']) && $maTra !== '') {
            $fallbackPayload = [];
            if ($khmshdon !== '') {
                $fallbackPayload['KHMSHDon'] = $khmshdon;
            }
            if ($khhdon !== '') {
                $fallbackPayload['KHHDon'] = $khhdon;
            }
            $fallbackPayload['MaTraCuu'] = $maTra;
            $result = $this->matbao_invoice_client->getInvoiceDetail($resolved['settings'], $fallbackPayload, (int) ($tenant['id'] ?? 0));
        }
        if (empty($result['success'])) {
            $providerMsg = '';
            if (!empty($result['response']) && is_array($result['response'])) {
                $providerMsg = (string) ($result['response']['message'] ?? ($result['response']['Message'] ?? ''));
            }
            set_alert('warning', 'Đồng bộ trạng thái thất bại.' . ($providerMsg !== '' ? (' ' . $providerMsg) : ''));
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $response = is_array($result['response']) ? $result['response'] : [];
        $data = $this->pick_matbao_payload($response);
        $remoteInvId = (string) $this->pick_val($data, ['InvID', 'invID', 'invId', 'inv_id'], '');
        $remoteMaSo = $this->extract_maso_hdon_from_response($response, $data);
        $remoteMaTra = (string) $this->pick_val($data, ['MaTraCuu', 'maTraCuu', 'ma_tra_cuu'], '');
        $localMaSo = $this->normalize_maso_hdon($record['ma_so_hdon'] ?? '');
        $env = strtolower(trim((string) ($resolved['settings']['environment'] ?? '')));
        $isDemoEnv = $env !== 'production';
        if (!$this->should_accept_remote_identity($record, $remoteInvId, $remoteMaSo, $remoteMaTra)) {
            log_message(
                'error',
                'KT MatBao sync identity mismatch. record_id=' . (int) ($record['id'] ?? 0)
                . ' local_inv_id=' . (string) ($record['inv_id'] ?? '')
                . ' local_maso=' . (string) ($record['ma_so_hdon'] ?? '')
                . ' local_matra=' . (string) ($record['ma_tra_cuu'] ?? '')
                . ' remote_inv_id=' . $remoteInvId
                . ' remote_maso=' . $remoteMaSo
                . ' remote_matra=' . $remoteMaTra
            );
            if (!$isDemoEnv) {
                set_alert('warning', 'Đồng bộ trạng thái thất bại. Dữ liệu định danh hóa đơn không khớp với bản ghi hiện tại.');
                redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
            }
            $remoteInvId = '';
            $remoteMaSo = '';
            $remoteMaTra = '';
        }
        $this->Kt_matbao_invoice_model->save_record([
            'id' => (int) $record['id'],
            'local_status' => !empty($data['MaTTHDon']) ? 'issued' : (string) ($record['local_status'] ?? 'created'),
            'tax_status_code' => (string) ($data['MaTTHDon'] ?? ($record['tax_status_code'] ?? '')),
            'tax_status_name' => (string) ($data['TenTTHDon'] ?? ($record['tax_status_name'] ?? '')),
            'mccqt' => (string) ($data['MCCQT'] ?? ($record['mccqt'] ?? '')),
            'inv_id' => $remoteInvId !== '' ? $remoteInvId : (string) ($record['inv_id'] ?? ''),
            'ma_so_hdon' => $remoteMaSo !== '' ? $remoteMaSo : $localMaSo,
            'ma_tra_cuu' => $remoteMaTra !== '' ? $remoteMaTra : (string) ($record['ma_tra_cuu'] ?? ''),
            'pdf_url' => (string) $this->pick_val($data, ['UrlDownloadPDF', 'urlDownloadPDF', 'pdfUrl', 'pdf_url'], (string) ($record['pdf_url'] ?? '')),
            'xml_url' => (string) $this->pick_val($data, ['UrlDownloadXML', 'urlDownloadXML', 'xmlUrl', 'xml_url'], (string) ($record['xml_url'] ?? '')),
            'raw_response_snapshot' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        set_alert('success', 'Synced invoice status.');
        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
    }

    public function download($recordId, $type = 'pdf')
    {
        $this->guardAccess();
        $tenant = $this->tenant();
        $feature = strtolower((string) $type) === 'xml' ? 'matbao_invoice.download_xml' : 'matbao_invoice.download_pdf';
        $this->requireTenantFeature((int) ($tenant['id'] ?? 0), $feature, true);
        $record = $this->Kt_matbao_invoice_model->get_record((int) $recordId);
        if (!$record || (int) ($record['tenant_id'] ?? 0) !== (int) $tenant['id']) {
            set_alert('warning', 'Không tìm thấy bản ghi hóa đơn điện tử của tenant.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $resolved = $this->Kt_matbao_invoice_model->resolve_tenant_effective_settings($tenant);
        if ($resolved['scope'] === 'none' || empty($resolved['settings'])) {
            set_alert('warning', 'Doanh nghiệp chưa có cấu hình Mắt Bão khả dụng để tải file.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $recordMaSo = $this->normalize_maso_hdon($record['ma_so_hdon'] ?? '');
        if (trim((string) ($record['ma_tra_cuu'] ?? '')) === '' && $recordMaSo === '') {
            set_alert('warning', 'Bản ghi chưa có mã tra cứu/Mã số hóa đơn để tải PDF/XML.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $ext = strtolower((string) $type) === 'xml' ? 'xml' : 'pdf';
        $directUrl = $ext === 'xml' ? (string) ($record['xml_url'] ?? '') : (string) ($record['pdf_url'] ?? '');
        if ($directUrl !== '' && $this->stream_remote_file($directUrl, $ext, (int) $record['id'])) {
            return;
        }

        $downloadPayload = [
            'InvID' => (string) ($record['inv_id'] ?? ''),
            'MaTraCuu' => (string) ($record['ma_tra_cuu'] ?? ''),
            'MaSoHDon' => $recordMaSo,
        ];
        if ($downloadPayload['InvID'] === '' && !empty($record['raw_response_snapshot'])) {
            $raw = json_decode((string) $record['raw_response_snapshot'], true);
            if (is_array($raw)) {
                $picked = $this->pick_matbao_payload($raw);
                $downloadPayload['InvID'] = (string) ($picked['InvID'] ?? $picked['invID'] ?? '');
                if ($downloadPayload['InvID'] === '') {
                    $downloadPayload['InvID'] = (string) ($picked['invId'] ?? $picked['inv_id'] ?? '');
                }
                $snapshotMaSo = $this->normalize_maso_hdon((string) ($picked['MaSoHDon'] ?? $picked['maSoHDon'] ?? ''));
                $downloadPayload['MaSoHDon'] = $downloadPayload['MaSoHDon'] !== '' ? $downloadPayload['MaSoHDon'] : $snapshotMaSo;
                $downloadPayload['MaTraCuu'] = $downloadPayload['MaTraCuu'] !== '' ? $downloadPayload['MaTraCuu'] : (string) ($picked['MaTraCuu'] ?? $picked['maTraCuu'] ?? '');
            }
        }

        if ($downloadPayload['InvID'] === '' && ($downloadPayload['MaTraCuu'] !== '' || $downloadPayload['MaSoHDon'] !== '')) {
            $detailPayload = [];
            if (!empty($record['khmshdon'])) {
                $detailPayload['KHMSHDon'] = (string) $record['khmshdon'];
            }
            if (!empty($record['khhdon'])) {
                $detailPayload['KHHDon'] = (string) $record['khhdon'];
            }
            if ($downloadPayload['MaSoHDon'] !== '') {
                $detailPayload['MaSoHDon'] = $downloadPayload['MaSoHDon'];
            } else {
                $detailPayload['MaTraCuu'] = $downloadPayload['MaTraCuu'];
            }
            $detailResult = $this->matbao_invoice_client->getInvoiceDetail($resolved['settings'], $detailPayload, (int) ($tenant['id'] ?? 0));
            if (!empty($detailResult['success']) && is_array($detailResult['response'])) {
                $detailData = $this->pick_matbao_payload($detailResult['response']);
                $resolvedInvId = (string) $this->pick_val($detailData, ['InvID', 'invID', 'invId', 'inv_id'], '');
                $resolvedMaSo = $this->extract_maso_hdon_from_response((array) $detailResult['response'], $detailData);
                $resolvedMaTra = (string) $this->pick_val($detailData, ['MaTraCuu', 'maTraCuu', 'ma_tra_cuu'], '');

                $env = strtolower(trim((string) ($resolved['settings']['environment'] ?? '')));
                $isDemoEnv = $env !== 'production';
                if (!$this->should_accept_remote_identity($record, $resolvedInvId, $resolvedMaSo, $resolvedMaTra)) {
                    if (!$isDemoEnv) {
                        set_alert('warning', 'Tải file thất bại: Dữ liệu hóa đơn từ nhà cung cấp không khớp bản ghi hiện tại.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                    $resolvedInvId = '';
                    $resolvedMaSo = '';
                    $resolvedMaTra = '';
                }

                if ($resolvedInvId !== '') {
                    $downloadPayload['InvID'] = $resolvedInvId;
                }
                if ($downloadPayload['MaSoHDon'] === '' && $resolvedMaSo !== '') {
                    $downloadPayload['MaSoHDon'] = $resolvedMaSo;
                }
                if ($downloadPayload['MaTraCuu'] === '' && $resolvedMaTra !== '') {
                    $downloadPayload['MaTraCuu'] = $resolvedMaTra;
                }
                $this->Kt_matbao_invoice_model->save_record([
                    'id' => (int) $record['id'],
                    'inv_id' => $resolvedInvId !== '' ? $resolvedInvId : (string) ($record['inv_id'] ?? ''),
                    'ma_so_hdon' => $downloadPayload['MaSoHDon'],
                    'ma_tra_cuu' => $downloadPayload['MaTraCuu'],
                    'raw_response_snapshot' => json_encode($detailResult['response'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ]);
            }
        }

        $result = $this->matbao_invoice_client->downloadInvoice($resolved['settings'], $downloadPayload, (int) ($tenant['id'] ?? 0));
        $errorMessage = (string) ($result['error'] ?? '');
        $needsInvIdRetry = (
            empty($result['success'])
            && $downloadPayload['InvID'] === ''
            && stripos($errorMessage, 'InvID') !== false
        );

        if ($needsInvIdRetry && $downloadPayload['MaTraCuu'] !== '') {
            $detailPayload = ['MaTraCuu' => (string) $downloadPayload['MaTraCuu']];
            if (!empty($record['khmshdon'])) {
                $detailPayload['KHMSHDon'] = (string) $record['khmshdon'];
            }
            if (!empty($record['khhdon'])) {
                $detailPayload['KHHDon'] = (string) $record['khhdon'];
            }
            $detailResult = $this->matbao_invoice_client->getInvoiceDetail($resolved['settings'], $detailPayload, (int) ($tenant['id'] ?? 0));
            if (!empty($detailResult['success']) && is_array($detailResult['response'])) {
                $detailData = $this->pick_matbao_payload($detailResult['response']);
                $resolvedInvId = (string) $this->pick_val($detailData, ['InvID', 'invID', 'invId', 'inv_id'], '');
                if ($resolvedInvId !== '') {
                    $downloadPayload['InvID'] = $resolvedInvId;
                    $result = $this->matbao_invoice_client->downloadInvoice($resolved['settings'], $downloadPayload, (int) ($tenant['id'] ?? 0));
                }
            }
        }

        if (empty($result['success']) || !is_array($result['response'])) {
            $errorMessage = (string) ($result['error'] ?? 'Provider không trả về dữ liệu file.');
            set_alert('warning', 'Tải file thất bại: ' . $errorMessage);
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $resp = $result['response'];
        $key = $ext === 'xml' ? 'xml' : 'pdf';
        $contentBase64 = '';
        $payloadNode = $resp['Data'] ?? ($resp['data'] ?? $resp);
        if (is_array($payloadNode)) {
            if (array_key_exists($key, $payloadNode)) {
                $contentBase64 = (string) $payloadNode[$key];
            } elseif ($key === 'pdf') {
                $contentBase64 = (string) ($payloadNode['data_PDF_Base64'] ?? $payloadNode['Data_PDF_Base64'] ?? '');
            } else {
                $contentBase64 = (string) ($payloadNode['data_XML_Base64'] ?? $payloadNode['Data_XML_Base64'] ?? '');
            }
        }

        if ($contentBase64 === '') {
            set_alert('warning', 'Hóa đơn chưa có file ' . strtoupper($ext) . ' khả dụng tại thời điểm này.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $bin = base64_decode($contentBase64, true);
        if ($bin === false) {
            set_alert('warning', 'Dữ liệu file trả về không hợp lệ, vui lòng đồng bộ trạng thái trước.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }

        $filename = 'matbao_' . ((int) $record['id']) . '.' . $ext;
        header('Content-Type: ' . ($ext === 'xml' ? 'application/xml' : 'application/pdf'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $bin;
    }

    private function createOrIssue($invoiceId, $loaiHDon)
    {
        try {
            $this->guardAccess();
            $this->requirePost();

            $tenant = $this->tenant();
            $tenantId = (int) ($tenant['id'] ?? 0);

            if ($tenantId < 1) {
                throw new RuntimeException('Tenant context is missing.');
            }

            if ((int) $loaiHDon === 1) {
                $dailyLimit = $this->Kt_matbao_invoice_model->get_feature_limit($tenantId, 'matbao_invoice.daily_quota', 0);
                $monthlyLimit = $this->Kt_matbao_invoice_model->get_feature_limit($tenantId, 'matbao_invoice.monthly_quota', 0);
                if ($dailyLimit > 0 && $this->Kt_matbao_invoice_model->get_issued_count_today($tenantId) >= $dailyLimit) {
                    $this->dispatchPhase3DEmail('invoice_issue_failed', [
                        'tenant_id' => $tenantId,
                        'tenant' => $tenant,
                        'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                        'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                        'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                        'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                        'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                        'invoice_id' => (int) $invoiceId,
                        'invoice_status' => 'quota_blocked',
                        'error_message' => 'Đã vượt hạn mức hóa đơn điện tử theo ngày.',
                        'related_type' => 'invoice',
                        'related_id' => (string) $invoiceId,
                        'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|daily_quota',
                    ], [
                        'event_key' => 'invoice_issue_failed',
                        'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|daily_quota',
                    ]);
                    set_alert('warning', 'Đã vượt hạn mức hóa đơn điện tử theo ngày.');
                    redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                }
                if ($monthlyLimit > 0 && $this->Kt_matbao_invoice_model->get_issued_count_month($tenantId) >= $monthlyLimit) {
                    $this->dispatchPhase3DEmail('invoice_issue_failed', [
                        'tenant_id' => $tenantId,
                        'tenant' => $tenant,
                        'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                        'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                        'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                        'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                        'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                        'invoice_id' => (int) $invoiceId,
                        'invoice_status' => 'quota_blocked',
                        'error_message' => 'Đã vượt hạn mức hóa đơn điện tử theo tháng.',
                        'related_type' => 'invoice',
                        'related_id' => (string) $invoiceId,
                        'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|monthly_quota',
                    ], [
                        'event_key' => 'invoice_issue_failed',
                        'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|monthly_quota',
                    ]);
                    set_alert('warning', 'Đã vượt hạn mức hóa đơn điện tử theo tháng.');
                    redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                }

                if ($this->Kt_matbao_invoice_model->has_active_einvoice_addon($tenantId)) {
                    $remaining = $this->Kt_matbao_invoice_model->total_einvoice_remaining_quota($tenantId);
                    if ($remaining < 1) {
                        $this->dispatchPhase3DEmail('invoice_issue_failed', [
                            'tenant_id' => $tenantId,
                            'tenant' => $tenant,
                            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                            'invoice_id' => (int) $invoiceId,
                            'invoice_status' => 'quota_blocked',
                            'error_message' => 'Đã dùng hết hạn mức gói bổ sung hóa đơn điện tử.',
                            'related_type' => 'invoice',
                            'related_id' => (string) $invoiceId,
                            'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|addon_quota',
                        ], [
                            'event_key' => 'invoice_issue_failed',
                            'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|addon_quota',
                        ]);
                        set_alert('warning', 'Đã dùng hết hạn mức gói bổ sung hóa đơn điện tử.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                }
            }

            $resolved = $this->Kt_matbao_invoice_model->resolve_tenant_effective_settings($tenant);
            if ($resolved['scope'] === 'none' || empty($resolved['settings'])) {
                $this->dispatchPhase3DEmail('invoice_issue_failed', [
                    'tenant_id' => $tenantId,
                    'tenant' => $tenant,
                    'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                    'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                    'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                    'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                    'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                    'invoice_id' => (int) $invoiceId,
                    'invoice_status' => 'settings_missing',
                    'error_message' => 'Chưa có cấu hình KT Mắt Bão đang hoạt động cho tenant.',
                    'related_type' => 'invoice',
                    'related_id' => (string) $invoiceId,
                    'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|settings_missing',
                ], [
                    'event_key' => 'invoice_issue_failed',
                    'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|settings_missing',
                ]);
                set_alert('warning', 'Chưa có cấu hình KT Mắt Bão đang hoạt động cho tenant.');
                redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
            }

            $signingMode = 'hddt_sign_invoice';
            $resolvedCa = ['scope' => 'none', 'settings' => null];
            if ((int) $loaiHDon === 1) {
                $resolvedCa = $this->Kt_matbao_invoice_model->resolve_tenant_effective_ca_account($tenant);
                if (!empty($resolvedCa['settings']['signing_mode'])) {
                    $signingMode = (string) $resolvedCa['settings']['signing_mode'];
                }
                if ($signingMode === 'get_xml_then_ca_sign') {
                    if (!$this->Kt_matbao_invoice_model->tenant_feature_allowed($tenantId, 'matbao_ca.enabled', false)) {
                        set_alert('warning', 'Gói hiện tại không cho phép ký bằng MatBaoCA.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                    if (!$this->Kt_matbao_invoice_model->tenant_feature_allowed($tenantId, 'matbao_ca.sign_xml', false)) {
                        set_alert('warning', 'Gói hiện tại không cho phép ký XML qua MatBaoCA.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                    if (empty($resolvedCa['settings']) || empty($resolvedCa['settings']['is_active'])) {
                        set_alert('warning', 'Tài khoản CA/HSM chưa hoạt động cho chế độ ký lấy XML rồi ký CA/HSM.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                    $hsmStatus = (string) ($resolvedCa['settings']['hsm_status'] ?? 'active');
                    if (!in_array($hsmStatus, ['active', 'pending', 'not_registered', ''], true) && !empty($hsmStatus)) {
                        set_alert('warning', 'HSM status is not eligible for signing: ' . $hsmStatus);
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                }
            }

            $bundle = $this->Kt_matbao_invoice_model->get_perfex_invoice_bundle((int) $invoiceId);
            if (!$bundle) {
                set_alert('warning', 'Source invoice not found.');
                redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
            }

            $invoice = $bundle['invoice'];
            $client = is_array($bundle['client']) ? $bundle['client'] : [];
            $items = is_array($bundle['items']) ? $bundle['items'] : [];

            $existing = $this->Kt_matbao_invoice_model->get_record_by_source('perfex_invoice', (int) $invoiceId, $tenantId);
            if ($existing && in_array((string) ($existing['local_status'] ?? ''), ['issued', 'signed'], true) && (int) $loaiHDon === 1) {
                set_alert('warning', 'Invoice already issued.');
                redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
            }

            $lineItems = [];
            $totalBeforeTax = 0.0;
            $totalTax = 0.0;
            $totalAmount = 0.0;

            foreach ($items as $i => $it) {
                $qty = (float) ($it['qty'] ?? 0);
                $rate = (float) ($it['rate'] ?? 0);
                $lineTotal = (float) ($it['amount'] ?? ($qty * $rate));
                $taxRate = (float) ($it['taxrate'] ?? 0);
                $lineTax = round($lineTotal * $taxRate / 100, 2);
                $totalBeforeTax += $lineTotal;
                $totalTax += $lineTax;
                $totalAmount += $lineTotal + $lineTax;

                $lineItems[] = [
                    'item_source_id' => (int) ($it['id'] ?? 0),
                    'tchat' => '1',
                    'stt' => (string) ($i + 1),
                    'mhhdvu' => '',
                    'thhdvu' => (string) ($it['description'] ?? ''),
                    'dvtinh' => (string) ($it['unit'] ?? ''),
                    'sluong' => $qty,
                    'dgia' => $rate,
                    'thtien_chua_ck' => $lineTotal,
                    'tlckhau' => 0,
                    'stckhau' => 0,
                    'thtien' => $lineTotal,
                    'tsuat' => $taxRate,
                    'tthue' => $lineTax,
                    'tgtien' => $lineTotal + $lineTax,
                ];
            }

            $invoiceSubtotal = round((float) ($invoice['subtotal'] ?? 0), 2);
            $invoiceTax = round((float) ($invoice['total_tax'] ?? 0), 2);
            $invoiceTotal = round((float) ($invoice['total'] ?? 0), 2);

            if ($invoiceTax > 0 && $totalTax <= 0) {
                set_alert('warning', 'Không thể map thuế từ hóa đơn nguồn. Vui lòng kiểm tra cấu hình thuế của dòng hàng trước khi xuất.');
                redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
            }

            // Use source invoice totals as canonical values to avoid payload/record mismatch.
            if ($invoiceSubtotal > 0) {
                $totalBeforeTax = $invoiceSubtotal;
            }
            if ($invoiceTax >= 0) {
                $totalTax = $invoiceTax;
            }
            if ($invoiceTotal > 0) {
                $totalAmount = $invoiceTotal;
            } else {
                $totalAmount = $totalBeforeTax + $totalTax;
            }

            $defaultYear = (int) ($resolved['settings']['default_year'] ?? date('Y'));
            $khmshdon = trim((string) ($resolved['settings']['default_khmshdon'] ?? ''));
            $khhdon = trim((string) ($resolved['settings']['default_khhdon'] ?? ''));

            if ($khmshdon === '' || $khhdon === '') {
                $templateTenantId = $resolved['scope'] === 'tenant' ? $tenantId : null;
                $templates = $this->Kt_matbao_invoice_model->get_templates($templateTenantId, (string) $resolved['scope']);

                // Auto-sync templates when local cache is empty/missing defaults.
                if (empty($templates)) {
                    $syncResult = $this->matbao_invoice_client->getTemplates($resolved['settings'], $defaultYear, $tenantId);
                    if (!empty($syncResult['success']) && is_array($syncResult['response'])) {
                        $syncData = $syncResult['response']['Data'] ?? $syncResult['response']['data'] ?? $syncResult['response'];
                        if (is_array($syncData)) {
                            $rows = [];
                            foreach ($syncData as $row) {
                                if (is_array($row)) {
                                    $rows[] = $row;
                                }
                            }
                            if (!empty($rows)) {
                                $this->Kt_matbao_invoice_model->replace_templates($templateTenantId, (string) $resolved['scope'], $defaultYear, $rows);
                                $templates = $this->Kt_matbao_invoice_model->get_templates($templateTenantId, (string) $resolved['scope']);
                            }
                        }
                    }
                }

                if (!empty($templates)) {
                    $matched = null;
                    foreach ($templates as $tpl) {
                        $sameYear = (int) ($tpl['year'] ?? 0) === $defaultYear;
                        $rawTpl = !empty($tpl['raw_json']) ? json_decode((string) $tpl['raw_json'], true) : [];
                        $clai = isset($tpl['clai']) ? (int) $tpl['clai'] : (isset($rawTpl['cLai']) ? (int) $rawTpl['cLai'] : null);
                        $hasRemain = $clai !== null ? ($clai > 0) : true;
                        if ($sameYear && $hasRemain) {
                            $matched = $tpl;
                            break;
                        }
                    }
                    if ($matched === null) {
                        foreach ($templates as $tpl) {
                            $rawTpl = !empty($tpl['raw_json']) ? json_decode((string) $tpl['raw_json'], true) : [];
                            $clai = isset($tpl['clai']) ? (int) $tpl['clai'] : (isset($rawTpl['cLai']) ? (int) $rawTpl['cLai'] : null);
                            $hasRemain = $clai !== null ? ($clai > 0) : true;
                            if ($hasRemain) {
                                $matched = $tpl;
                                break;
                            }
                        }
                    }
                    if ($matched === null) {
                        $matched = $templates[0];
                    }
                    $matchedKhmsh = trim((string) ($matched['khmshdon'] ?? ''));
                    $matchedKhhd = trim((string) ($matched['khhdon'] ?? ''));
                    if (($matchedKhmsh === '' || $matchedKhhd === '') && !empty($matched['raw_json'])) {
                        $rawTpl = json_decode((string) $matched['raw_json'], true);
                        if (is_array($rawTpl)) {
                            if ($matchedKhmsh === '') {
                                $matchedKhmsh = trim((string) ($rawTpl['KHMSHDon'] ?? $rawTpl['khmshDon'] ?? $rawTpl['khmshdon'] ?? ''));
                            }
                            if ($matchedKhhd === '') {
                                $matchedKhhd = trim((string) ($rawTpl['KHHDon'] ?? $rawTpl['khhDon'] ?? $rawTpl['khhdon'] ?? ''));
                            }
                        }
                    }
                    $khmshdon = $khmshdon !== '' ? $khmshdon : $matchedKhmsh;
                    $khhdon = $khhdon !== '' ? $khhdon : $matchedKhhd;
                }
            }

            if ($khmshdon === '' || $khhdon === '') {
                $this->dispatchPhase3DEmail('invoice_issue_failed', [
                    'tenant_id' => $tenantId,
                    'tenant' => $tenant,
                    'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                    'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                    'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                    'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                    'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                    'invoice_id' => (int) $invoiceId,
                    'invoice_status' => 'template_missing',
                    'error_message' => 'Missing template defaults.',
                    'related_type' => 'invoice',
                    'related_id' => (string) $invoiceId,
                    'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|template_missing',
                ], [
                    'event_key' => 'invoice_issue_failed',
                    'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|template_missing',
                ]);
                set_alert('warning', 'Thiếu mẫu số/ký hiệu hóa đơn. Vui lòng Sync templates và cấu hình Default KHMSHDon/KHHDon.');
                redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
            }

            $maTraCuu = 'MB' . $tenantId . 'I' . (int) $invoiceId . 'T' . time();
            $resolvedCaDocumentId = '';
            $createLoaiHDon = ((int) $loaiHDon === 1 && $signingMode === 'get_xml_then_ca_sign') ? 0 : (int) $loaiHDon;
            $payload = [[
            'KHMSHDon' => $khmshdon,
            'KHHDon' => $khhdon,
            'MaTraCuu' => $maTraCuu,
            'MTChieu' => 'PERFEX-' . (int) $invoiceId,
            'NLap' => date('Y-m-d'),
            'DVTTe' => '704',
            'TGia' => 1,
            'HTTToan' => 'Chuyen khoan',
            'GChu' => 'Perfex Invoice #' . (int) $invoiceId,
            'TCHDon' => 0,
            'LoaiHDon' => $createLoaiHDon,
            'NMua_Ten' => (string) ($client['company'] ?? $client['companyname'] ?? 'Customer'),
            'NMua_MST' => (string) ($client['vat'] ?? ''),
            'NMua_DChi' => trim((string) (($client['address'] ?? '') . ' ' . ($client['city'] ?? ''))),
            'NMua_SDThoai' => (string) ($client['phonenumber'] ?? ''),
            'NMua_DCTDTu' => (string) ($client['email'] ?? ''),
            'DSHHDVu' => $lineItems,
            'TgThTien' => round($totalBeforeTax, 2),
            'TgTThue' => round($totalTax, 2),
            'TTCKTMai' => 0,
            'TGTKhac' => 0,
            'TgTTTBSo' => round($totalAmount, 2),
            'TgTTTBChu' => '',
        ]];

            $result = $this->matbao_invoice_client->createInvoice($resolved['settings'], $payload, $tenantId);
            if (empty($result['success'])) {
                $providerMsg = '';
                if (!empty($result['response']) && is_array($result['response'])) {
                    $providerMsg = (string) ($result['response']['message'] ?? ($result['response']['Message'] ?? ''));
                    if (isset($result['response']['data'][0]['message']) && trim((string) $result['response']['data'][0]['message']) !== '') {
                        $providerMsg = trim((string) $result['response']['data'][0]['message']);
                    } elseif (isset($result['response']['Data'][0]['message']) && trim((string) $result['response']['Data'][0]['message']) !== '') {
                        $providerMsg = trim((string) $result['response']['Data'][0]['message']);
                    }
                }
                $this->dispatchPhase3DEmail('invoice_issue_failed', [
                    'tenant_id' => $tenantId,
                    'tenant' => $tenant,
                    'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                    'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                    'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                    'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                    'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                    'invoice_id' => (int) $invoiceId,
                    'invoice_number' => (string) ($invoice['number'] ?? $invoice['invoice_number'] ?? ''),
                    'invoice_status' => 'create_failed',
                    'error_message' => $providerMsg !== '' ? $providerMsg : 'MatBao create invoice failed.',
                    'related_type' => 'invoice',
                    'related_id' => (string) $invoiceId,
                    'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|create_failed',
                ], [
                    'event_key' => 'invoice_issue_failed',
                    'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|create_failed',
                ]);
                set_alert('warning', 'MatBao create invoice failed.' . ($providerMsg !== '' ? (' ' . $providerMsg) : ''));
                redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
            }

            $response = is_array($result['response']) ? $result['response'] : [];
            $data = $this->pick_matbao_payload($response);
            $maSoHDon = $this->extract_maso_hdon_from_response($response, $data);
            $maTraCuuResolved = (string) $this->pick_val($data, ['MaTraCuu', 'maTraCuu', 'ma_tra_cuu'], $maTraCuu);
            $invId = (string) $this->pick_val($data, ['InvID', 'invID', 'invId', 'inv_id'], '');
            $pdfUrl = (string) $this->pick_val($data, ['UrlDownloadPDF', 'urlDownloadPDF', 'pdfUrl', 'pdf_url'], '');
            $xmlUrl = (string) $this->pick_val($data, ['UrlDownloadXML', 'urlDownloadXML', 'xmlUrl', 'xml_url'], '');

            if ((int) $loaiHDon === 1) {
                if ($signingMode === 'hddt_sign_invoice') {
                    $signPayload = [
                        'MaSoHDon' => $maSoHDon,
                        'MaTraCuu' => $maTraCuuResolved,
                    ];
                    $signResult = $this->matbao_invoice_client->signInvoice($resolved['settings'], $signPayload, $tenantId);
                    if (empty($signResult['success'])) {
                        $this->dispatchPhase3DEmail('invoice_sign_failed', [
                            'tenant_id' => $tenantId,
                            'tenant' => $tenant,
                            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                            'invoice_id' => (int) $invoiceId,
                            'invoice_number' => $maSoHDon,
                            'invoice_status' => 'sign_failed',
                            'pdf_url' => $pdfUrl,
                            'xml_url' => $xmlUrl,
                            'error_message' => 'Invoice created but HDDT sign-invoice failed.',
                            'related_type' => 'invoice',
                            'related_id' => (string) $invoiceId,
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|hddt_sign',
                        ], [
                            'event_key' => 'invoice_sign_failed',
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|hddt_sign',
                        ]);
                        set_alert('warning', 'Invoice created but HDDT sign-invoice failed.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                } elseif ($signingMode === 'get_xml_then_ca_sign') {
                    $xmlResult = $this->matbao_invoice_client->getXmlNotSign($resolved['settings'], $maSoHDon, $tenantId);
                    if (empty($xmlResult['success']) || !is_array($xmlResult['response'])) {
                        $this->dispatchPhase3DEmail('invoice_sign_failed', [
                            'tenant_id' => $tenantId,
                            'tenant' => $tenant,
                            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                            'invoice_id' => (int) $invoiceId,
                            'invoice_number' => $maSoHDon,
                            'invoice_status' => 'sign_failed',
                            'pdf_url' => $pdfUrl,
                            'xml_url' => $xmlUrl,
                            'error_message' => 'Get XML not signed failed.',
                            'related_type' => 'invoice',
                            'related_id' => (string) $invoiceId,
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|get_xml',
                        ], [
                            'event_key' => 'invoice_sign_failed',
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|get_xml',
                        ]);
                        set_alert('warning', 'Get XML not signed failed.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                    $xmlNode = $this->pick_matbao_payload($xmlResult['response']);
                    $xmlBase64 = (string) $this->pick_val($xmlNode, ['XmlDataBase64', 'xmlDataBase64', 'xml', 'XML', 'DataXML', 'dataXML'], '');
                    if ($xmlBase64 === '') {
                        $this->dispatchPhase3DEmail('invoice_sign_failed', [
                            'tenant_id' => $tenantId,
                            'tenant' => $tenant,
                            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                            'invoice_id' => (int) $invoiceId,
                            'invoice_number' => $maSoHDon,
                            'invoice_status' => 'sign_failed',
                            'pdf_url' => $pdfUrl,
                            'xml_url' => $xmlUrl,
                            'error_message' => 'Provider did not return unsigned XML.',
                            'related_type' => 'invoice',
                            'related_id' => (string) $invoiceId,
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|unsigned_xml',
                        ], [
                            'event_key' => 'invoice_sign_failed',
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|unsigned_xml',
                        ]);
                        set_alert('warning', 'Provider did not return unsigned XML.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                    $caPayload = [
                        'NodeToBeSigned' => '//DSCKS/NBan',
                        'SignatureLocation' => 'SigningData',
                        'XmlDataBase64' => $xmlBase64,
                    ];
                    $caSignResult = $this->matbao_sign_client->signatureXml($resolvedCa['settings'], $caPayload, $tenantId);
                    if (empty($caSignResult['success']) || !is_array($caSignResult['response'])) {
                        $this->dispatchPhase3DEmail('invoice_sign_failed', [
                            'tenant_id' => $tenantId,
                            'tenant' => $tenant,
                            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                            'invoice_id' => (int) $invoiceId,
                            'invoice_number' => $maSoHDon,
                            'invoice_status' => 'sign_failed',
                            'pdf_url' => $pdfUrl,
                            'xml_url' => $xmlUrl,
                            'error_message' => 'CA signature XML failed.',
                            'related_type' => 'invoice',
                            'related_id' => (string) $invoiceId,
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|ca_sign',
                        ], [
                            'event_key' => 'invoice_sign_failed',
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|ca_sign',
                        ]);
                        set_alert('warning', 'CA signature XML failed.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }
                    $caNode = $caSignResult['response']['Data'] ?? ($caSignResult['response']['data'] ?? $caSignResult['response']);
                    $signedXmlBase64 = '';
                    $caDocumentId = '';
                    if (is_array($caNode)) {
                        $signedXmlBase64 = (string) ($caNode['XmlDataBase64'] ?? ($caNode['xmlDataBase64'] ?? ($caNode['SignedXmlDataBase64'] ?? '')));
                        $caDocumentId = (string) ($caNode['DocumentId'] ?? ($caNode['documentId'] ?? ($caNode['document_id'] ?? '')));
                    } elseif (is_string($caNode)) {
                        $signedXmlBase64 = $caNode;
                    }
                    if ($signedXmlBase64 === '') {
                        $this->dispatchPhase3DEmail('invoice_sign_failed', [
                            'tenant_id' => $tenantId,
                            'tenant' => $tenant,
                            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                            'invoice_id' => (int) $invoiceId,
                            'invoice_number' => $maSoHDon,
                            'invoice_status' => 'sign_failed',
                            'pdf_url' => $pdfUrl,
                            'xml_url' => $xmlUrl,
                            'error_message' => 'CA did not return signed XML.',
                            'related_type' => 'invoice',
                            'related_id' => (string) $invoiceId,
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|signed_xml',
                        ], [
                            'event_key' => 'invoice_sign_failed',
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|signed_xml',
                        ]);
                        set_alert('warning', 'CA did not return signed XML.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }

                    $pushSignedResult = $this->matbao_invoice_client->signXml($resolved['settings'], [
                        'MaSoHDon' => $maSoHDon,
                        'MaTraCuu' => $maTraCuuResolved,
                        'XmlDataBase64' => $signedXmlBase64,
                    ], $tenantId);
                    if (empty($pushSignedResult['success'])) {
                        $this->dispatchPhase3DEmail('invoice_sign_failed', [
                            'tenant_id' => $tenantId,
                            'tenant' => $tenant,
                            'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                            'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                            'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                            'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                            'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                            'invoice_id' => (int) $invoiceId,
                            'invoice_number' => $maSoHDon,
                            'invoice_status' => 'sign_failed',
                            'pdf_url' => $pdfUrl,
                            'xml_url' => $xmlUrl,
                            'error_message' => 'Push signed XML to HDDT failed.',
                            'related_type' => 'invoice',
                            'related_id' => (string) $invoiceId,
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|push_signed',
                        ], [
                            'event_key' => 'invoice_sign_failed',
                            'dedupe_key' => 'invoice_sign_failed|' . $tenantId . '|' . $invoiceId . '|push_signed',
                        ]);
                        set_alert('warning', 'Push signed XML to HDDT failed.');
                        redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                    }

                    $resolvedCaDocumentId = $caDocumentId;
                }
            }

            $this->Kt_matbao_invoice_model->save_record([
            'id' => $existing['id'] ?? null,
            'tenant_id' => $tenantId,
            'source_type' => 'perfex_invoice',
            'source_id' => (int) $invoiceId,
            'perfex_invoice_id' => (int) $invoiceId,
            'customer_id' => (int) ($invoice['clientid'] ?? 0),
            'seller_scope' => 'tenant',
            'credential_scope' => (string) $resolved['scope'],
            'khmshdon' => $khmshdon,
            'khhdon' => $khhdon,
            'ma_tra_cuu' => $maTraCuuResolved,
            'mt_chieu' => 'PERFEX-' . (int) $invoiceId,
            'ma_so_hdon' => $maSoHDon,
            'inv_id' => $invId,
            'ca_document_id' => $resolvedCaDocumentId,
            'signing_provider_status' => $resolvedCaDocumentId !== '' ? 'SIGNED' : null,
            'local_status' => (int) $loaiHDon === 1 ? (($signingMode === 'get_xml_then_ca_sign') ? 'signed' : 'issued') : 'created',
            'issue_mode' => (int) $loaiHDon === 1 ? 'create_and_issue' : 'draft_only',
            'nlap' => date('Y-m-d H:i:s'),
            'total_before_tax' => round($totalBeforeTax, 2),
            'total_tax' => round($totalTax, 2),
            'total_amount' => round($totalAmount, 2),
            'pdf_url' => $pdfUrl,
            'xml_url' => $xmlUrl,
            'raw_request_snapshot' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'raw_response_snapshot' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_by' => is_staff_logged_in() ? get_staff_user_id() : null,
            'issued_at' => (int) $loaiHDon === 1 ? date('Y-m-d H:i:s') : null,
            ], $lineItems);

            if ((int) $loaiHDon === 1 && $this->Kt_matbao_invoice_model->has_active_einvoice_addon($tenantId)) {
                $saved = $this->Kt_matbao_invoice_model->get_record_by_source('perfex_invoice', (int) $invoiceId, $tenantId);
                $consume = $this->Kt_matbao_invoice_model->consume_einvoice_quota_fifo($tenantId, 1, 'matbao_invoice_record', (int) ($saved['id'] ?? 0));
                if (empty($consume['success'])) {
                    $this->Kt_matbao_invoice_model->save_record([
                        'id' => (int) ($saved['id'] ?? 0),
                        'error_message' => (string) ($consume['message'] ?? 'Quota consume failed'),
                    ]);
                    $this->dispatchPhase3DEmail('invoice_issue_failed', [
                        'tenant_id' => $tenantId,
                        'tenant' => $tenant,
                        'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                        'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                        'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                        'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                        'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                        'invoice_id' => (int) $invoiceId,
                        'invoice_number' => $maSoHDon,
                        'invoice_status' => 'quota_consume_failed',
                        'error_message' => (string) ($consume['message'] ?? 'Quota consume failed'),
                        'related_type' => 'invoice',
                        'related_id' => (string) $invoiceId,
                        'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|quota_consume_failed',
                    ], [
                        'event_key' => 'invoice_issue_failed',
                        'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|quota_consume_failed',
                    ]);
                    set_alert('warning', 'Invoice issued but quota consume failed. Check add-on balance.');
                    redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
                }
            }

            set_alert('success', (int) $loaiHDon === 1 ? 'Issued MatBao invoice.' : 'Created MatBao draft.');
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        } catch (Throwable $e) {
            log_message('error', 'KT MatBao tenant createOrIssue failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            $tenant = $this->tenant();
            $tenantId = (int) ($tenant['id'] ?? 0);
            $this->dispatchPhase3DEmail('invoice_issue_failed', [
                'tenant_id' => $tenantId,
                'tenant' => $tenant,
                'recipient_email' => (string) ($tenant['owner_email'] ?? $tenant['admin_email'] ?? $tenant['email'] ?? ''),
                'owner_email' => (string) ($tenant['owner_email'] ?? $tenant['email'] ?? ''),
                'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['contact_name'] ?? $tenant['company_name'] ?? $tenant['tenant_name'] ?? ''),
                'tenant_name' => (string) ($tenant['tenant_name'] ?? $tenant['company_name'] ?? $tenant['tenant_code'] ?? ''),
                'workspace_url' => (string) ($tenant['workspace_url'] ?? ''),
                'invoice_status' => 'exception',
                'error_message' => $e->getMessage(),
                'related_type' => 'invoice',
                'related_id' => (string) $invoiceId,
                'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|exception',
            ], [
                'event_key' => 'invoice_issue_failed',
                'dedupe_key' => 'invoice_issue_failed|' . $tenantId . '|' . $invoiceId . '|exception',
            ]);
            set_alert('warning', 'Create draft/issue failed: ' . $e->getMessage());
            redirect(admin_url('kt_matbao_invoice/tenant/invoices'));
        }
    }
}
