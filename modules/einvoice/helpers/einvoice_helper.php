<?php

function einvioce_module_get_templates(): array
{
    $ci = &get_instance();
    $ci->load->model('templates_model');

    return $ci->templates_model->getByType('einvoice');
}

function einvoice_module_settings_url(): string
{
    if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
        return admin_url('einvoice/tenant_settings');
    }

    return admin_url('settings?group=einvoice');
}

function einvoice_module_get_settings(): array
{
    return [
        'einvoice_default_invoice_template' => (string) get_option('einvoice_default_invoice_template'),
        'einvoice_send_as_invoice_email_attachment' => (string) get_option('einvoice_send_as_invoice_email_attachment'),
        'einvoice_default_credit_note_template' => (string) get_option('einvoice_default_credit_note_template'),
        'einvoice_send_as_credit_note_email_attachment' => (string) get_option('einvoice_send_as_credit_note_email_attachment'),
    ];
}

function einvoice_module_save_settings(array $data): array
{
    $ci = &get_instance();
    $ci->load->model('templates_model');

    $templates = $ci->templates_model->getByType('einvoice');
    $templateIds = array_map(static function ($template) {
        return (string) ($template['id'] ?? '');
    }, $templates);

    $invoiceTemplate = trim((string) ($data['einvoice_default_invoice_template'] ?? ''));
    $creditNoteTemplate = trim((string) ($data['einvoice_default_credit_note_template'] ?? ''));

    if ($invoiceTemplate !== '' && $invoiceTemplate !== '0' && !in_array($invoiceTemplate, $templateIds, true)) {
        return ['success' => false, 'message' => 'Default invoice template không hợp lệ.'];
    }

    if ($creditNoteTemplate !== '' && $creditNoteTemplate !== '0' && !in_array($creditNoteTemplate, $templateIds, true)) {
        return ['success' => false, 'message' => 'Default credit note template không hợp lệ.'];
    }

    update_option('einvoice_default_invoice_template', $invoiceTemplate);
    update_option('einvoice_send_as_invoice_email_attachment', isset($data['einvoice_send_as_invoice_email_attachment']) ? '1' : '0');
    update_option('einvoice_default_credit_note_template', $creditNoteTemplate);
    update_option('einvoice_send_as_credit_note_email_attachment', isset($data['einvoice_send_as_credit_note_email_attachment']) ? '1' : '0');

    return ['success' => true, 'message' => _l('settings_updated')];
}
