<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_saas_model extends App_Model
{
    protected $planFeatureValueColumnExists = null;

    public function get_tenant_workspace_form_options()
    {
        $languages = $this->resolveTenantLanguageOptions();
        $currentLanguage = trim((string) $this->tenant_option('active_language', ''));
        if ($currentLanguage !== '') {
            $languages[] = $currentLanguage;
        }

        $currentCurrency = strtoupper(trim((string) $this->tenant_option('default_currency', '')));
        $currencies = $this->workspace_currency_options();
        if ($currentCurrency !== '') {
            $hasCurrentCurrency = false;
            foreach ($currencies as $currency) {
                if (strtoupper((string) ($currency['code'] ?? '')) === $currentCurrency) {
                    $hasCurrentCurrency = true;
                    break;
                }
            }
            if (!$hasCurrentCurrency) {
                $currencies[] = [
                    'id'        => 0,
                    'code'      => $currentCurrency,
                    'symbol'    => '',
                    'isdefault' => false,
                ];
            }
        }

        return [
            'languages'           => array_values(array_unique(array_filter(array_map('strval', $languages)))),
            'timezones'           => $this->workspace_timezone_options(),
            'currencies'          => $currencies,
            'date_formats'        => $this->workspace_date_format_options(),
            'number_formats'      => $this->workspace_number_format_options(),
            'prefix_suggestions'  => [
                'invoice'     => 'INV-',
                'estimate'    => 'EST-',
                'credit_note' => 'CN-',
            ],
        ];
    }

    public function get_tenant_workspace_settings($tenantId)
    {
        $tenant = kt_saas_current_tenant();
        if ((int) ($tenant['id'] ?? 0) !== (int) $tenantId) {
            $tenant = $this->find_landlord_tenant_row($tenantId);
        }
        if (!$tenant) {
            return [];
        }

        $landlordLanguage = trim((string) get_option('active_language'));
        if ($landlordLanguage === '') {
            $landlordLanguage = 'english';
        }
        $landlordTimezone = trim((string) get_option('default_timezone'));
        if ($landlordTimezone === '') {
            $landlordTimezone = 'UTC';
        }
        $landlordCurrency = strtoupper(trim((string) get_option('default_currency')));
        if ($landlordCurrency === '') {
            $landlordCurrency = 'USD';
        }
        $landlordDateFormat = trim((string) get_option('dateformat'));
        if ($landlordDateFormat === '') {
            $landlordDateFormat = 'Y-m-d|%Y-%m-%d';
        }
        $landlordTimeFormat = trim((string) get_option('time_format'));
        if ($landlordTimeFormat !== '12') {
            $landlordTimeFormat = '24';
        }

        $formOptions = $this->get_tenant_workspace_form_options();
        $languageOptions = is_array($formOptions['languages'] ?? null) ? $formOptions['languages'] : ['english'];
        if (empty($languageOptions)) {
            $languageOptions = ['english'];
        }
        $timezoneOptions = is_array($formOptions['timezones'] ?? null) ? $formOptions['timezones'] : ['UTC'];
        if (empty($timezoneOptions)) {
            $timezoneOptions = ['UTC'];
        }
        $currencyOptions = is_array($formOptions['currencies'] ?? null) ? $formOptions['currencies'] : [];

        $storedLanguage = trim((string) $this->tenant_option('active_language', ''));
        if ($storedLanguage === '') {
            $storedLanguage = trim((string) $this->tenant_option('default_language', ''));
        }
        $resolvedLanguage = $storedLanguage !== '' ? $storedLanguage : $landlordLanguage;
        if ($resolvedLanguage === '' || !in_array($resolvedLanguage, $languageOptions, true)) {
            $resolvedLanguage = in_array($landlordLanguage, $languageOptions, true) ? $landlordLanguage : (string) reset($languageOptions);
        }

        $resolvedTimezone = trim((string) $this->tenant_option('default_timezone', $landlordTimezone));
        if ($resolvedTimezone === '' || !in_array($resolvedTimezone, $timezoneOptions, true)) {
            $resolvedTimezone = in_array($landlordTimezone, $timezoneOptions, true) ? $landlordTimezone : (string) reset($timezoneOptions);
        }

        $availableCurrencyCodes = [];
        foreach ($currencyOptions as $currencyOption) {
            $code = strtoupper(trim((string) ($currencyOption['code'] ?? '')));
            if ($code !== '') {
                $availableCurrencyCodes[] = $code;
            }
        }
        $availableCurrencyCodes = array_values(array_unique($availableCurrencyCodes));

        $resolvedCurrency = strtoupper(trim((string) $this->tenant_option('default_currency', $landlordCurrency)));
        if ($resolvedCurrency === '' || !in_array($resolvedCurrency, $availableCurrencyCodes, true)) {
            if (in_array($landlordCurrency, $availableCurrencyCodes, true)) {
                $resolvedCurrency = $landlordCurrency;
            } elseif (!empty($availableCurrencyCodes)) {
                $resolvedCurrency = (string) reset($availableCurrencyCodes);
            } else {
                $resolvedCurrency = $landlordCurrency !== '' ? $landlordCurrency : 'USD';
            }
        }

        return [
            'companyname'          => $this->tenant_option('companyname', (string) ($tenant['company_name'] ?? '')),
            'company_email'        => $this->tenant_option('company_email', ''),
            'companyphonenumber'   => $this->tenant_option('companyphonenumber', ''),
            'company_vat'          => $this->tenant_option('company_vat', ''),
            'kt_saas_mail_from_name' => $this->tenant_option('kt_saas_mail_from_name', ''),
            'kt_saas_mail_reply_to_email' => $this->tenant_option('kt_saas_mail_reply_to_email', ''),
            'bcc_emails'          => $this->tenant_option('bcc_emails', ''),
            'email_signature'     => $this->tenant_option('email_signature', ''),
            'email_header'        => $this->tenant_option('email_header', ''),
            'email_footer'        => $this->tenant_option('email_footer', ''),
            'invoice_company_name' => $this->tenant_option('invoice_company_name', (string) ($tenant['company_name'] ?? '')),
            'invoice_company_address' => $this->tenant_option('invoice_company_address', ''),
            'invoice_company_city' => $this->tenant_option('invoice_company_city', ''),
            'invoice_company_state' => $this->tenant_option('invoice_company_state', ''),
            'invoice_company_country_code' => $this->tenant_option('invoice_company_country_code', ''),
            'invoice_company_postal_code' => $this->tenant_option('invoice_company_postal_code', ''),
            'invoice_company_phonenumber' => $this->tenant_option('invoice_company_phonenumber', ''),
            'invoice_due_after'    => $this->tenant_option('invoice_due_after', '30'),
            'estimate_due_after'   => $this->tenant_option('estimate_due_after', '7'),
            'invoice_prefix'       => $this->tenant_option('invoice_prefix', 'INV-'),
            'next_invoice_number'  => $this->tenant_option('next_invoice_number', '1'),
            'invoice_number_format' => $this->tenant_option('invoice_number_format', '1'),
            'estimate_prefix'      => $this->tenant_option('estimate_prefix', 'EST-'),
            'next_estimate_number' => $this->tenant_option('next_estimate_number', '1'),
            'estimate_number_format' => $this->tenant_option('estimate_number_format', '1'),
            'credit_note_prefix'   => $this->tenant_option('credit_note_prefix', 'CN-'),
            'next_credit_note_number' => $this->tenant_option('next_credit_note_number', '1'),
            'credit_note_number_format' => $this->tenant_option('credit_note_number_format', '1'),
            'predefined_clientnote_invoice' => $this->tenant_option('predefined_clientnote_invoice', ''),
            'predefined_terms_invoice' => $this->tenant_option('predefined_terms_invoice', ''),
            'view_invoice_only_logged_in' => $this->tenant_option('view_invoice_only_logged_in', '0'),
            'exclude_invoice_from_client_area_with_draft_status' => $this->tenant_option('exclude_invoice_from_client_area_with_draft_status', '1'),
            'show_sale_agent_on_invoices' => $this->tenant_option('show_sale_agent_on_invoices', '1'),
            'show_project_on_invoice' => $this->tenant_option('show_project_on_invoice', '1'),
            'show_total_paid_on_invoice' => $this->tenant_option('show_total_paid_on_invoice', '1'),
            'show_credits_applied_on_invoice' => $this->tenant_option('show_credits_applied_on_invoice', '1'),
            'show_amount_due_on_invoice' => $this->tenant_option('show_amount_due_on_invoice', '1'),
            'view_estimate_only_logged_in' => $this->tenant_option('view_estimate_only_logged_in', '0'),
            'show_sale_agent_on_estimates' => $this->tenant_option('show_sale_agent_on_estimates', '1'),
            'show_project_on_estimate' => $this->tenant_option('show_project_on_estimate', '1'),
            'estimate_auto_convert_to_invoice_on_client_accept' => $this->tenant_option('estimate_auto_convert_to_invoice_on_client_accept', '1'),
            'exclude_estimate_from_client_area_with_draft_status' => $this->tenant_option('exclude_estimate_from_client_area_with_draft_status', '1'),
            'show_subscriptions_in_customers_area' => $this->tenant_option('show_subscriptions_in_customers_area', '1'),
            'after_subscription_payment_captured' => $this->tenant_option('after_subscription_payment_captured', 'send_invoice_and_receipt'),
            'create_invoice_from_recurring_only_on_paid_invoices' => $this->tenant_option('create_invoice_from_recurring_only_on_paid_invoices', '0'),
            'new_recurring_invoice_action' => $this->tenant_option('new_recurring_invoice_action', 'generate_and_send'),
            'active_language'      => $resolvedLanguage,
            'default_timezone'     => $resolvedTimezone,
            'default_currency'     => $resolvedCurrency,
            'dateformat'           => $this->tenant_option('dateformat', $landlordDateFormat),
            'time_format'          => $this->tenant_option('time_format', $landlordTimeFormat),
            'attach_invoice_to_payment_receipt_email' => $this->tenant_option('attach_invoice_to_payment_receipt_email', '0'),
            'automatically_send_invoice_overdue_reminder_after' => $this->tenant_option('automatically_send_invoice_overdue_reminder_after', '0'),
            'automatically_resend_invoice_overdue_reminder_after' => $this->tenant_option('automatically_resend_invoice_overdue_reminder_after', '0'),
            'invoice_due_notice_before' => $this->tenant_option('invoice_due_notice_before', '0'),
            'invoice_due_notice_resend_after' => $this->tenant_option('invoice_due_notice_resend_after', '0'),
            'send_estimate_expiry_reminder_before' => $this->tenant_option('send_estimate_expiry_reminder_before', '0'),
            'contract_expiration_before' => $this->tenant_option('contract_expiration_before', '0'),
            'contract_sign_reminder_every_days' => $this->tenant_option('contract_sign_reminder_every_days', '0'),
            'company_logo'         => $this->tenant_option('company_logo', ''),
            'company_logo_dark'    => $this->tenant_option('company_logo_dark', ''),
            'favicon'              => $this->tenant_option('favicon', ''),
            'kt_saas_use_custom_email_identity' => $this->tenant_option('kt_saas_use_custom_email_identity', '0'),
            'kt_saas_use_invoice_settings' => $this->tenant_option('kt_saas_use_invoice_settings', '0'),
            'kt_saas_use_custom_branding' => $this->tenant_option('kt_saas_use_custom_branding', '0'),
            'workspace_settings_staff_ids' => $this->tenant_option('kt_saas_workspace_settings_staff_ids', ''),
            'workspace_governance_view_staff_ids' => $this->tenant_option('kt_saas_workspace_governance_view_staff_ids', ''),
            'workspace_governance_manage_staff_ids' => $this->tenant_option('kt_saas_workspace_governance_manage_staff_ids', $this->tenant_option('kt_saas_workspace_governance_staff_ids', '')),
        ];
    }

    protected function resolveTenantLanguageOptions()
    {
        $languages = [];

        // Pull landlord-enabled languages first so tenant can persist values
        // even when tenant DB does not have localization options mirrored yet.
        $landlordDb = $this->config->item('kt_saas_landlord_db');
        if ($landlordDb) {
            $optionsTable = db_prefix() . 'options';
            if ($landlordDb->table_exists($optionsTable)) {
                $rows = $landlordDb
                    ->select('name,value')
                    ->from($optionsTable)
                    ->where_in('name', ['enabled_languages', 'active_language'])
                    ->get()
                    ->result_array();
                $landlordOptions = [];
                foreach ($rows as $row) {
                    $name = (string) ($row['name'] ?? '');
                    if ($name !== '') {
                        $landlordOptions[$name] = (string) ($row['value'] ?? '');
                    }
                }

                $landlordEnabledRaw = trim((string) ($landlordOptions['enabled_languages'] ?? ''));
                if ($landlordEnabledRaw !== '') {
                    $decoded = json_decode($landlordEnabledRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $decoded = maybe_unserialize($landlordEnabledRaw);
                    }
                    if (is_array($decoded)) {
                        foreach ($decoded as $language) {
                            $language = trim((string) $language);
                            if ($language !== '') {
                                $languages[] = $language;
                            }
                        }
                    }
                }

                $landlordActive = trim((string) ($landlordOptions['active_language'] ?? ''));
                if ($landlordActive !== '') {
                    $languages[] = $landlordActive;
                }
            }
        }

        $enabledLanguagesRaw = trim((string) get_option('enabled_languages'));
        if ($enabledLanguagesRaw !== '') {
            $decoded = json_decode($enabledLanguagesRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = maybe_unserialize($enabledLanguagesRaw);
            }
            if (is_array($decoded)) {
                foreach ($decoded as $language) {
                    $language = trim((string) $language);
                    if ($language !== '') {
                        $languages[] = $language;
                    }
                }
            }
        }

        $landlordActive = trim((string) get_option('active_language'));
        if ($landlordActive !== '') {
            $languages[] = $landlordActive;
        }

        if (isset($this->app) && method_exists($this->app, 'get_available_languages')) {
            foreach ((array) $this->app->get_available_languages() as $language) {
                $language = trim((string) $language);
                if ($language !== '') {
                    $languages[] = $language;
                }
            }
        }

        if (empty($languages)) {
            $languages = ['english'];
        }

        return array_values(array_unique($languages));
    }

    public function save_tenant_workspace_settings($tenantId, array $data)
    {
        $tenant = kt_saas_current_tenant();
        if ((int) ($tenant['id'] ?? 0) !== (int) $tenantId) {
            $tenant = $this->find_landlord_tenant_row($tenantId);
        }
        if (!$tenant) {
            return ['success' => false, 'message' => 'Không tìm thấy tenant.'];
        }

        $currentSettings = $this->get_tenant_workspace_settings($tenantId);
        $companyEditable = !kt_saas_is_tenant_runtime() || kt_saas_workspace_feature_allowed('workspace.company.edit', false);
        $companyName = trim((string) ($data['companyname'] ?? ''));
        if (!$companyEditable) {
            $companyName = trim((string) ($currentSettings['companyname'] ?? $companyName));
        }
        if ($companyName === '') {
            return ['success' => false, 'message' => 'Tên công ty là bắt buộc.'];
        }

        $options = $this->get_tenant_workspace_form_options();
        $companyEmail = trim((string) ($data['company_email'] ?? ''));
        if (!$companyEditable) {
            $companyEmail = trim((string) ($currentSettings['company_email'] ?? $companyEmail));
        }
        if ($companyEmail !== '' && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email công ty không hợp lệ.'];
        }

        $mailFromName = trim((string) ($data['kt_saas_mail_from_name'] ?? ''));
        $mailReplyToEmail = trim((string) ($data['kt_saas_mail_reply_to_email'] ?? ''));
        if ($mailReplyToEmail !== '' && !filter_var($mailReplyToEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Reply-to email không hợp lệ.'];
        }

        $bccEmails = $this->normalizeWorkspaceEmailList($data['bcc_emails'] ?? '');
        if ($bccEmails === null) {
            return ['success' => false, 'message' => 'Danh sách BCC email không hợp lệ.'];
        }

        $languageFallbackApplied = false;

        $requestedLanguage = trim((string) ($data['active_language'] ?? 'english')) ?: 'english';
        $activeLanguage = $requestedLanguage;
        if (!in_array($activeLanguage, $options['languages'], true) && !is_dir(APPPATH . 'language/' . $activeLanguage)) {
            $activeLanguage = (string) ($currentSettings['active_language'] ?? 'english');
            if ($activeLanguage === '') {
                $activeLanguage = 'english';
            }
            $languageFallbackApplied = true;
        }

        $landlordTimezone = trim((string) get_option('default_timezone'));
        if ($landlordTimezone === '') {
            $landlordTimezone = 'UTC';
        }
        $defaultTimezone = trim((string) ($data['default_timezone'] ?? ($currentSettings['default_timezone'] ?? $landlordTimezone)));
        if ($defaultTimezone === '') {
            $defaultTimezone = (string) ($currentSettings['default_timezone'] ?? $landlordTimezone);
        }
        if (!in_array($defaultTimezone, $options['timezones'], true)) {
            $defaultTimezone = in_array((string) ($currentSettings['default_timezone'] ?? ''), $options['timezones'], true)
                ? (string) $currentSettings['default_timezone']
                : $landlordTimezone;
        }

        $landlordDateFormat = trim((string) get_option('dateformat'));
        if ($landlordDateFormat === '') {
            $landlordDateFormat = 'Y-m-d|%Y-%m-%d';
        }
        $dateFormat = trim((string) ($data['dateformat'] ?? ($currentSettings['dateformat'] ?? $landlordDateFormat)));
        if ($dateFormat === '') {
            $dateFormat = (string) ($currentSettings['dateformat'] ?? $landlordDateFormat);
        }
        if (!array_key_exists($dateFormat, $options['date_formats'])) {
            $dateFormat = array_key_exists((string) ($currentSettings['dateformat'] ?? ''), $options['date_formats'])
                ? (string) $currentSettings['dateformat']
                : $landlordDateFormat;
        }

        $invoiceNumberFormat = $this->normalizeWorkspaceNumberFormat($data['invoice_number_format'] ?? '1', $options['number_formats']);
        $estimateNumberFormat = $this->normalizeWorkspaceNumberFormat($data['estimate_number_format'] ?? '1', $options['number_formats']);
        $creditNoteNumberFormat = $this->normalizeWorkspaceNumberFormat($data['credit_note_number_format'] ?? '1', $options['number_formats']);

        $landlordCurrency = strtoupper(trim((string) get_option('default_currency')));
        if ($landlordCurrency === '') {
            $landlordCurrency = 'USD';
        }
        $defaultCurrency = strtoupper(trim((string) ($data['default_currency'] ?? ($currentSettings['default_currency'] ?? $landlordCurrency))));
        $defaultCurrency = substr((string) preg_replace('/[^A-Z]/', '', $defaultCurrency), 0, 10);
        if ($defaultCurrency === '') {
            $defaultCurrency = strtoupper((string) ($currentSettings['default_currency'] ?? $landlordCurrency));
        }

        $invoiceCountryCode = strtoupper(trim((string) ($data['invoice_company_country_code'] ?? '')));
        $invoiceCountryCode = substr((string) preg_replace('/[^A-Z]/', '', $invoiceCountryCode), 0, 2);

        $emailSignature = trim((string) ($data['email_signature'] ?? ''));
        $emailHeader = trim((string) ($data['email_header'] ?? ''));
        $emailFooter = trim((string) ($data['email_footer'] ?? ''));
        if (function_exists('html_entity_decode')) {
            $emailSignature = html_entity_decode($emailSignature, ENT_QUOTES, 'UTF-8');
            $emailHeader = html_entity_decode($emailHeader, ENT_QUOTES, 'UTF-8');
            $emailFooter = html_entity_decode($emailFooter, ENT_QUOTES, 'UTF-8');
        }

        $payload = [
            'companyname'          => $companyName,
            'company_email'        => $companyEmail,
            'companyphonenumber'   => trim((string) ($data['companyphonenumber'] ?? '')),
            'company_vat'          => trim((string) ($data['company_vat'] ?? '')),
            'kt_saas_mail_from_name' => function_exists('mb_substr') ? mb_substr($mailFromName, 0, 191) : substr($mailFromName, 0, 191),
            'kt_saas_mail_reply_to_email' => function_exists('mb_substr') ? mb_substr($mailReplyToEmail, 0, 191) : substr($mailReplyToEmail, 0, 191),
            'bcc_emails'          => $bccEmails,
            'email_signature'     => $emailSignature,
            'email_header'        => $emailHeader,
            'email_footer'        => $emailFooter,
            'invoice_company_name' => trim((string) ($data['invoice_company_name'] ?? '')) ?: $companyName,
            'invoice_company_address' => trim((string) ($data['invoice_company_address'] ?? '')),
            'invoice_company_city' => trim((string) ($data['invoice_company_city'] ?? '')),
            'invoice_company_state' => trim((string) ($data['invoice_company_state'] ?? '')),
            'invoice_company_country_code' => $invoiceCountryCode,
            'invoice_company_postal_code' => trim((string) ($data['invoice_company_postal_code'] ?? '')),
            'invoice_company_phonenumber' => trim((string) ($data['invoice_company_phonenumber'] ?? '')),
            'invoice_due_after'    => (string) min(max(0, (int) ($data['invoice_due_after'] ?? 30)), 3650),
            'estimate_due_after'   => (string) min(max(0, (int) ($data['estimate_due_after'] ?? 7)), 3650),
            'invoice_prefix'       => $this->normalizeWorkspacePrefix($data['invoice_prefix'] ?? 'INV-', 'INV-'),
            'next_invoice_number'  => (string) min(max(1, (int) ($data['next_invoice_number'] ?? 1)), 999999999),
            'invoice_number_format' => $invoiceNumberFormat,
            'estimate_prefix'      => $this->normalizeWorkspacePrefix($data['estimate_prefix'] ?? 'EST-', 'EST-'),
            'next_estimate_number' => (string) min(max(1, (int) ($data['next_estimate_number'] ?? 1)), 999999999),
            'estimate_number_format' => $estimateNumberFormat,
            'credit_note_prefix'   => $this->normalizeWorkspacePrefix($data['credit_note_prefix'] ?? 'CN-', 'CN-'),
            'next_credit_note_number' => (string) min(max(1, (int) ($data['next_credit_note_number'] ?? 1)), 999999999),
            'credit_note_number_format' => $creditNoteNumberFormat,
            'predefined_clientnote_invoice' => trim((string) ($data['predefined_clientnote_invoice'] ?? '')),
            'predefined_terms_invoice' => trim((string) ($data['predefined_terms_invoice'] ?? '')),
            'view_invoice_only_logged_in' => isset($data['view_invoice_only_logged_in']) ? '1' : '0',
            'exclude_invoice_from_client_area_with_draft_status' => isset($data['exclude_invoice_from_client_area_with_draft_status']) ? '1' : '0',
            'show_sale_agent_on_invoices' => isset($data['show_sale_agent_on_invoices']) ? '1' : '0',
            'show_project_on_invoice' => isset($data['show_project_on_invoice']) ? '1' : '0',
            'show_total_paid_on_invoice' => isset($data['show_total_paid_on_invoice']) ? '1' : '0',
            'show_credits_applied_on_invoice' => isset($data['show_credits_applied_on_invoice']) ? '1' : '0',
            'show_amount_due_on_invoice' => isset($data['show_amount_due_on_invoice']) ? '1' : '0',
            'view_estimate_only_logged_in' => isset($data['view_estimate_only_logged_in']) ? '1' : '0',
            'show_sale_agent_on_estimates' => isset($data['show_sale_agent_on_estimates']) ? '1' : '0',
            'show_project_on_estimate' => isset($data['show_project_on_estimate']) ? '1' : '0',
            'estimate_auto_convert_to_invoice_on_client_accept' => isset($data['estimate_auto_convert_to_invoice_on_client_accept']) ? '1' : '0',
            'exclude_estimate_from_client_area_with_draft_status' => isset($data['exclude_estimate_from_client_area_with_draft_status']) ? '1' : '0',
            'show_subscriptions_in_customers_area' => isset($data['show_subscriptions_in_customers_area']) ? '1' : '0',
            'after_subscription_payment_captured' => $this->normalizeWorkspaceEnum(
                $data['after_subscription_payment_captured'] ?? 'send_invoice_and_receipt',
                ['send_invoice_and_receipt', 'send_invoice', 'send_payment_receipt', 'nothing'],
                'send_invoice_and_receipt'
            ),
            'create_invoice_from_recurring_only_on_paid_invoices' => isset($data['create_invoice_from_recurring_only_on_paid_invoices']) ? '1' : '0',
            'new_recurring_invoice_action' => $this->normalizeWorkspaceEnum(
                $data['new_recurring_invoice_action'] ?? 'generate_and_send',
                ['generate_and_send', 'generate_unpaid', 'generate_draft'],
                'generate_and_send'
            ),
            'active_language'      => $activeLanguage,
            'default_language'     => $activeLanguage,
            'default_timezone'     => $defaultTimezone,
            'default_currency'     => $defaultCurrency,
            'dateformat'           => $dateFormat,
            'time_format'          => (string) ((string) ($data['time_format'] ?? '24') === '12' ? '12' : '24'),
            'attach_invoice_to_payment_receipt_email' => isset($data['attach_invoice_to_payment_receipt_email']) ? '1' : '0',
            'automatically_send_invoice_overdue_reminder_after' => (string) min(max(0, (int) ($data['automatically_send_invoice_overdue_reminder_after'] ?? 0)), 3650),
            'automatically_resend_invoice_overdue_reminder_after' => (string) min(max(0, (int) ($data['automatically_resend_invoice_overdue_reminder_after'] ?? 0)), 3650),
            'invoice_due_notice_before' => (string) min(max(0, (int) ($data['invoice_due_notice_before'] ?? 0)), 3650),
            'invoice_due_notice_resend_after' => (string) min(max(0, (int) ($data['invoice_due_notice_resend_after'] ?? 0)), 3650),
            'send_estimate_expiry_reminder_before' => (string) min(max(0, (int) ($data['send_estimate_expiry_reminder_before'] ?? 0)), 3650),
            'contract_expiration_before' => (string) min(max(0, (int) ($data['contract_expiration_before'] ?? 0)), 3650),
            'contract_sign_reminder_every_days' => (string) min(max(0, (int) ($data['contract_sign_reminder_every_days'] ?? 0)), 3650),
            'kt_saas_workspace_settings_staff_ids' => $this->normalizeWorkspaceStaffIds($data['workspace_settings_staff_ids'] ?? []),
            'kt_saas_workspace_governance_view_staff_ids' => $this->normalizeWorkspaceStaffIds($data['workspace_governance_view_staff_ids'] ?? []),
            'kt_saas_workspace_governance_manage_staff_ids' => $this->normalizeWorkspaceStaffIds($data['workspace_governance_manage_staff_ids'] ?? []),
        ];

        $payload['kt_saas_workspace_governance_staff_ids'] = $payload['kt_saas_workspace_governance_manage_staff_ids'];

        if (kt_saas_is_tenant_runtime()) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $entitlements = new TenantEntitlementService();
            $viewerIds = json_decode($payload['kt_saas_workspace_governance_view_staff_ids'], true);
            $managerIds = json_decode($payload['kt_saas_workspace_governance_manage_staff_ids'], true);
            $viewerCount = is_array($viewerIds) ? count($viewerIds) : 0;
            $managerCount = is_array($managerIds) ? count($managerIds) : 0;
            $profile = kt_saas_current_profile();
            if (!$profile) {
                $profile = $entitlements->getRuntimeProfile(kt_saas_current_tenant());
            }

            try {
                $entitlements->assertWithinLimit('governance_viewers', $viewerCount, $profile, $viewerCount);
                $entitlements->assertWithinLimit('governance_managers', $managerCount, $profile, $managerCount);
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }

            if (!kt_saas_workspace_feature_allowed('workspace.finance.edit', false)) {
                $financeKeys = [
                    'invoice_company_name',
                    'invoice_company_address',
                    'invoice_company_city',
                    'invoice_company_state',
                    'invoice_company_country_code',
                    'invoice_company_postal_code',
                    'invoice_company_phonenumber',
                    'invoice_due_after',
                    'estimate_due_after',
                    'invoice_prefix',
                    'next_invoice_number',
                    'invoice_number_format',
                    'estimate_prefix',
                    'next_estimate_number',
                    'estimate_number_format',
                    'credit_note_prefix',
                    'next_credit_note_number',
                    'credit_note_number_format',
                    'predefined_clientnote_invoice',
                    'predefined_terms_invoice',
                ];

                foreach ($financeKeys as $key) {
                    $payload[$key] = (string) ($currentSettings[$key] ?? '');
                }
            }

            if (!kt_saas_workspace_feature_allowed('workspace.company.edit', false)) {
                $companyKeys = [
                    'companyname',
                    'company_email',
                    'companyphonenumber',
                    'company_vat',
                ];

                foreach ($companyKeys as $key) {
                    $payload[$key] = (string) ($currentSettings[$key] ?? '');
                }
            }

            if (!kt_saas_workspace_feature_allowed('workspace.localization.edit', false)) {
                $localizationKeys = [
                    'active_language',
                    'default_language',
                    'default_timezone',
                    'default_currency',
                    'dateformat',
                    'time_format',
                ];

                foreach ($localizationKeys as $key) {
                    $payload[$key] = (string) ($currentSettings[$key] ?? '');
                }
            }

            if (!kt_saas_workspace_feature_allowed('workspace.finance.advanced.edit', false)) {
                $advancedFinanceKeys = [
                    'view_invoice_only_logged_in',
                    'exclude_invoice_from_client_area_with_draft_status',
                    'show_sale_agent_on_invoices',
                    'show_project_on_invoice',
                    'show_total_paid_on_invoice',
                    'show_credits_applied_on_invoice',
                    'show_amount_due_on_invoice',
                    'view_estimate_only_logged_in',
                    'show_sale_agent_on_estimates',
                    'show_project_on_estimate',
                    'estimate_auto_convert_to_invoice_on_client_accept',
                    'exclude_estimate_from_client_area_with_draft_status',
                    'show_subscriptions_in_customers_area',
                    'after_subscription_payment_captured',
                    'create_invoice_from_recurring_only_on_paid_invoices',
                    'new_recurring_invoice_action',
                ];

                foreach ($advancedFinanceKeys as $key) {
                    $payload[$key] = (string) ($currentSettings[$key] ?? '');
                }
            }

            if (!kt_saas_workspace_feature_allowed('workspace.mail.identity.edit', false)) {
                $mailKeys = [
                    'kt_saas_mail_from_name',
                    'kt_saas_mail_reply_to_email',
                    'bcc_emails',
                    'email_signature',
                    'email_header',
                    'email_footer',
                ];

                foreach ($mailKeys as $key) {
                    $payload[$key] = (string) ($currentSettings[$key] ?? '');
                }
            }

            if (!kt_saas_workspace_feature_allowed('workspace.notifications.edit', false)) {
                $notificationKeys = [
                    'attach_invoice_to_payment_receipt_email',
                    'automatically_send_invoice_overdue_reminder_after',
                    'automatically_resend_invoice_overdue_reminder_after',
                    'invoice_due_notice_before',
                    'invoice_due_notice_resend_after',
                    'send_estimate_expiry_reminder_before',
                    'contract_expiration_before',
                    'contract_sign_reminder_every_days',
                ];

                foreach ($notificationKeys as $key) {
                    $payload[$key] = (string) ($currentSettings[$key] ?? '');
                }
            }

            if (!kt_saas_workspace_feature_allowed('workspace.governance.view', false)) {
                $payload['kt_saas_workspace_governance_view_staff_ids'] = (string) ($currentSettings['workspace_governance_view_staff_ids'] ?? '[]');
                $payload['kt_saas_workspace_governance_manage_staff_ids'] = (string) ($currentSettings['workspace_governance_manage_staff_ids'] ?? '[]');
                $payload['kt_saas_workspace_governance_staff_ids'] = $payload['kt_saas_workspace_governance_manage_staff_ids'];
            } elseif (!kt_saas_workspace_feature_allowed('workspace.governance.manage', false)) {
                $payload['kt_saas_workspace_governance_manage_staff_ids'] = (string) ($currentSettings['workspace_governance_manage_staff_ids'] ?? '[]');
                $payload['kt_saas_workspace_governance_staff_ids'] = $payload['kt_saas_workspace_governance_manage_staff_ids'];
            }
        }

        foreach ($payload as $name => $value) {
            $this->upsert_tenant_option($name, $value);
        }

        if (function_exists('kt_saas_clear_tenant_option_cache')) {
            kt_saas_clear_tenant_option_cache((int) $tenantId);
        }

        // Keep tenant base currency in sync with workspace default currency so finance screens
        // and monetary flows reflect the selected tenant currency immediately.
        $this->sync_tenant_base_currency($payload['default_currency']);

        $now = date('Y-m-d H:i:s');
        $landlordDb = $this->config->item('kt_saas_landlord_db');
        if ($landlordDb) {
            $landlordDb
                ->where('id', (int) $tenantId)
                ->update(db_prefix() . 'kt_saas_tenants', [
                    'company_name' => $payload['companyname'],
                    'timezone'     => $payload['default_timezone'],
                    'locale'       => $payload['active_language'],
                    'currency'     => $payload['default_currency'],
                    'updated_at'   => $now,
                    'updated_by'   => get_staff_user_id() ?: null,
                ]);
        }

        if (kt_saas_is_tenant_runtime()) {
            $runtimeTenant = kt_saas_current_tenant();
            if (is_array($runtimeTenant) && (int) ($runtimeTenant['id'] ?? 0) === (int) $tenantId) {
                $runtimeTenant['company_name'] = $payload['companyname'];
                $runtimeTenant['timezone'] = $payload['default_timezone'];
                $runtimeTenant['locale'] = $payload['active_language'];
                $runtimeTenant['currency'] = $payload['default_currency'];
                $GLOBALS['kt_saas_current_tenant'] = $runtimeTenant;
                $this->config->set_item('kt_saas_current_tenant', $runtimeTenant);
            }
        }

        $this->log_activity('tenant.workspace_settings_updated', 'info', [
            'tenant_id'   => (int) $tenantId,
            'tenant_code' => $tenant['tenant_code'] ?? null,
            'fields'      => array_keys($payload),
        ], (int) $tenantId);

        if (kt_saas_is_tenant_runtime()) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $entitlements = new TenantEntitlementService();
            $entitlements->persistUsageSnapshot(kt_saas_current_tenant());
        }

        $message = '';
        if ($languageFallbackApplied) {
            $message = 'Saved with language fallback to english because selected language is not enabled.';
        }

        return ['success' => true, 'message' => $message];
    }

    public function saveTenantProfile($tenantId, array $data)
    {
        $tenant = $this->resolveWorkspaceTenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Không tìm thấy doanh nghiệp.'];
        }

        $companyName = trim((string) ($data['companyname'] ?? ''));
        if ($companyName === '') {
            return ['success' => false, 'message' => 'Ten doanh nghiep la bat buoc.'];
        }

        $companyEmail = trim((string) ($data['company_email'] ?? ''));
        if ($companyEmail !== '' && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email doanh nghiep khong hop le.'];
        }

        $payload = [
            'companyname'        => $companyName,
            'company_email'      => $companyEmail,
            'companyphonenumber' => trim((string) ($data['companyphonenumber'] ?? '')),
            'company_vat'        => trim((string) ($data['company_vat'] ?? '')),
        ];

        $this->persistTenantWorkspaceOptions($tenantId, $payload, 'tenant.workspace_profile_updated');
        $this->syncTenantRuntimeAndLandlord($tenantId, [
            'company_name' => $payload['companyname'],
        ]);

        return ['success' => true, 'message' => 'Đã lưu hồ sơ doanh nghiệp.'];
    }

    public function saveTenantLocalization($tenantId, array $data)
    {
        $tenant = $this->resolveWorkspaceTenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Không tìm thấy doanh nghiệp.'];
        }

        $settings = $this->get_tenant_workspace_settings($tenantId);
        $options = $this->get_tenant_workspace_form_options();

        $language = trim((string) ($data['active_language'] ?? ($settings['active_language'] ?? 'english'))) ?: 'english';
        if (!in_array($language, (array) ($options['languages'] ?? []), true) && !is_dir(APPPATH . 'language/' . $language)) {
            return ['success' => false, 'message' => 'Ngon ngu khong hop le.'];
        }

        $timezone = trim((string) ($data['default_timezone'] ?? ($settings['default_timezone'] ?? 'UTC'))) ?: 'UTC';
        if (!in_array($timezone, (array) ($options['timezones'] ?? []), true)) {
            return ['success' => false, 'message' => 'Mui gio khong hop le.'];
        }

        $dateFormat = trim((string) ($data['dateformat'] ?? ($settings['dateformat'] ?? 'Y-m-d|%Y-%m-%d')));
        if (!array_key_exists($dateFormat, (array) ($options['date_formats'] ?? []))) {
            return ['success' => false, 'message' => 'Dinh dang ngay khong hop le.'];
        }

        $currencyCodes = [];
        foreach ((array) ($options['currencies'] ?? []) as $currency) {
            $code = strtoupper(trim((string) ($currency['code'] ?? '')));
            if ($code !== '') {
                $currencyCodes[$code] = $code;
            }
        }
        $currency = strtoupper(trim((string) ($data['default_currency'] ?? ($settings['default_currency'] ?? 'USD'))));
        $currency = substr((string) preg_replace('/[^A-Z]/', '', $currency), 0, 10);
        if ($currency === '' || (!empty($currencyCodes) && !isset($currencyCodes[$currency]))) {
            return ['success' => false, 'message' => 'Tien te khong hop le.'];
        }

        $payload = [
            'active_language'  => $language,
            'default_language' => $language,
            'default_timezone' => $timezone,
            'default_currency' => $currency,
            'dateformat'       => $dateFormat,
            'time_format'      => (string) ((string) ($data['time_format'] ?? '24') === '12' ? '12' : '24'),
        ];

        $this->persistTenantWorkspaceOptions($tenantId, $payload, 'tenant.workspace_localization_updated');
        $this->sync_tenant_base_currency($payload['default_currency']);
        $this->syncTenantRuntimeAndLandlord($tenantId, [
            'timezone' => $payload['default_timezone'],
            'locale'   => $payload['active_language'],
            'currency' => $payload['default_currency'],
        ]);

        return ['success' => true, 'message' => 'Đã lưu ngôn ngữ và định dạng.'];
    }

    public function saveTenantEmailIdentity($tenantId, array $data)
    {
        if (empty($data['use_custom_email_identity'])) {
            $this->persistTenantWorkspaceOptions($tenantId, ['kt_saas_use_custom_email_identity' => '0'], 'tenant.workspace_email_identity_disabled');
            return ['success' => true, 'message' => 'Da tat nhan dien email rieng.'];
        }

        $replyToEmail = trim((string) ($data['kt_saas_mail_reply_to_email'] ?? ''));
        if ($replyToEmail !== '' && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Reply-to email khong hop le.'];
        }

        $bccEmails = $this->normalizeWorkspaceEmailList($data['bcc_emails'] ?? '');
        if ($bccEmails === null) {
            return ['success' => false, 'message' => 'Danh sach BCC email khong hop le.'];
        }

        $emailSignature = trim((string) ($data['email_signature'] ?? ''));
        $emailHeader = trim((string) ($data['email_header'] ?? ''));
        $emailFooter = trim((string) ($data['email_footer'] ?? ''));
        if (function_exists('html_entity_decode')) {
            $emailSignature = html_entity_decode($emailSignature, ENT_QUOTES, 'UTF-8');
            $emailHeader = html_entity_decode($emailHeader, ENT_QUOTES, 'UTF-8');
            $emailFooter = html_entity_decode($emailFooter, ENT_QUOTES, 'UTF-8');
        }

        $fromName = trim((string) ($data['kt_saas_mail_from_name'] ?? ''));
        $payload = [
            'kt_saas_use_custom_email_identity' => '1',
            'kt_saas_mail_from_name' => function_exists('mb_substr') ? mb_substr($fromName, 0, 191) : substr($fromName, 0, 191),
            'kt_saas_mail_reply_to_email' => function_exists('mb_substr') ? mb_substr($replyToEmail, 0, 191) : substr($replyToEmail, 0, 191),
            'bcc_emails'      => $bccEmails,
            'email_signature' => $emailSignature,
            'email_header'    => $emailHeader,
            'email_footer'    => $emailFooter,
        ];

        $this->persistTenantWorkspaceOptions($tenantId, $payload, 'tenant.workspace_email_identity_updated');
        return ['success' => true, 'message' => 'Đã lưu nhận diện email.'];
    }

    public function saveTenantInvoiceDefaults($tenantId, array $data)
    {
        if (empty($data['use_invoice_settings'])) {
            $this->persistTenantWorkspaceOptions($tenantId, ['kt_saas_use_invoice_settings' => '0'], 'tenant.workspace_invoice_settings_disabled');
            return ['success' => true, 'message' => 'Da tat cau hinh hoa don rieng.'];
        }

        $settings = $this->get_tenant_workspace_settings($tenantId);
        $options = $this->get_tenant_workspace_form_options();
        $invoiceCountryCode = strtoupper(trim((string) ($data['invoice_company_country_code'] ?? '')));
        $invoiceCountryCode = substr((string) preg_replace('/[^A-Z]/', '', $invoiceCountryCode), 0, 2);

        $payload = [
            'kt_saas_use_invoice_settings' => '1',
            'invoice_company_name' => trim((string) ($data['invoice_company_name'] ?? '')) ?: (string) ($settings['companyname'] ?? ''),
            'invoice_company_address' => trim((string) ($data['invoice_company_address'] ?? '')),
            'invoice_company_city' => trim((string) ($data['invoice_company_city'] ?? '')),
            'invoice_company_state' => trim((string) ($data['invoice_company_state'] ?? '')),
            'invoice_company_country_code' => $invoiceCountryCode,
            'invoice_company_postal_code' => trim((string) ($data['invoice_company_postal_code'] ?? '')),
            'invoice_company_phonenumber' => trim((string) ($data['invoice_company_phonenumber'] ?? '')),
            'invoice_due_after' => (string) min(max(0, (int) ($data['invoice_due_after'] ?? 30)), 3650),
            'estimate_due_after' => (string) min(max(0, (int) ($data['estimate_due_after'] ?? 7)), 3650),
            'invoice_prefix' => $this->normalizeWorkspacePrefix($data['invoice_prefix'] ?? 'INV-', 'INV-'),
            'next_invoice_number' => (string) min(max(1, (int) ($data['next_invoice_number'] ?? 1)), 999999999),
            'invoice_number_format' => $this->normalizeWorkspaceNumberFormat($data['invoice_number_format'] ?? '1', $options['number_formats']),
            'estimate_prefix' => $this->normalizeWorkspacePrefix($data['estimate_prefix'] ?? 'EST-', 'EST-'),
            'next_estimate_number' => (string) min(max(1, (int) ($data['next_estimate_number'] ?? 1)), 999999999),
            'estimate_number_format' => $this->normalizeWorkspaceNumberFormat($data['estimate_number_format'] ?? '1', $options['number_formats']),
            'credit_note_prefix' => $this->normalizeWorkspacePrefix($data['credit_note_prefix'] ?? 'CN-', 'CN-'),
            'next_credit_note_number' => (string) min(max(1, (int) ($data['next_credit_note_number'] ?? 1)), 999999999),
            'credit_note_number_format' => $this->normalizeWorkspaceNumberFormat($data['credit_note_number_format'] ?? '1', $options['number_formats']),
            'predefined_clientnote_invoice' => trim((string) ($data['predefined_clientnote_invoice'] ?? '')),
            'predefined_terms_invoice' => trim((string) ($data['predefined_terms_invoice'] ?? '')),
        ];

        $this->persistTenantWorkspaceOptions($tenantId, $payload, 'tenant.workspace_invoice_defaults_updated');
        return ['success' => true, 'message' => 'Đã lưu cấu hình hóa đơn.'];
    }

    public function saveTenantFinanceAdvanced($tenantId, array $data)
    {
        $options = $this->get_tenant_workspace_form_options();
        $payload = [
            'invoice_prefix' => $this->normalizeWorkspacePrefix($data['invoice_prefix'] ?? 'INV-', 'INV-'),
            'next_invoice_number' => (string) min(max(1, (int) ($data['next_invoice_number'] ?? 1)), 999999999),
            'invoice_number_format' => $this->normalizeWorkspaceNumberFormat($data['invoice_number_format'] ?? '1', $options['number_formats']),
            'estimate_prefix' => $this->normalizeWorkspacePrefix($data['estimate_prefix'] ?? 'EST-', 'EST-'),
            'next_estimate_number' => (string) min(max(1, (int) ($data['next_estimate_number'] ?? 1)), 999999999),
            'estimate_number_format' => $this->normalizeWorkspaceNumberFormat($data['estimate_number_format'] ?? '1', $options['number_formats']),
            'credit_note_prefix' => $this->normalizeWorkspacePrefix($data['credit_note_prefix'] ?? 'CN-', 'CN-'),
            'next_credit_note_number' => (string) min(max(1, (int) ($data['next_credit_note_number'] ?? 1)), 999999999),
            'credit_note_number_format' => $this->normalizeWorkspaceNumberFormat($data['credit_note_number_format'] ?? '1', $options['number_formats']),
            'predefined_clientnote_invoice' => trim((string) ($data['predefined_clientnote_invoice'] ?? '')),
            'predefined_terms_invoice' => trim((string) ($data['predefined_terms_invoice'] ?? '')),
            'view_invoice_only_logged_in' => isset($data['view_invoice_only_logged_in']) ? '1' : '0',
            'exclude_invoice_from_client_area_with_draft_status' => isset($data['exclude_invoice_from_client_area_with_draft_status']) ? '1' : '0',
            'show_sale_agent_on_invoices' => isset($data['show_sale_agent_on_invoices']) ? '1' : '0',
            'show_project_on_invoice' => isset($data['show_project_on_invoice']) ? '1' : '0',
            'show_total_paid_on_invoice' => isset($data['show_total_paid_on_invoice']) ? '1' : '0',
            'show_credits_applied_on_invoice' => isset($data['show_credits_applied_on_invoice']) ? '1' : '0',
            'show_amount_due_on_invoice' => isset($data['show_amount_due_on_invoice']) ? '1' : '0',
            'view_estimate_only_logged_in' => isset($data['view_estimate_only_logged_in']) ? '1' : '0',
            'show_sale_agent_on_estimates' => isset($data['show_sale_agent_on_estimates']) ? '1' : '0',
            'show_project_on_estimate' => isset($data['show_project_on_estimate']) ? '1' : '0',
            'estimate_auto_convert_to_invoice_on_client_accept' => isset($data['estimate_auto_convert_to_invoice_on_client_accept']) ? '1' : '0',
            'exclude_estimate_from_client_area_with_draft_status' => isset($data['exclude_estimate_from_client_area_with_draft_status']) ? '1' : '0',
            'show_subscriptions_in_customers_area' => isset($data['show_subscriptions_in_customers_area']) ? '1' : '0',
            'after_subscription_payment_captured' => $this->normalizeWorkspaceEnum($data['after_subscription_payment_captured'] ?? 'send_invoice_and_receipt', ['send_invoice_and_receipt', 'send_invoice', 'send_payment_receipt', 'nothing'], 'send_invoice_and_receipt'),
            'create_invoice_from_recurring_only_on_paid_invoices' => isset($data['create_invoice_from_recurring_only_on_paid_invoices']) ? '1' : '0',
            'new_recurring_invoice_action' => $this->normalizeWorkspaceEnum($data['new_recurring_invoice_action'] ?? 'generate_and_send', ['generate_and_send', 'generate_unpaid', 'generate_draft'], 'generate_and_send'),
        ];

        $this->persistTenantWorkspaceOptions($tenantId, $payload, 'tenant.workspace_finance_advanced_updated');
        return ['success' => true, 'message' => 'Đã lưu cấu hình tài chính nâng cao.'];
    }

    public function saveTenantBranding($tenantId, array $data)
    {
        if (empty($data['use_custom_branding'])) {
            $this->persistTenantWorkspaceOptions($tenantId, ['kt_saas_use_custom_branding' => '0'], 'tenant.workspace_branding_disabled');
            return ['success' => true, 'message' => 'Da tat branding rieng.'];
        }

        $uploadResult = $this->handle_tenant_workspace_branding_uploads($tenantId);
        if (empty($uploadResult['success'])) {
            return $uploadResult;
        }

        $this->persistTenantWorkspaceOptions($tenantId, ['kt_saas_use_custom_branding' => '1'], 'tenant.workspace_branding_updated');
        return ['success' => true, 'message' => $uploadResult['message'] !== '' ? $uploadResult['message'] : 'Đã lưu thương hiệu.'];
    }

    public function saveTenantNotifications($tenantId, array $data)
    {
        $payload = [
            'attach_invoice_to_payment_receipt_email' => isset($data['attach_invoice_to_payment_receipt_email']) ? '1' : '0',
            'automatically_send_invoice_overdue_reminder_after' => (string) min(max(0, (int) ($data['automatically_send_invoice_overdue_reminder_after'] ?? 0)), 3650),
            'automatically_resend_invoice_overdue_reminder_after' => (string) min(max(0, (int) ($data['automatically_resend_invoice_overdue_reminder_after'] ?? 0)), 3650),
            'invoice_due_notice_before' => (string) min(max(0, (int) ($data['invoice_due_notice_before'] ?? 0)), 3650),
            'invoice_due_notice_resend_after' => (string) min(max(0, (int) ($data['invoice_due_notice_resend_after'] ?? 0)), 3650),
            'send_estimate_expiry_reminder_before' => (string) min(max(0, (int) ($data['send_estimate_expiry_reminder_before'] ?? 0)), 3650),
            'contract_expiration_before' => (string) min(max(0, (int) ($data['contract_expiration_before'] ?? 0)), 3650),
            'contract_sign_reminder_every_days' => (string) min(max(0, (int) ($data['contract_sign_reminder_every_days'] ?? 0)), 3650),
        ];

        $this->persistTenantWorkspaceOptions($tenantId, $payload, 'tenant.workspace_notifications_updated');
        return ['success' => true, 'message' => 'Đã lưu thông báo.'];
    }

    public function saveTenantGovernance($tenantId, array $data)
    {
        $payload = [
            'kt_saas_workspace_settings_staff_ids' => $this->normalizeWorkspaceStaffIds($data['workspace_settings_staff_ids'] ?? []),
            'kt_saas_workspace_governance_view_staff_ids' => $this->normalizeWorkspaceStaffIds($data['workspace_governance_view_staff_ids'] ?? []),
            'kt_saas_workspace_governance_manage_staff_ids' => $this->normalizeWorkspaceStaffIds($data['workspace_governance_manage_staff_ids'] ?? []),
        ];
        $payload['kt_saas_workspace_governance_staff_ids'] = $payload['kt_saas_workspace_governance_manage_staff_ids'];

        if (kt_saas_is_tenant_runtime()) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $entitlements = new TenantEntitlementService();
            $viewerIds = json_decode($payload['kt_saas_workspace_governance_view_staff_ids'], true);
            $managerIds = json_decode($payload['kt_saas_workspace_governance_manage_staff_ids'], true);
            $profile = kt_saas_current_profile();
            if (!$profile) {
                $profile = $entitlements->getRuntimeProfile(kt_saas_current_tenant());
            }
            try {
                $entitlements->assertWithinLimit('governance_viewers', is_array($viewerIds) ? count($viewerIds) : 0, $profile, is_array($viewerIds) ? count($viewerIds) : 0);
                $entitlements->assertWithinLimit('governance_managers', is_array($managerIds) ? count($managerIds) : 0, $profile, is_array($managerIds) ? count($managerIds) : 0);
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        $this->persistTenantWorkspaceOptions($tenantId, $payload, 'tenant.workspace_governance_settings_updated');
        return ['success' => true, 'message' => 'Đã lưu truy cập và điều phối.'];
    }

    private function resolveWorkspaceTenant($tenantId)
    {
        $tenant = kt_saas_current_tenant();
        if ((int) ($tenant['id'] ?? 0) !== (int) $tenantId) {
            $tenant = $this->find_landlord_tenant_row($tenantId);
        }
        return $tenant ?: null;
    }

    private function persistTenantWorkspaceOptions($tenantId, array $payload, $activityKey)
    {
        foreach ($payload as $name => $value) {
            $this->upsert_tenant_option($name, $value);
        }

        if (function_exists('kt_saas_clear_tenant_option_cache')) {
            kt_saas_clear_tenant_option_cache((int) $tenantId);
        }

        $tenant = $this->resolveWorkspaceTenant($tenantId);
        $this->log_activity($activityKey, 'info', [
            'tenant_id'   => (int) $tenantId,
            'tenant_code' => is_array($tenant) ? ($tenant['tenant_code'] ?? null) : null,
            'fields'      => array_keys($payload),
        ], (int) $tenantId);

        if (kt_saas_is_tenant_runtime()) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $entitlements = new TenantEntitlementService();
            $entitlements->persistUsageSnapshot(kt_saas_current_tenant());
        }
    }

    private function syncTenantRuntimeAndLandlord($tenantId, array $fields)
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $fields['updated_by'] = get_staff_user_id() ?: null;

        $landlordDb = $this->config->item('kt_saas_landlord_db');
        if ($landlordDb) {
            $landlordDb
                ->where('id', (int) $tenantId)
                ->update(db_prefix() . 'kt_saas_tenants', $fields);
        }

        if (kt_saas_is_tenant_runtime()) {
            $runtimeTenant = kt_saas_current_tenant();
            if (is_array($runtimeTenant) && (int) ($runtimeTenant['id'] ?? 0) === (int) $tenantId) {
                foreach ([
                    'company_name' => 'company_name',
                    'timezone'     => 'timezone',
                    'locale'       => 'locale',
                    'currency'     => 'currency',
                ] as $field => $runtimeKey) {
                    if (array_key_exists($field, $fields)) {
                        $runtimeTenant[$runtimeKey] = $fields[$field];
                    }
                }
                $GLOBALS['kt_saas_current_tenant'] = $runtimeTenant;
                $this->config->set_item('kt_saas_current_tenant', $runtimeTenant);
            }
        }
    }

    public function get_active_tenant_staff_members()
    {
        if (!$this->db->table_exists(db_prefix() . 'staff')) {
            return [];
        }

        return $this->db
            ->select('staffid, firstname, lastname, email, role, admin, active')
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->order_by('admin', 'desc')
            ->order_by('firstname', 'asc')
            ->order_by('lastname', 'asc')
            ->get()
            ->result_array();
    }

    public function get_active_tenant_staff_members_for_tenant($tenantId)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            $tenant = $this->find_landlord_tenant_row($tenantId);
        }

        if (!$tenant) {
            return [];
        }

        $tenantDb = $this->connect_tenant_runtime_db($tenant);
        if (!$tenantDb || !$tenantDb->table_exists(db_prefix() . 'staff')) {
            return [];
        }

        return $tenantDb
            ->select('staffid, firstname, lastname, email, role, admin, active, phonenumber, default_language, datecreated, last_login, last_activity, last_password_change')
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->order_by('admin', 'desc')
            ->order_by('firstname', 'asc')
            ->order_by('lastname', 'asc')
            ->get()
            ->result_array();
    }

    public function get_tenant_roles()
    {
        if (!$this->db->table_exists(db_prefix() . 'roles')) {
            return [];
        }

        return $this->db
            ->select('roleid, name, permissions')
            ->from(db_prefix() . 'roles')
            ->order_by('name', 'asc')
            ->get()
            ->result_array();
    }

    public function get_tenant_role_usage_counts()
    {
        if (
            !$this->db->table_exists(db_prefix() . 'roles')
            || !$this->db->table_exists(db_prefix() . 'staff')
        ) {
            return [];
        }

        $rows = $this->db
            ->select('role, COUNT(*) as total_count', false)
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->where('role >', 0)
            ->group_by('role')
            ->get()
            ->result_array();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) ($row['role'] ?? 0)] = (int) ($row['total_count'] ?? 0);
        }

        return $counts;
    }

    public function get_tenant_governance_summary()
    {
        $staffMembers = $this->get_active_tenant_staff_members();
        $roles = $this->get_tenant_roles();
        $adminCount = 0;

        foreach ($staffMembers as $staff) {
            if ((int) ($staff['admin'] ?? 0) === 1) {
                $adminCount++;
            }
        }

        return [
            'staff_count' => count($staffMembers),
            'admin_count' => $adminCount,
            'role_count'  => count($roles),
        ];
    }

    public function handle_tenant_workspace_branding_uploads($tenantId)
    {
        if (kt_saas_is_tenant_runtime() && !kt_saas_workspace_feature_allowed('workspace.branding.edit', false)) {
            foreach (['company_logo', 'company_logo_dark', 'favicon'] as $field) {
                if (!empty($_FILES[$field]['name'])) {
                    return ['success' => false, 'message' => 'Gói hiện tại không cho phép cập nhật branding của workspace.'];
                }
            }
        }

        $fields = [
            'company_logo'      => ['prefix' => 'tenant_' . (int) $tenantId . '_company_logo'],
            'company_logo_dark' => ['prefix' => 'tenant_' . (int) $tenantId . '_company_logo_dark'],
            'favicon'           => ['prefix' => 'tenant_' . (int) $tenantId . '_favicon'],
        ];

        $uploaded = [];
        $errors = [];
        foreach ($fields as $field => $config) {
            $result = $this->processTenantBrandingUpload((int) $tenantId, $field, $config['prefix']);
            if (($result['status'] ?? '') === 'uploaded') {
                $uploaded[$field] = $result['filename'];
            } elseif (($result['status'] ?? '') === 'error') {
                $errors[] = $result['message'];
            }
        }

        if (!empty($errors)) {
            $message = implode(' ', array_filter($errors));
            if (!empty($uploaded)) {
                $message = 'Một số tệp đã được lưu, nhưng còn lỗi: ' . $message;
            }

            return ['success' => false, 'message' => $message];
        }

        if (empty($uploaded)) {
            return ['success' => true, 'message' => ''];
        }

        return ['success' => true, 'message' => 'Đã cập nhật branding của workspace.'];
    }

    public function remove_tenant_workspace_branding($optionName)
    {
        if (kt_saas_is_tenant_runtime() && !kt_saas_workspace_feature_allowed('workspace.branding.edit', false)) {
            return false;
        }

        $optionName = trim((string) $optionName);
        if (!in_array($optionName, ['company_logo', 'company_logo_dark', 'favicon'], true)) {
            return false;
        }

        $tenant = kt_saas_current_tenant();
        $tenantId = (int) ($tenant['id'] ?? 0);
        $filename = basename($this->tenant_option($optionName, ''));
        $path = $tenantId > 0 ? kt_saas_tenant_branding_path($tenantId, $filename) : '';
        if ($filename !== '' && $path !== '' && $this->isTenantBrandingPath($tenantId, $path) && file_exists($path)) {
            @unlink($path);
        }

        $this->upsert_tenant_option($optionName, '');

        if (function_exists('kt_saas_clear_tenant_option_cache')) {
            kt_saas_clear_tenant_option_cache($tenantId);
        }

        return true;
    }

    public function get_tenant_subscription_profile($tenantId)
    {
        $subscription = $this->get_current_subscription($tenantId);
        if (!$subscription) {
            return null;
        }

        return $this->landlord_db()
            ->select('s.*, p.plan_code, p.plan_name, p.price, p.setup_fee, p.trial_days, p.grace_days, p.billing_cycle, p.module_json, p.limit_staff, p.limit_clients, p.limit_storage_mb, p.limit_invoices, p.limit_projects, p.limit_api_requests_daily, p.limit_warehouses, p.limit_automations')
            ->from(db_prefix() . 'kt_saas_subscriptions s')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('s.id', (int) $subscription['id'])
            ->get()
            ->row_array();
    }

    public function get_public_plans()
    {
        return $this->landlord_db()
            ->where('deleted_at IS NULL', null, false)
            ->where('is_active', 1)
            ->where('is_public', 1)
            ->order_by('price', 'asc')
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_saas_plans')
            ->result_array();
    }

    public function get_workspace_feature_catalog()
    {
        return [
            'workspace.governance.view' => [
                'module_name'  => 'workspace',
                'label'        => 'Governance: view',
                'description'  => 'Allow tenant users to open the Users & Roles governance workspace.',
            ],
            'workspace.branding.edit' => [
                'module_name'  => 'workspace',
                'label'        => 'Branding: edit',
                'description'  => 'Allow editing tenant workspace branding assets such as logos and favicon.',
            ],
            'workspace.company.edit' => [
                'module_name'  => 'workspace',
                'label'        => 'Company profile: edit',
                'description'  => 'Allow editing tenant company profile fields such as company name, email, phone and tax information.',
            ],
            'workspace.localization.edit' => [
                'module_name'  => 'workspace',
                'label'        => 'Localization: edit',
                'description'  => 'Allow editing tenant localization settings such as language, timezone, currency and date/time format.',
            ],
            'workspace.finance.edit' => [
                'module_name'  => 'workspace',
                'label'        => 'Finance basics: edit',
                'description'  => 'Allow editing tenant finance basics such as prefixes, numbering, due days, and default invoice text.',
            ],
            'workspace.finance.advanced.edit' => [
                'module_name'  => 'workspace',
                'label'        => 'Finance advanced: edit',
                'description'  => 'Allow editing tenant invoice, estimate, subscription, and recurring behavior toggles without exposing global cron or payment infrastructure.',
            ],
            'workspace.mail.identity.edit' => [
                'module_name'  => 'workspace',
                'label'        => 'Mail identity: edit',
                'description'  => 'Allow editing tenant mail identity such as sender display name, reply-to, BCC, and email signature/header/footer.',
            ],
            'email.own_credentials' => [
                'module_name'  => 'workspace',
                'label'        => 'Email: own credentials',
                'description'  => 'Allow tenant to use dedicated credentials instead of landlord global transport.',
            ],
            'email.custom_sender' => [
                'module_name'  => 'workspace',
                'label'        => 'Email: custom sender',
                'description'  => 'Allow tenant to customize sender name and reply-to while still using landlord transport.',
            ],
            'email.custom_smtp' => [
                'module_name'  => 'workspace',
                'label'        => 'Email: custom SMTP',
                'description'  => 'Allow tenant to configure custom SMTP transport credentials.',
            ],
            'email.brevo_smtp' => [
                'module_name'  => 'workspace',
                'label'        => 'Email: Brevo SMTP',
                'description'  => 'Allow tenant to use Brevo SMTP credentials.',
            ],
            'email.brevo_api' => [
                'module_name'  => 'workspace',
                'label'        => 'Email: Brevo API',
                'description'  => 'Allow tenant to configure Brevo API credentials.',
            ],
            'workspace.notifications.edit' => [
                'module_name'  => 'workspace',
                'label'        => 'Notifications: edit',
                'description'  => 'Allow editing tenant reminder timings and customer-facing notification preferences without exposing cron or mail transport infrastructure.',
            ],
            'workspace.governance.manage' => [
                'module_name'  => 'workspace',
                'label'        => 'Governance: manage',
                'description'  => 'Allow delegated governance managers to change governance assignments and settings.',
            ],
            'workspace.roles.manage' => [
                'module_name'  => 'workspace',
                'label'        => 'Roles: manage',
                'description'  => 'Allow creating, editing, and deleting tenant-local custom roles.',
            ],
            'workspace.departments.manage' => [
                'module_name'  => 'workspace',
                'label'        => 'Departments: manage',
                'description'  => 'Allow creating, editing, and deleting tenant-local departments.',
            ],
        ];
    }

    public function get_integration_feature_catalog()
    {
        return [
            'dms.enabled' => [
                'module_name' => 'dms',
                'label'       => 'Enable DMS',
                'description' => 'Allow the tenant to access the DMS execution engine and DMS dashboard surfaces.',
            ],
            'dms.checkin_enabled' => [
                'module_name' => 'dms',
                'label'       => 'Enable Check-in',
                'description' => 'Allow the tenant to sync and view DMS check-in events.',
            ],
            'dms.visit_enabled' => [
                'module_name' => 'dms',
                'label'       => 'Enable Visit Snapshot',
                'description' => 'Allow the tenant to sync and view DMS visit snapshots.',
            ],
            'dms.live_tracking_enabled' => [
                'module_name' => 'dms',
                'label'       => 'Enable Live Tracking',
                'description' => 'Allow the tenant to sync and view DMS live tracking snapshots.',
            ],
            'dms.route_analytics_enabled' => [
                'module_name' => 'dms',
                'label'       => 'Enable Route Analytics',
                'description' => 'Allow the tenant to view route performance analytics derived from DMS snapshots.',
            ],
            'dms.notification_bridge_enabled' => [
                'module_name' => 'dms',
                'label'       => 'Enable Notification Bridge',
                'description' => 'Allow the tenant to receive CRM notifications in DMS.',
            ],
            'dms.sso_enabled' => [
                'module_name' => 'dms',
                'label'       => 'Enable SSO',
                'description' => 'Allow the tenant to use trusted internal CRM-to-DMS SSO.',
            ],
            'kt_sepay.settings.edit' => [
                'module_name' => 'kt_sepay',
                'label'       => 'KT SePay: edit settings',
                'description' => 'Allow tenant to update SePay account, prefixes, QR and activation settings.',
            ],
            'kt_sepay.health.run' => [
                'module_name' => 'kt_sepay',
                'label'       => 'KT SePay: run health checks',
                'description' => 'Allow tenant to run SePay connection, bank account, QR, webhook and reconciliation tests.',
            ],
            'kt_sepay.reconcile.run' => [
                'module_name' => 'kt_sepay',
                'label'       => 'KT SePay: run reconciliation',
                'description' => 'Allow tenant to trigger manual SePay reconciliation and review reconciliation actions.',
            ],
            'kt_sepay.payment_requests.create' => [
                'module_name' => 'kt_sepay',
                'label'       => 'KT SePay: create manual payment requests',
                'description' => 'Allow tenant to create tenant-owned manual payment requests and QR links.',
            ],
            'einvoice.settings.edit' => [
                'module_name' => 'einvoice',
                'label'       => 'eInvoice: edit settings',
                'description' => 'Allow tenant to edit e-Invoice defaults, attachment toggles and template selection.',
            ],
        ];
    }

    public function get_dms_quota_feature_catalog()
    {
        return [
            'dms.package_code' => [
                'module_name' => 'dms',
                'label' => 'DMS package code',
                'description' => 'Commercial package label exposed to Marketplace, Setup Center, and DMS licensing payloads.',
                'input_type' => 'text',
            ],
            'dms.user_limit' => [
                'module_name' => 'dms',
                'label' => 'DMS user limit',
                'description' => 'Maximum number of provisioned DMS users allowed for the tenant.',
                'input_type' => 'number',
            ],
            'dms.store_limit' => [
                'module_name' => 'dms',
                'label' => 'Store limit',
                'description' => 'Maximum number of stores/outlets the DMS workspace can use.',
                'input_type' => 'number',
            ],
            'dms.product_limit' => [
                'module_name' => 'dms',
                'label' => 'Product sync limit',
                'description' => 'Maximum number of CRM products that can be synced into DMS.',
                'input_type' => 'number',
            ],
            'dms.checkin_monthly_limit' => [
                'module_name' => 'dms',
                'label' => 'Monthly check-in limit',
                'description' => 'Maximum number of check-ins allowed per calendar month.',
                'input_type' => 'number',
            ],
            'dms.photo_limit' => [
                'module_name' => 'dms',
                'label' => 'Monthly check-in photo limit',
                'description' => 'Maximum number of check-in photos allowed per calendar month.',
                'input_type' => 'number',
            ],
            'dms.report_export_limit' => [
                'module_name' => 'dms',
                'label' => 'Report export limit',
                'description' => 'Maximum number of DMS report exports allowed per billing period.',
                'input_type' => 'number',
            ],
            'dms.log_retention_months' => [
                'module_name' => 'dms',
                'label' => 'Log retention months',
                'description' => 'How many months DMS activity/log snapshots should be retained.',
                'input_type' => 'number',
            ],
        ];
    }

    public function get_dms_addon_package_catalog(): array
    {
        $catalog = [];
        if (function_exists('get_option')) {
            $raw = (string) get_option($this->get_dms_package_catalog_option_key());
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $catalog = $decoded;
                }
            }
        }

        if (empty($catalog)) {
            $catalog = $this->get_default_dms_addon_package_catalog();
        }

        return $this->normalize_dms_addon_package_catalog($catalog);
    }

    public function save_dms_addon_package(array $data, $existingCode = null): array
    {
        $catalog = $this->get_dms_addon_package_catalog();
        $existingCode = strtolower(trim((string) $existingCode));
        $package = $this->normalize_dms_addon_package_definition($data, $existingCode !== '' ? ($catalog[$existingCode] ?? []) : []);
        $packageCode = strtolower(trim((string) ($package['package_code'] ?? '')));

        if ($packageCode === '') {
            return ['success' => false, 'message' => 'Package code is required.'];
        }

        if ($existingCode !== '' && $existingCode !== $packageCode && isset($catalog[$existingCode]) && $this->count_dms_addon_package_usage($existingCode) > 0) {
            return ['success' => false, 'message' => 'Cannot rename a DMS package that is already assigned to tenant subscriptions.'];
        }

        if ($existingCode !== '' && $existingCode !== $packageCode) {
            unset($catalog[$existingCode]);
        }

        $catalog[$packageCode] = $package;
        uasort($catalog, static function (array $left, array $right) {
            return strcmp((string) ($left['name'] ?? $left['package_code'] ?? ''), (string) ($right['name'] ?? $right['package_code'] ?? ''));
        });

        $save = $this->persist_dms_addon_package_catalog($catalog);
        if (empty($save['success'])) {
            return $save;
        }

        $this->bump_dms_config_version('package_saved:' . $packageCode);

        return ['success' => true, 'package' => $catalog[$packageCode], 'catalog' => $catalog];
    }

    public function delete_dms_addon_package($packageCode): array
    {
        $packageCode = strtolower(trim((string) $packageCode));
        $catalog = $this->get_dms_addon_package_catalog();
        if ($packageCode === '' || !isset($catalog[$packageCode])) {
            return ['success' => false, 'message' => 'DMS package not found.'];
        }

        $usageCount = $this->count_dms_addon_package_usage($packageCode);
        if ($usageCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete a DMS package that is already used by tenant add-on subscriptions.', 'usage_count' => $usageCount];
        }

        unset($catalog[$packageCode]);
        $result = $this->persist_dms_addon_package_catalog($catalog);
        if (!empty($result['success'])) {
            $this->bump_dms_config_version('package_deleted:' . $packageCode);
        }

        return $result;
    }

    public function count_dms_addon_package_usage($packageCode): int
    {
        $packageCode = strtolower(trim((string) $packageCode));
        if ($packageCode === '' || !$this->canQueryDmsAddonTable()) {
            return 0;
        }

        return (int) $this->landlord_db()
            ->where('provider', 'khachtot')
            ->where('service_type', 'dms_runtime')
            ->where('package_code', $packageCode)
            ->count_all_results(db_prefix() . 'kt_saas_tenant_addons');
    }

    private function get_default_dms_addon_package_catalog(): array
    {
        return [
            'dms_mini' => [
                'id' => 1001,
                'package_code' => 'dms_mini',
                'name' => 'DMS Mini',
                'monthly_price' => 499000,
                'yearly_price' => 4990000,
                'user_limit' => 5,
                'store_limit' => 100,
                'product_limit' => 500,
                'checkin_monthly_limit' => 1000,
                'photo_limit' => 2500,
                'live_tracking_enabled' => false,
                'report_export_limit' => 25,
                'log_retention_months' => 3,
                'features' => [
                    'DMS_ENABLED' => true,
                    'CHECKIN_ENABLED' => true,
                    'VISIT_ENABLED' => true,
                    'LIVE_TRACKING_ENABLED' => false,
                    'ROUTE_ANALYTICS_ENABLED' => true,
                    'NOTIFICATION_BRIDGE_ENABLED' => true,
                    'SSO_ENABLED' => true,
                ],
            ],
            'dms_standard' => [
                'id' => 1002,
                'package_code' => 'dms_standard',
                'name' => 'DMS Standard',
                'monthly_price' => 1490000,
                'yearly_price' => 14900000,
                'user_limit' => 20,
                'store_limit' => 500,
                'product_limit' => 5000,
                'checkin_monthly_limit' => 10000,
                'photo_limit' => 20000,
                'live_tracking_enabled' => true,
                'report_export_limit' => 100,
                'log_retention_months' => 6,
                'features' => [
                    'DMS_ENABLED' => true,
                    'CHECKIN_ENABLED' => true,
                    'VISIT_ENABLED' => true,
                    'LIVE_TRACKING_ENABLED' => true,
                    'ROUTE_ANALYTICS_ENABLED' => true,
                    'NOTIFICATION_BRIDGE_ENABLED' => true,
                    'SSO_ENABLED' => true,
                ],
            ],
            'dms_pro' => [
                'id' => 1003,
                'package_code' => 'dms_pro',
                'name' => 'DMS Pro',
                'monthly_price' => 3490000,
                'yearly_price' => 34900000,
                'user_limit' => 50,
                'store_limit' => 2000,
                'product_limit' => 20000,
                'checkin_monthly_limit' => 50000,
                'photo_limit' => 100000,
                'live_tracking_enabled' => true,
                'report_export_limit' => 500,
                'log_retention_months' => 12,
                'features' => [
                    'DMS_ENABLED' => true,
                    'CHECKIN_ENABLED' => true,
                    'VISIT_ENABLED' => true,
                    'LIVE_TRACKING_ENABLED' => true,
                    'ROUTE_ANALYTICS_ENABLED' => true,
                    'NOTIFICATION_BRIDGE_ENABLED' => true,
                    'SSO_ENABLED' => true,
                ],
            ],
            'dms_enterprise' => [
                'id' => 1004,
                'package_code' => 'dms_enterprise',
                'name' => 'DMS Enterprise',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'user_limit' => 0,
                'store_limit' => 0,
                'product_limit' => 0,
                'checkin_monthly_limit' => 0,
                'photo_limit' => 0,
                'live_tracking_enabled' => true,
                'report_export_limit' => 0,
                'log_retention_months' => 12,
                'features' => [
                    'DMS_ENABLED' => true,
                    'CHECKIN_ENABLED' => true,
                    'VISIT_ENABLED' => true,
                    'LIVE_TRACKING_ENABLED' => true,
                    'ROUTE_ANALYTICS_ENABLED' => true,
                    'NOTIFICATION_BRIDGE_ENABLED' => true,
                    'SSO_ENABLED' => true,
                ],
            ],
        ];
    }

    private function get_dms_package_catalog_option_key(): string
    {
        return 'kt_saas_dms_package_catalog_json';
    }

    public function get_dms_config_version(): int
    {
        if (!function_exists('get_option')) {
            return 1;
        }

        return max(1, (int) get_option('kt_saas_dms_config_version'));
    }

    public function bump_dms_config_version(string $reason = ''): int
    {
        $version = $this->get_dms_config_version() + 1;
        if (function_exists('update_option')) {
            update_option('kt_saas_dms_config_version', (string) $version);
            update_option('kt_saas_dms_config_generated_at', gmdate('c'));
        }
        if (method_exists($this, 'log_activity')) {
            $this->log_activity('dms.config_version_changed', 'info', [
                'config_version' => $version,
                'reason' => trim($reason),
            ]);
        }

        return $version;
    }

    private function persist_dms_addon_package_catalog(array $catalog): array
    {
        $normalized = $this->normalize_dms_addon_package_catalog($catalog);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return ['success' => false, 'message' => 'Unable to encode DMS package catalog.'];
        }

        if (!function_exists('get_option') || !function_exists('update_option')) {
            return ['success' => false, 'message' => 'Perfex option storage is unavailable.'];
        }

        $optionKey = $this->get_dms_package_catalog_option_key();
        if ((string) get_option($optionKey) === '') {
            if (function_exists('add_option')) {
                add_option($optionKey, $encoded);
            } else {
                update_option($optionKey, $encoded);
            }
        } else {
            update_option($optionKey, $encoded);
        }

        return ['success' => true, 'catalog' => $normalized];
    }

    private function normalize_dms_addon_package_catalog(array $catalog): array
    {
        $normalized = [];
        foreach ($catalog as $packageCode => $package) {
            if (!is_array($package)) {
                continue;
            }

            $base = is_string($packageCode) ? $packageCode : (string) ($package['package_code'] ?? '');
            $definition = $this->normalize_dms_addon_package_definition($package, ['package_code' => $base]);
            $resolvedCode = strtolower(trim((string) ($definition['package_code'] ?? '')));
            if ($resolvedCode === '') {
                continue;
            }

            $normalized[$resolvedCode] = $definition;
        }

        return $normalized;
    }

    private function normalize_dms_addon_package_definition(array $package, array $defaults = []): array
    {
        $package = array_merge($defaults, $package);
        $packageCode = strtolower(trim((string) ($package['package_code'] ?? '')));
        $fallbackCode = strtolower(trim((string) ($defaults['package_code'] ?? '')));
        if ($packageCode === '') {
            $packageCode = $fallbackCode;
        }

        $id = (int) ($package['id'] ?? 0);
        if ($id <= 0) {
            $id = abs((int) sprintf('%u', crc32($packageCode !== '' ? $packageCode : uniqid('dms_pkg_', true))));
        }

        $features = is_array($package['features'] ?? null) ? $package['features'] : [];
        $featureDefaults = [
            'DMS_ENABLED' => true,
            'CHECKIN_ENABLED' => true,
            'VISIT_ENABLED' => true,
            'ORDERS_ENABLED' => true,
            'INVENTORY_ENABLED' => true,
            'FINANCE_VIEW_ENABLED' => true,
            'REPORTS_ENABLED' => true,
            'FIELD_FEEDBACK_ENABLED' => true,
            'MAP_ENABLED' => true,
            'LIVE_TRACKING_ENABLED' => !empty($package['live_tracking_enabled']),
            'ROUTE_ANALYTICS_ENABLED' => true,
            'NOTIFICATION_BRIDGE_ENABLED' => true,
            'SSO_ENABLED' => true,
        ];
        foreach ($featureDefaults as $featureKey => $defaultValue) {
            $features[$featureKey] = array_key_exists($featureKey, $features)
                ? (bool) $features[$featureKey]
                : (bool) $defaultValue;
        }

        $allowedBillingPeriods = $package['allowed_billing_periods'] ?? ($defaults['allowed_billing_periods'] ?? ['monthly', 'yearly']);
        if (is_string($allowedBillingPeriods)) {
            $allowedBillingPeriods = preg_split('/[\s,|]+/', $allowedBillingPeriods, -1, PREG_SPLIT_NO_EMPTY);
        }
        $allowedBillingPeriods = array_values(array_intersect(
            ['monthly', 'quarterly', 'yearly'],
            array_map(static fn ($value) => strtolower(trim((string) $value)), (array) $allowedBillingPeriods)
        ));
        if (empty($allowedBillingPeriods)) {
            $allowedBillingPeriods = ['monthly'];
        }
        $defaultBillingPeriod = strtolower(trim((string) ($package['default_billing_period'] ?? ($defaults['default_billing_period'] ?? ''))));
        if (!in_array($defaultBillingPeriod, $allowedBillingPeriods, true)) {
            $defaultBillingPeriod = $allowedBillingPeriods[0];
        }

        return [
            'id' => $id,
            'package_code' => $packageCode,
            'name' => trim((string) ($package['name'] ?? strtoupper(str_replace('_', ' ', $packageCode)))),
            'monthly_price' => max(0, (float) ($package['monthly_price'] ?? 0)),
            'quarterly_price' => array_key_exists('quarterly_price', $package)
                ? max(0, (float) ($package['quarterly_price'] ?? 0))
                : null,
            'yearly_price' => max(0, (float) ($package['yearly_price'] ?? 0)),
            'allowed_billing_periods' => $allowedBillingPeriods,
            'default_billing_period' => $defaultBillingPeriod,
            'user_limit' => max(0, (int) ($package['user_limit'] ?? 0)),
            'store_limit' => max(0, (int) ($package['store_limit'] ?? 0)),
            'product_limit' => max(0, (int) ($package['product_limit'] ?? 0)),
            'checkin_monthly_limit' => max(0, (int) ($package['checkin_monthly_limit'] ?? 0)),
            'order_monthly_limit' => max(0, (int) ($package['order_monthly_limit'] ?? 0)),
            'photo_limit' => max(0, (int) ($package['photo_limit'] ?? 0)),
            'live_tracking_enabled' => !empty($package['live_tracking_enabled']),
            'report_export_limit' => max(0, (int) ($package['report_export_limit'] ?? 0)),
            'log_retention_months' => max(0, (int) ($package['log_retention_months'] ?? 0)),
            'features' => $features,
        ];
    }

    public function get_dms_addon_package($packageCode): array
    {
        $normalized = strtolower(trim((string) $packageCode));
        $catalog = $this->get_dms_addon_package_catalog();

        return $catalog[$normalized] ?? [];
    }

    public function get_dms_addon_billing_cycle_options(array $package): array
    {
        $labels = function_exists('kt_saas_billing_cycles')
            ? kt_saas_billing_cycles()
            : [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'yearly' => 'Yearly',
            ];

        $priceFields = [
            'monthly' => 'monthly_price',
            'quarterly' => 'quarterly_price',
            'yearly' => 'yearly_price',
        ];

        $allowed = array_values((array) ($package['allowed_billing_periods'] ?? ['monthly', 'yearly']));
        $options = [];
        foreach ($priceFields as $cycle => $field) {
            if (!in_array($cycle, $allowed, true)) {
                continue;
            }
            if ($cycle === 'quarterly' && !array_key_exists($field, $package)) {
                continue;
            }

            if (!array_key_exists($field, $package) && !in_array($cycle, ['monthly', 'yearly'], true)) {
                continue;
            }

            $options[$cycle] = [
                'cycle' => $cycle,
                'label' => (string) ($labels[$cycle] ?? ucfirst($cycle)),
                'price' => round(max(0, (float) ($package[$field] ?? 0)), 2),
            ];
        }

        return $options;
    }

    public function get_tenant_dms_addon($tenantId, $activeOnly = false): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0 || !$this->canQueryDmsAddonTable()) {
            return [];
        }

        $db = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('provider', 'khachtot')
            ->where('service_type', 'dms_runtime');

        if ($activeOnly) {
            $db->where('status', 'active');
        }

        $row = $db->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_saas_tenant_addons')
            ->row_array();

        return is_array($row) ? $row : [];
    }

    public function resolve_tenant_dms_addon_context($tenantId): array
    {
        $tenantId = (int) $tenantId;
        $addon = $this->get_tenant_dms_addon($tenantId);
        if (empty($addon)) {
            return [];
        }

        if (empty($addon['billing_cycle'])) {
            $addon['billing_cycle'] = $this->resolve_tenant_dms_addon_billing_cycle(
                $tenantId,
                (string) ($addon['package_code'] ?? ''),
                (int) ($addon['subscription_id'] ?? 0)
            );
        }

        $package = $this->get_dms_addon_package((string) ($addon['package_code'] ?? ''));

        return [
            'addon' => $addon,
            'package' => $package,
            'status' => strtolower(trim((string) ($addon['status'] ?? 'none'))),
            'is_active' => strtolower(trim((string) ($addon['status'] ?? ''))) === 'active',
            'package_code' => strtolower(trim((string) ($addon['package_code'] ?? ''))),
        ];
    }

    private function resolve_tenant_dms_addon_billing_cycle($tenantId, $packageCode = '', $subscriptionId = 0): string
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return '';
        }

        $rows = $this->landlord_db()
            ->select('subscription_id, payload_json, status')
            ->from(db_prefix() . 'kt_saas_invoices')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'desc')
            ->limit(20)
            ->get()
            ->result_array();

        $packageCode = strtolower(trim((string) $packageCode));
        $subscriptionId = (int) $subscriptionId;

        foreach ($rows as $row) {
            if ($subscriptionId > 0 && (int) ($row['subscription_id'] ?? 0) > 0 && (int) ($row['subscription_id'] ?? 0) !== $subscriptionId) {
                continue;
            }

            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $reason = strtolower(trim((string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''))));
            if ($reason !== 'dms_addon_purchase') {
                continue;
            }

            $invoicePackageCode = strtolower(trim((string) ($payload['package_code'] ?? ($payload['context']['package_code'] ?? ''))));
            if ($packageCode !== '' && $invoicePackageCode !== '' && $invoicePackageCode !== $packageCode) {
                continue;
            }

            $billingCycle = strtolower(trim((string) ($payload['billing_cycle'] ?? ($payload['billing_summary']['billing_cycle'] ?? ($payload['context']['billing_cycle'] ?? '')))));
            if (in_array($billingCycle, ['monthly', 'quarterly', 'yearly'], true)) {
                return $billingCycle;
            }
        }

        return '';
    }

    public function upsert_tenant_dms_addon($tenantId, $packageCode, $status = 'payment_pending', array $options = []): array
    {
        $tenantId = (int) $tenantId;
        $packageCode = strtolower(trim((string) $packageCode));
        $status = strtolower(trim((string) $status));

        if ($tenantId <= 0) {
            return ['success' => false, 'message' => 'Tenant id is required.'];
        }

        $package = $this->get_dms_addon_package($packageCode);
        if (empty($package)) {
            return ['success' => false, 'message' => 'Invalid DMS package.'];
        }

        if (!$this->canQueryDmsAddonTable()) {
            return ['success' => false, 'message' => 'DMS add-on storage is not available.'];
        }

        $now = date('Y-m-d H:i:s');
        $allowedBillingPeriods = array_values((array) ($package['allowed_billing_periods'] ?? ['monthly']));
        $billingCycle = strtolower(trim((string) ($options['billing_cycle'] ?? ($package['default_billing_period'] ?? 'monthly'))));
        if (!in_array($billingCycle, $allowedBillingPeriods, true)) {
            return [
                'success' => false,
                'message' => 'Chu kỳ thanh toán không được phép đối với gói DMS đã chọn.',
                'allowed_billing_periods' => $allowedBillingPeriods,
            ];
        }
        $durationDays = $billingCycle === 'yearly'
            ? 365
            : ($billingCycle === 'quarterly' ? 90 : 30);
        $existing = $this->get_tenant_dms_addon($tenantId);
        $startsAt = array_key_exists('starts_at', $options)
            ? $options['starts_at']
            : (in_array($status, ['active', 'provisioning'], true)
                ? ($existing['starts_at'] ?? $now)
                : null);
        $endsAt = array_key_exists('ends_at', $options)
            ? $options['ends_at']
            : ($startsAt ? date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days', strtotime((string) $startsAt))) : null);
        $notes = trim((string) ($options['notes'] ?? ($existing['notes'] ?? 'Created from tenant marketplace DMS package selection.')));
        $payload = [
            'tenant_id' => $tenantId,
            'subscription_id' => array_key_exists('subscription_id', $options) ? ($options['subscription_id'] ?: null) : ($existing['subscription_id'] ?? null),
            'order_id' => array_key_exists('order_id', $options) ? ($options['order_id'] ?: null) : ($existing['order_id'] ?? null),
            'package_id' => array_key_exists('package_id', $options)
                ? ($options['package_id'] ?: (int) ($package['id'] ?? 0))
                : ((int) ($existing['package_id'] ?? 0) > 0 ? (int) $existing['package_id'] : (int) ($package['id'] ?? 0)),
            'provider' => 'khachtot',
            'service_type' => 'dms_runtime',
            'package_code' => $packageCode,
            'quantity_purchased' => 1,
            'quantity_used' => 0,
            'quantity_remaining' => 1,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'provider_account_id' => array_key_exists('provider_account_id', $options) ? ($options['provider_account_id'] ?: null) : ($existing['provider_account_id'] ?? null),
            'provider_order_code' => array_key_exists('provider_order_code', $options) ? ($options['provider_order_code'] ?: null) : ($existing['provider_order_code'] ?? null),
            'provider_status' => $status,
            'provisioning_job_id' => array_key_exists('provisioning_job_id', $options) ? ($options['provisioning_job_id'] ?: null) : ($existing['provisioning_job_id'] ?? null),
            'notes' => $notes,
            'updated_at' => $now,
        ];

        if (!empty($existing['id'])) {
            $this->landlord_db()
                ->where('id', (int) $existing['id'])
                ->update(db_prefix() . 'kt_saas_tenant_addons', $payload);
            $addonId = (int) $existing['id'];
        } else {
            $payload['created_at'] = $now;
            $this->landlord_db()->insert(db_prefix() . 'kt_saas_tenant_addons', $payload);
            $addonId = (int) $this->landlord_db()->insert_id();
        }

        return [
            'success' => $addonId > 0,
            'addon_id' => $addonId,
            'status' => $status,
            'package' => $package,
            'package_code' => $packageCode,
        ];
    }

    public function get_tenant_dms_addon_invoice($tenantId, $packageCode = null, array $statuses = ['draft', 'issued', 'pending_payment', 'overdue'])
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }

        $rows = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->where_in('status', array_values(array_unique(array_filter(array_map('strval', $statuses)))))
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_invoices')
            ->result_array();

        $packageCode = strtolower(trim((string) $packageCode));
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $reason = strtolower(trim((string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''))));
            if ($reason !== 'dms_addon_purchase') {
                continue;
            }

            $payloadPackageCode = strtolower(trim((string) ($payload['package_code'] ?? ($payload['context']['package_code'] ?? ''))));
            if ($packageCode !== '' && $payloadPackageCode !== $packageCode) {
                continue;
            }

            return $row;
        }

        return [];
    }

    public function ensure_dms_commercial_order_schema(): bool
    {
        $db = $this->landlord_db();
        $ordersTable = db_prefix() . 'kt_saas_dms_orders';
        $eventsTable = db_prefix() . 'kt_saas_dms_order_events';
        if (!$db->table_exists($ordersTable)) {
            $db->query("CREATE TABLE IF NOT EXISTS `{$ordersTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` BIGINT UNSIGNED NOT NULL,
                `crm_company_id` BIGINT UNSIGNED NULL,
                `tenant_slug` VARCHAR(191) NOT NULL,
                `package_code` VARCHAR(120) NOT NULL,
                `package_name` VARCHAR(191) NOT NULL,
                `billing_cycle` VARCHAR(30) NOT NULL,
                `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
                `invoice_id` BIGINT UNSIGNED NULL,
                `payment_provider` VARCHAR(50) NULL,
                `payment_reference` VARCHAR(128) NULL,
                `payment_url` TEXT NULL,
                `status` VARCHAR(40) NOT NULL DEFAULT 'draft',
                `payment_status` VARCHAR(40) NOT NULL DEFAULT 'payment_pending',
                `verification_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
                `subscription_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
                `provisioning_status` VARCHAR(40) NOT NULL DEFAULT 'not_started',
                `workspace_status` VARCHAR(40) NOT NULL DEFAULT 'not_ready',
                `provision_job_id` BIGINT UNSIGNED NULL,
                `last_payment_checked_at` DATETIME NULL,
                `blocked_reason` TEXT NULL,
                `metadata_json` LONGTEXT NULL,
                `created_by` INT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `activated_at` DATETIME NULL,
                `cancelled_at` DATETIME NULL,
                `idempotency_key` VARCHAR(128) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `dms_order_idempotency_unique` (`idempotency_key`),
                UNIQUE KEY `dms_order_invoice_unique` (`invoice_id`),
                KEY `dms_order_tenant_idx` (`tenant_id`),
                KEY `dms_order_status_idx` (`status`),
                KEY `dms_order_payment_idx` (`payment_status`, `verification_status`),
                KEY `dms_order_provision_idx` (`provisioning_status`, `workspace_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        if (!$db->table_exists($eventsTable)) {
            $db->query("CREATE TABLE IF NOT EXISTS `{$eventsTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` BIGINT UNSIGNED NOT NULL,
                `tenant_id` BIGINT UNSIGNED NOT NULL,
                `event_key` VARCHAR(80) NOT NULL,
                `event_status` VARCHAR(40) NULL,
                `message` VARCHAR(255) NOT NULL,
                `actor_type` VARCHAR(30) NOT NULL DEFAULT 'system',
                `actor_id` BIGINT NULL,
                `dedupe_key` VARCHAR(160) NOT NULL,
                `context_json` LONGTEXT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `dms_order_event_dedupe_unique` (`order_id`, `dedupe_key`),
                KEY `dms_order_event_order_idx` (`order_id`),
                KEY `dms_order_event_tenant_idx` (`tenant_id`),
                KEY `dms_order_event_created_idx` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        return $db->table_exists($ordersTable) && $db->table_exists($eventsTable);
    }

    public function append_dms_order_event($orderId, $eventKey, $message, array $context = [], $dedupeKey = '', $eventStatus = ''): bool
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return false;
        }
        $order = $this->landlord_db()
            ->where('id', (int) $orderId)
            ->get(db_prefix() . 'kt_saas_dms_orders')
            ->row_array();
        if (!$order) {
            return false;
        }
        $dedupeKey = trim((string) $dedupeKey);
        if ($dedupeKey === '') {
            $dedupeKey = trim((string) $eventKey) . '|' . sha1(json_encode($context));
        }
        $actorId = function_exists('get_staff_user_id') ? (int) get_staff_user_id() : 0;
        $payload = [
            'order_id' => (int) $orderId,
            'tenant_id' => (int) ($order['tenant_id'] ?? 0),
            'event_key' => trim((string) $eventKey),
            'event_status' => trim((string) $eventStatus) ?: null,
            'message' => mb_substr(trim((string) $message), 0, 255),
            'actor_type' => $actorId > 0 ? 'staff' : 'system',
            'actor_id' => $actorId > 0 ? $actorId : null,
            'dedupe_key' => mb_substr($dedupeKey, 0, 160),
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $db = $this->landlord_db();
        $exists = $db->where('order_id', (int) $orderId)
            ->where('dedupe_key', $payload['dedupe_key'])
            ->count_all_results(db_prefix() . 'kt_saas_dms_order_events') > 0;
        if ($exists) {
            return true;
        }

        return (bool) $db->insert(db_prefix() . 'kt_saas_dms_order_events', $payload);
    }

    public function update_dms_commercial_order($orderId, array $payload, $eventKey = '', $eventMessage = '', array $eventContext = []): bool
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return false;
        }
        $allowed = [
            'invoice_id', 'payment_provider', 'payment_reference', 'payment_url', 'status',
            'payment_status', 'verification_status', 'subscription_status', 'provisioning_status',
            'workspace_status', 'provision_job_id', 'last_payment_checked_at', 'blocked_reason',
            'metadata_json', 'activated_at', 'cancelled_at',
        ];
        $update = array_intersect_key($payload, array_flip($allowed));
        $update['updated_at'] = date('Y-m-d H:i:s');
        $ok = (bool) $this->landlord_db()
            ->where('id', (int) $orderId)
            ->update(db_prefix() . 'kt_saas_dms_orders', $update);
        if ($ok && trim((string) $eventKey) !== '') {
            $this->append_dms_order_event(
                (int) $orderId,
                (string) $eventKey,
                (string) $eventMessage,
                $eventContext,
                (string) $eventKey . '|' . ($eventContext['dedupe'] ?? sha1(json_encode($update))),
                (string) ($update['status'] ?? '')
            );
        }

        return $ok;
    }

    public function get_pending_dms_commercial_order($tenantId): array
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return [];
        }
        $row = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where_in('status', ['draft', 'payment_pending', 'payment_submitted', 'payment_verified', 'subscription_active', 'provisioning_pending', 'provisioning_running'])
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_saas_dms_orders')
            ->row_array();

        return is_array($row) ? $row : [];
    }

    public function create_dms_commercial_order(array $tenant, array $package, $billingCycle, $amount, array $options = []): array
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return ['success' => false, 'message' => 'Chưa thể khởi tạo sổ theo dõi đơn DMS.'];
        }
        $tenantId = (int) ($tenant['id'] ?? 0);
        $packageCode = strtolower(trim((string) ($package['package_code'] ?? '')));
        $billingCycle = strtolower(trim((string) $billingCycle));
        $attempt = (int) $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('package_code', $packageCode)
            ->where('billing_cycle', $billingCycle)
            ->count_all_results(db_prefix() . 'kt_saas_dms_orders') + 1;
        $idempotencyKey = hash('sha256', implode('|', ['dms-order-v1', $tenantId, $packageCode, $billingCycle, $attempt]));
        $now = date('Y-m-d H:i:s');
        $payload = [
            'tenant_id' => $tenantId,
            'crm_company_id' => !empty($tenant['landlord_client_id']) ? (int) $tenant['landlord_client_id'] : null,
            'tenant_slug' => (string) ($tenant['subdomain'] ?? ''),
            'package_code' => $packageCode,
            'package_name' => (string) ($package['name'] ?? strtoupper($packageCode)),
            'billing_cycle' => $billingCycle,
            'amount' => round((float) $amount, 2),
            'currency' => 'VND',
            'payment_provider' => (string) ($options['payment_provider'] ?? 'sepay'),
            'status' => 'draft',
            'payment_status' => 'payment_pending',
            'verification_status' => 'pending',
            'subscription_status' => 'pending',
            'provisioning_status' => 'not_started',
            'workspace_status' => 'not_ready',
            'metadata_json' => json_encode(['source' => (string) ($options['source'] ?? 'tenant_marketplace')], JSON_UNESCAPED_UNICODE),
            'created_by' => function_exists('get_staff_user_id') ? (get_staff_user_id() ?: null) : null,
            'created_at' => $now,
            'updated_at' => $now,
            'idempotency_key' => $idempotencyKey,
        ];
        $db = $this->landlord_db();
        $db->insert(db_prefix() . 'kt_saas_dms_orders', $payload);
        $orderId = (int) $db->insert_id();
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Chưa thể tạo đơn DMS.'];
        }
        $this->append_dms_order_event($orderId, 'package_selected', 'Doanh nghiệp đã chọn ' . $payload['package_name'] . '.', [
            'package_code' => $packageCode,
            'billing_cycle' => $billingCycle,
        ], 'package_selected');
        $this->append_dms_order_event($orderId, 'order_created', 'Đơn DMS đã được tạo.', [], 'order_created');

        return ['success' => true, 'order_id' => $orderId, 'order' => $this->get_dms_commercial_order($orderId)];
    }

    public function get_dms_order_events($orderId): array
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return [];
        }
        return $this->landlord_db()
            ->where('order_id', (int) $orderId)
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_saas_dms_order_events')
            ->result_array();
    }

    public function get_dms_commercial_order($orderId, $tenantId = null): array
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return [];
        }
        $db = $this->landlord_db();
        $db->where('id', (int) $orderId);
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }
        $order = $db->get(db_prefix() . 'kt_saas_dms_orders')->row_array();
        if (!$order) {
            return [];
        }

        return $this->enrich_dms_commercial_order($order);
    }

    public function get_dms_commercial_order_by_invoice($invoiceId): array
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return [];
        }
        $row = $this->landlord_db()
            ->where('invoice_id', (int) $invoiceId)
            ->limit(1)
            ->get(db_prefix() . 'kt_saas_dms_orders')
            ->row_array();
        return $row ? $this->enrich_dms_commercial_order($row) : [];
    }

    public function get_latest_dms_commercial_order($tenantId): array
    {
        $this->backfill_dms_commercial_orders((int) $tenantId);
        if (!$this->ensure_dms_commercial_order_schema()) {
            return [];
        }
        $row = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_saas_dms_orders')
            ->row_array();

        return $row ? $this->enrich_dms_commercial_order($row) : [];
    }

    public function get_dms_commercial_orders(array $filters = []): array
    {
        $this->backfill_dms_commercial_orders();
        if (!$this->ensure_dms_commercial_order_schema()) {
            return [];
        }
        $db = $this->landlord_db()
            ->select('o.*, t.company_name, t.tenant_code')
            ->from(db_prefix() . 'kt_saas_dms_orders o')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = o.tenant_id', 'left');
        foreach (['status', 'payment_status', 'verification_status', 'provisioning_status', 'workspace_status', 'package_code'] as $field) {
            if (trim((string) ($filters[$field] ?? '')) !== '') {
                $db->where('o.' . $field, trim((string) $filters[$field]));
            }
        }
        if ((int) ($filters['tenant_id'] ?? 0) > 0) {
            $db->where('o.tenant_id', (int) $filters['tenant_id']);
        }
        $rows = $db->order_by('o.id', 'desc')->limit(500)->get()->result_array();
        return array_map(function ($row) {
            return $this->enrich_dms_commercial_order($row);
        }, $rows);
    }

    public function backfill_dms_commercial_orders($tenantId = null): int
    {
        if (!$this->ensure_dms_commercial_order_schema()) {
            return 0;
        }
        $db = $this->landlord_db();
        $db->from(db_prefix() . 'kt_saas_invoices')->where('deleted_at IS NULL', null, false);
        if ($tenantId !== null) {
            $db->where('tenant_id', (int) $tenantId);
        }
        $invoices = $db->order_by('id', 'asc')->get()->result_array();
        $created = 0;
        foreach ($invoices as $invoice) {
            $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
            if (!is_array($payload) || strtolower((string) ($payload['reason'] ?? '')) !== 'dms_addon_purchase') {
                continue;
            }
            $exists = $this->landlord_db()->where('invoice_id', (int) $invoice['id'])->count_all_results(db_prefix() . 'kt_saas_dms_orders') > 0;
            if ($exists) {
                continue;
            }
            $tenant = $this->get_tenant((int) $invoice['tenant_id']);
            $package = $this->get_dms_addon_package((string) ($payload['package_code'] ?? ''));
            if (!$tenant || !$package) {
                continue;
            }
            $invoicePaid = (string) ($invoice['status'] ?? '') === 'paid';
            $now = date('Y-m-d H:i:s');
            $record = [
                'tenant_id' => (int) $tenant['id'],
                'crm_company_id' => !empty($tenant['landlord_client_id']) ? (int) $tenant['landlord_client_id'] : null,
                'tenant_slug' => (string) ($tenant['subdomain'] ?? ''),
                'package_code' => (string) ($payload['package_code'] ?? ''),
                'package_name' => (string) ($payload['package_name'] ?? ($package['name'] ?? 'DMS')),
                'billing_cycle' => (string) ($payload['billing_cycle'] ?? 'monthly'),
                'amount' => (float) ($invoice['grand_total'] ?? 0),
                'currency' => (string) ($invoice['currency'] ?? 'VND'),
                'invoice_id' => (int) $invoice['id'],
                'payment_provider' => (string) ($invoice['gateway'] ?? 'sepay'),
                'payment_reference' => (string) ($invoice['invoice_number'] ?? ''),
                'status' => $invoicePaid ? 'workspace_ready' : 'payment_pending',
                'payment_status' => $invoicePaid ? 'paid' : 'payment_pending',
                'verification_status' => $invoicePaid ? 'verified' : 'pending',
                'subscription_status' => $invoicePaid ? 'active' : 'pending',
                'provisioning_status' => $invoicePaid ? 'workspace_ready' : 'not_started',
                'workspace_status' => $invoicePaid ? 'ready' : 'not_ready',
                'created_by' => $invoice['created_by'] ?? null,
                'created_at' => (string) ($invoice['created_at'] ?? $now),
                'updated_at' => (string) ($invoice['updated_at'] ?? $now),
                'activated_at' => $invoicePaid ? (string) ($invoice['paid_at'] ?? $now) : null,
                'idempotency_key' => hash('sha256', 'legacy-dms-invoice|' . (int) $invoice['id']),
            ];
            $this->landlord_db()->insert(db_prefix() . 'kt_saas_dms_orders', $record);
            $orderId = (int) $this->landlord_db()->insert_id();
            if ($orderId > 0) {
                $created++;
                $this->append_dms_order_event($orderId, 'order_created', 'Đơn DMS được khôi phục từ hóa đơn hiện có.', [], 'legacy_order_created');
                $this->append_dms_order_event($orderId, 'invoice_created', 'Hóa đơn DMS đã được liên kết.', ['invoice_id' => (int) $invoice['id']], 'legacy_invoice_created');
                if ($invoicePaid) {
                    $this->append_dms_order_event($orderId, 'payment_verified', 'Thanh toán đã được xác minh.', [], 'legacy_payment_verified');
                    $this->append_dms_order_event($orderId, 'subscription_activated', 'Gói DMS đã được kích hoạt.', [], 'legacy_subscription_activated');
                    $this->append_dms_order_event($orderId, 'workspace_provision_done', 'Workspace DMS đã sẵn sàng.', [], 'legacy_workspace_ready');
                }
            }
        }

        return $created;
    }

    private function enrich_dms_commercial_order(array $order): array
    {
        $invoiceId = (int) ($order['invoice_id'] ?? 0);
        $invoice = $invoiceId > 0 ? ($this->get_invoice($invoiceId) ?: []) : [];
        $payment = [];
        if ($invoiceId > 0 && $this->landlord_db()->table_exists(db_prefix() . 'kt_saas_payments')) {
            $payment = $this->landlord_db()
                ->where('invoice_id', $invoiceId)
                ->where('deleted_at IS NULL', null, false)
                ->order_by('id', 'desc')
                ->limit(1)
                ->get(db_prefix() . 'kt_saas_payments')
                ->row_array() ?: [];
        }
        $paymentRequest = [];
        if ($invoiceId > 0 && $this->landlord_db()->table_exists(db_prefix() . 'kt_sepay_payment_requests')) {
            $paymentRequest = $this->landlord_db()
                ->select('id, tenant_id, invoice_id, amount, currency, reference_code, status, paid_amount, expires_at, processed_at, created_at, updated_at')
                ->where('invoice_id', $invoiceId)
                ->order_by('id', 'desc')
                ->limit(1)
                ->get(db_prefix() . 'kt_sepay_payment_requests')
                ->row_array() ?: [];
            $resolvedPaymentStatus = strtolower(trim((string) ($invoice['status'] ?? ''))) === 'paid'
                ? 'paid'
                : (string) ($paymentRequest['status'] ?? $order['payment_status']);
            $paymentUpdate = [];
            if (!empty($paymentRequest['reference_code']) && (string) ($order['payment_reference'] ?? '') !== (string) $paymentRequest['reference_code']) {
                $paymentUpdate['payment_reference'] = (string) $paymentRequest['reference_code'];
            }
            if ($resolvedPaymentStatus !== '' && (string) ($order['payment_status'] ?? '') !== $resolvedPaymentStatus) {
                $paymentUpdate['payment_status'] = $resolvedPaymentStatus;
            }
            if (!empty($paymentUpdate)) {
                $this->update_dms_commercial_order((int) $order['id'], $paymentUpdate);
                $order = array_merge($order, $paymentUpdate);
            }
        }
        $job = [];
        if (!empty($order['provision_job_id'])) {
            $job = $this->landlord_db()->where('id', (int) $order['provision_job_id'])->get(db_prefix() . 'kt_saas_provision_jobs')->row_array() ?: [];
        }
        $jobStatus = strtolower(trim((string) ($job['status'] ?? '')));
        if ($jobStatus !== '') {
            $state = [];
            if ($jobStatus === 'queued') {
                $state = ['status' => 'provisioning_pending', 'provisioning_status' => 'provisioning_pending'];
            } elseif ($jobStatus === 'running') {
                $state = ['status' => 'provisioning_running', 'provisioning_status' => 'provisioning_running'];
            } elseif ($jobStatus === 'done') {
                $state = ['status' => 'workspace_ready', 'provisioning_status' => 'workspace_ready', 'workspace_status' => 'ready', 'blocked_reason' => null];
            } elseif ($jobStatus === 'failed') {
                $state = ['status' => 'blocked', 'provisioning_status' => 'failed', 'workspace_status' => 'blocked', 'blocked_reason' => (string) ($job['error_message'] ?? 'Provisioning failed')];
            }
            $changed = false;
            foreach ($state as $key => $value) {
                if (($order[$key] ?? null) !== $value) {
                    $changed = true;
                    break;
                }
            }
            if ($changed && !empty($state)) {
                $event = $jobStatus === 'done' ? 'workspace_provision_done' : ($jobStatus === 'failed' ? 'failed' : 'workspace_provision_' . $jobStatus);
                $message = $jobStatus === 'done' ? 'Workspace DMS đã sẵn sàng.' : ($jobStatus === 'failed' ? 'Khởi tạo workspace cần được kiểm tra.' : 'Workspace DMS đang được khởi tạo.');
                $this->update_dms_commercial_order((int) $order['id'], $state, $event, $message, ['dedupe' => 'job|' . (int) ($job['id'] ?? 0) . '|' . $jobStatus]);
                $order = array_merge($order, $state);
            }
        }
        $order['invoice'] = $invoice;
        $order['payment'] = $payment;
        $order['payment_request'] = $paymentRequest;
        $order['provision_job'] = $job;
        $order['events'] = $this->get_dms_order_events((int) ($order['id'] ?? 0));
        $order['tenant'] = $this->get_tenant((int) ($order['tenant_id'] ?? 0)) ?: [];
        return $order;
    }

    public function dms_order_status_label($status): string
    {
        $labels = [
            'draft' => 'Đang tạo đơn', 'payment_pending' => 'Chờ thanh toán',
            'payment_submitted' => 'Đang xác minh thanh toán', 'pending' => 'Đang chờ',
            'verified' => 'Đã xác minh', 'paid' => 'Đã thanh toán',
            'payment_verified' => 'Đã xác minh thanh toán', 'payment_failed' => 'Thanh toán cần kiểm tra',
            'subscription_active' => 'Gói DMS đã kích hoạt', 'active' => 'Đang hoạt động',
            'provisioning_pending' => 'Chờ kích hoạt workspace', 'provisioning_running' => 'Đang kích hoạt DMS',
            'workspace_ready' => 'Workspace DMS đã sẵn sàng', 'ready' => 'Sẵn sàng',
            'not_ready' => 'Chưa sẵn sàng', 'not_started' => 'Chưa bắt đầu',
            'queued' => 'Đang chờ', 'running' => 'Đang xử lý', 'done' => 'Hoàn tất',
            'blocked' => 'Cần kiểm tra thủ công', 'failed' => 'Cần kiểm tra',
            'cancelled' => 'Đã hủy', 'suspended' => 'Tạm ngưng',
        ];
        $normalized = strtolower(trim((string) $status));
        return $labels[$normalized] ?? 'Chưa xác định';
    }

    public function get_dms_runtime_process_health($tenantId = null): array
    {
        $db = $this->landlord_db();
        $tenantId = $tenantId !== null ? (int) $tenantId : null;
        $cronTimestamp = (int) get_option('last_cron_run');
        $cronAge = $cronTimestamp > 0 ? time() - $cronTimestamp : null;
        $provision = ['queued' => 0, 'running' => 0, 'failed' => 0, 'done' => 0, 'latest' => []];
        foreach (['queued', 'running', 'failed', 'done'] as $status) {
            $db->from(db_prefix() . 'kt_saas_provision_jobs')->where('status', $status);
            if ($tenantId) {
                $db->where('tenant_id', $tenantId);
            }
            $provision[$status] = (int) $db->count_all_results();
        }
        $db->from(db_prefix() . 'kt_saas_provision_jobs');
        if ($tenantId) {
            $db->where('tenant_id', $tenantId);
        }
        $provision['latest'] = $db->order_by('id', 'desc')->limit(1)->get()->row_array() ?: [];

        $sync = ['queued' => 0, 'running' => 0, 'failed' => 0, 'done' => 0, 'latest' => []];
        if ($db->table_exists(db_prefix() . 'kt_integration_sync_jobs')) {
            foreach (['queued', 'running', 'failed', 'done'] as $status) {
                $db->from(db_prefix() . 'kt_integration_sync_jobs')->where('status', $status);
                if ($tenantId) {
                    $db->where('tenant_id', $tenantId);
                }
                $sync[$status] = (int) $db->count_all_results();
            }
            $db->from(db_prefix() . 'kt_integration_sync_jobs');
            if ($tenantId) {
                $db->where('tenant_id', $tenantId);
            }
            $sync['latest'] = $db->order_by('id', 'desc')->limit(1)->get()->row_array() ?: [];
        }

        $latestStarted = strtotime((string) ($provision['latest']['started_at'] ?? '')) ?: 0;
        $staleLock = (string) ($provision['latest']['status'] ?? '') === 'running' && $latestStarted > 0 && $latestStarted < strtotime('-15 minutes');
        return [
            'cron' => [
                'status' => $cronAge !== null && $cronAge <= 600 ? 'running' : 'not_running',
                'last_run_at' => $cronTimestamp > 0 ? date('Y-m-d H:i:s', $cronTimestamp) : null,
                'age_seconds' => $cronAge,
            ],
            'provision' => $provision,
            'staff_sync' => $sync,
            'stale_lock' => $staleLock,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function cancel_dms_commercial_order($orderId, $tenantId = null, $reason = ''): array
    {
        $order = $this->get_dms_commercial_order((int) $orderId, $tenantId !== null ? (int) $tenantId : null);
        if (!$order) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn DMS.'];
        }
        if (in_array((string) ($order['payment_status'] ?? ''), ['paid', 'verified'], true)
            || in_array((string) ($order['subscription_status'] ?? ''), ['active'], true)) {
            return ['success' => false, 'message' => 'Đơn đã thanh toán hoặc kích hoạt không thể hủy trực tiếp.'];
        }
        $invoiceId = (int) ($order['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $invoice = $this->get_invoice($invoiceId);
            if ($invoice && !in_array((string) ($invoice['status'] ?? ''), ['paid', 'cancelled'], true)) {
                $this->update_invoice($invoiceId, ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $addon = $this->get_tenant_dms_addon((int) $order['tenant_id']);
        if ($addon && (string) ($addon['provider_order_code'] ?? '') === (string) ($order['payment_reference'] ?? '')) {
            $this->landlord_db()->where('id', (int) $addon['id'])->update(db_prefix() . 'kt_saas_tenant_addons', [
                'status' => 'cancelled',
                'provider_status' => 'cancelled',
                'notes' => trim((string) $reason) ?: 'DMS order cancelled before payment.',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->update_dms_commercial_order((int) $order['id'], [
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'verification_status' => 'cancelled',
            'subscription_status' => 'cancelled',
            'provisioning_status' => 'cancelled',
            'workspace_status' => 'not_ready',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'blocked_reason' => trim((string) $reason) ?: null,
        ], 'cancelled', 'Đơn DMS đã được hủy.', ['dedupe' => 'cancelled']);

        return ['success' => true, 'order' => $this->get_dms_commercial_order((int) $order['id'])];
    }

    public function recheck_dms_commercial_order_payment($orderId): array
    {
        $order = $this->get_dms_commercial_order((int) $orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn DMS.'];
        }
        $invoice = (array) ($order['invoice'] ?? []);
        $now = date('Y-m-d H:i:s');
        $this->update_dms_commercial_order((int) $order['id'], ['last_payment_checked_at' => $now]);
        if ((string) ($invoice['status'] ?? '') !== 'paid') {
            $this->append_dms_order_event((int) $order['id'], 'payment_rechecked', 'Đã kiểm tra lại thanh toán; chưa tìm thấy giao dịch phù hợp.', [], 'payment_rechecked|' . date('YmdH'));
            return ['success' => true, 'verified' => false, 'message' => 'Chưa tìm thấy thanh toán đã xác minh.'];
        }
        $payment = (array) ($order['payment'] ?? []);
        $activation = $this->activate_tenant_dms_addon_from_invoice($invoice, [
            'payment_reference' => (string) ($payment['payment_reference'] ?? $order['payment_reference'] ?? ''),
            'gateway' => (string) ($payment['gateway'] ?? $order['payment_provider'] ?? 'sepay'),
        ]);
        return [
            'success' => !empty($activation['success']),
            'verified' => !empty($activation['success']),
            'message' => !empty($activation['success']) ? 'Thanh toán đã được xác minh và tiến trình kích hoạt đã được cập nhật.' : (string) ($activation['message'] ?? 'Chưa thể cập nhật kích hoạt.'),
        ];
    }

    public function retry_dms_commercial_order_provisioning($orderId): array
    {
        $order = $this->get_dms_commercial_order((int) $orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn DMS.'];
        }
        if ((string) ($order['subscription_status'] ?? '') !== 'active') {
            return ['success' => false, 'message' => 'Chỉ có thể khởi tạo workspace sau khi gói DMS đã được kích hoạt.'];
        }
        $jobs = $this->get_tenant_provision_jobs((int) $order['tenant_id'], ['install_salesflow_dms'], 10);
        foreach ($jobs as $job) {
            if (in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
                return ['success' => true, 'reused' => true, 'message' => 'Đã có tiến trình khởi tạo đang chạy.', 'job_id' => (int) $job['id']];
            }
        }
        $tenant = $this->get_tenant((int) $order['tenant_id']);
        $jobId = (int) $this->create_provision_job((int) $order['tenant_id'], 'install_salesflow_dms', [
            'tenant_id' => (int) $order['tenant_id'],
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'app_slug' => 'salesflow-dms',
            'source' => 'dms_commercial_order_retry',
            'package_code' => (string) $order['package_code'],
            'invoice_id' => (int) ($order['invoice_id'] ?? 0),
            'dms_order_id' => (int) $order['id'],
        ]);
        if ($jobId <= 0) {
            return ['success' => false, 'message' => 'Chưa thể tạo tiến trình khởi tạo workspace.'];
        }
        $this->update_dms_commercial_order((int) $order['id'], [
            'status' => 'provisioning_pending',
            'provisioning_status' => 'provisioning_pending',
            'workspace_status' => 'not_ready',
            'provision_job_id' => $jobId,
            'blocked_reason' => null,
        ], 'workspace_provision_started', 'Đã yêu cầu khởi tạo lại workspace DMS.', ['dedupe' => 'retry|' . $jobId]);
        return ['success' => true, 'job_id' => $jobId, 'message' => 'Đã xếp hàng khởi tạo lại workspace DMS.'];
    }

    public function create_or_refresh_tenant_dms_addon_invoice($tenantId, $packageCode, $billingCycle = 'monthly', array $options = []): array
    {
        $tenantId = (int) $tenantId;
        $packageCode = strtolower(trim((string) $packageCode));
        $billingCycle = strtolower(trim((string) $billingCycle));
        if (!in_array($billingCycle, ['monthly', 'quarterly', 'yearly'], true)) {
            return ['success' => false, 'message' => 'Chu kỳ thanh toán không hợp lệ.'];
        }

        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $subscription = $this->get_tenant_subscription_profile($tenantId);
        if (empty($subscription['id'])) {
            return ['success' => false, 'message' => 'CRM subscription is required before purchasing the DMS add-on.'];
        }

        $package = $this->get_dms_addon_package($packageCode);
        if (empty($package)) {
            return ['success' => false, 'message' => 'Invalid DMS package.'];
        }

        $availableCycles = $this->get_dms_addon_billing_cycle_options($package);
        if (empty($availableCycles)) {
            return ['success' => false, 'message' => 'The selected DMS package has no billing cycle configured.'];
        }

        if (!isset($availableCycles[$billingCycle])) {
            return [
                'success' => false,
                'message' => 'Chu kỳ thanh toán không được phép đối với gói DMS đã chọn.',
                'allowed_billing_periods' => array_keys($availableCycles),
            ];
        }

        $existingAddonContext = $this->resolve_tenant_dms_addon_context($tenantId);
        $existingAddonStatus = strtolower(trim((string) ($existingAddonContext['status'] ?? 'none')));
        $existingAddonPackageCode = strtolower(trim((string) ($existingAddonContext['package_code'] ?? '')));
        if ($existingAddonStatus === 'active' && $existingAddonPackageCode !== '' && $existingAddonPackageCode !== $packageCode) {
            return ['success' => false, 'message' => 'This tenant already has an active DMS package. Review the current subscription before changing package.'];
        }

        $amount = $this->resolve_dms_addon_price($package, $billingCycle);
        if ($existingAddonStatus === 'active') {
            $activeOrder = $this->get_latest_dms_commercial_order($tenantId);
            return [
                'success' => false,
                'message' => 'Gói DMS của doanh nghiệp đang hoạt động. Vui lòng dùng quy trình đổi gói.',
                'order' => $activeOrder,
            ];
        }

        $orderId = 0;
        $pendingOrder = $this->get_pending_dms_commercial_order($tenantId);
        if (!empty($pendingOrder)) {
            $sameSelection = (string) ($pendingOrder['package_code'] ?? '') === $packageCode
                && (string) ($pendingOrder['billing_cycle'] ?? '') === $billingCycle;
            if (!$sameSelection) {
                return [
                    'success' => false,
                    'message' => 'Bạn đã có đơn DMS đang chờ xử lý. Hãy tiếp tục thanh toán hoặc hủy đơn hiện tại trước khi chọn gói khác.',
                    'pending_order' => $this->get_dms_commercial_order((int) $pendingOrder['id'], $tenantId),
                    'conflict' => true,
                ];
            }
            $orderId = (int) ($pendingOrder['id'] ?? 0);
            if (!empty($pendingOrder['invoice_id'])) {
                $reusedInvoice = $this->get_invoice((int) $pendingOrder['invoice_id']);
                $this->append_dms_order_event($orderId, 'payment_page_opened', 'Doanh nghiệp tiếp tục thanh toán đơn DMS đang có.', [], 'payment_page_reopened|' . date('YmdH'));
                return [
                    'success' => !empty($reusedInvoice['id']),
                    'reused' => true,
                    'order_id' => $orderId,
                    'order' => $this->get_dms_commercial_order($orderId, $tenantId),
                    'invoice_id' => (int) ($reusedInvoice['id'] ?? 0),
                    'invoice' => $reusedInvoice ?: [],
                    'package' => $package,
                    'package_code' => $packageCode,
                    'billing_cycle' => $billingCycle,
                    'addon_status' => 'payment_pending',
                ];
            }
        }
        if ($orderId <= 0) {
            $orderResult = $this->create_dms_commercial_order($tenant, $package, $billingCycle, $amount, [
                'source' => trim((string) ($options['source'] ?? 'tenant_marketplace')),
                'payment_provider' => 'sepay',
            ]);
            if (empty($orderResult['success'])) {
                return $orderResult;
            }
            $orderId = (int) ($orderResult['order_id'] ?? 0);
        }
        $existingInvoice = $this->get_tenant_dms_addon_invoice($tenantId, $packageCode);
        $now = date('Y-m-d H:i:s');
        $dueDays = max((int) kt_saas_get_option('kt_saas_billing_due_days', '7'), 0);
        $dueDate = date('Y-m-d', strtotime('+' . $dueDays . ' days'));
        $invoiceStatus = $amount > 0 ? 'pending_payment' : 'paid';
        $invoiceNumber = '';

        require_once module_dir_path(KT_SAAS_MODULE, 'services/BillingEngineService.php');
        $billing = new BillingEngineService();
        if (!empty($existingInvoice['invoice_number'])) {
            $invoiceNumber = (string) $existingInvoice['invoice_number'];
        } else {
            $invoiceNumber = (string) $billing->buildInvoiceNumber($tenant, $subscription);
        }

        $payload = [
            'source' => trim((string) ($options['source'] ?? 'tenant_marketplace')),
            'reason' => 'dms_addon_purchase',
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'subscription_id' => (int) ($subscription['id'] ?? 0),
            'subscription_status' => (string) ($subscription['status'] ?? ''),
            'package_code' => $packageCode,
            'package_name' => (string) ($package['name'] ?? strtoupper($packageCode)),
            'billing_cycle' => $billingCycle,
            'service_type' => 'dms_runtime',
            'dms_order_id' => $orderId,
            'line_items' => [
                [
                    'type' => 'dms_addon',
                    'label' => (string) ($package['name'] ?? strtoupper($packageCode)),
                    'amount' => round($amount, 2),
                    'quantity' => 1,
                ],
            ],
            'billing_summary' => [
                'currency' => 'VND',
                'subtotal' => round($amount, 2),
                'grand_total' => round($amount, 2),
                'billing_cycle' => $billingCycle,
            ],
            'context' => [
                'reason' => 'dms_addon_purchase',
                'package_code' => $packageCode,
                'billing_cycle' => $billingCycle,
                'service_type' => 'dms_runtime',
                'dms_order_id' => $orderId,
            ],
        ];

        $invoiceRecord = [
            'tenant_id' => $tenantId,
            'subscription_id' => (int) ($subscription['id'] ?? 0),
            'invoice_number' => $invoiceNumber,
            'status' => $invoiceStatus,
            'currency' => 'VND',
            'subtotal' => round($amount, 2),
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => round($amount, 2),
            'issued_at' => $now,
            'due_date' => $dueDate,
            'paid_at' => $invoiceStatus === 'paid' ? $now : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => get_staff_user_id() ?: null,
            'updated_by' => get_staff_user_id() ?: null,
        ];

        if (!empty($existingInvoice['id'])) {
            $this->update_invoice((int) $existingInvoice['id'], $invoiceRecord);
            $invoiceId = (int) $existingInvoice['id'];
        } else {
            $this->landlord_db()->insert(db_prefix() . 'kt_saas_invoices', $invoiceRecord);
            $invoiceId = (int) $this->landlord_db()->insert_id();
        }

        $this->update_dms_commercial_order($orderId, [
            'invoice_id' => $invoiceId,
            'payment_reference' => $invoiceNumber,
            'status' => $invoiceStatus === 'paid' ? 'payment_verified' : 'payment_pending',
            'payment_status' => $invoiceStatus === 'paid' ? 'paid' : 'payment_pending',
            'verification_status' => $invoiceStatus === 'paid' ? 'verified' : 'pending',
        ], 'invoice_created', 'Hóa đơn DMS đã được tạo và liên kết với đơn.', [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'dedupe' => 'invoice|' . $invoiceId,
        ]);
        if ($invoiceStatus !== 'paid') {
            $this->append_dms_order_event($orderId, 'payment_pending', 'Đơn DMS đang chờ thanh toán.', [], 'payment_pending');
        }

        if ($existingAddonStatus !== 'active') {
            $addonResult = $this->upsert_tenant_dms_addon($tenantId, $packageCode, $invoiceStatus === 'paid' ? 'active' : 'payment_pending', [
                'billing_cycle' => $billingCycle,
                'subscription_id' => (int) ($subscription['id'] ?? 0),
                'provider_order_code' => $invoiceNumber,
                'notes' => $invoiceStatus === 'paid'
                    ? 'DMS add-on activated immediately because invoice total is zero.'
                    : 'Waiting for invoice payment before DMS add-on activation.',
            ]);

            if (empty($addonResult['success'])) {
                return $addonResult;
            }
        }

        $invoice = $this->get_invoice($invoiceId);
        $this->log_activity('marketplace.salesflow_dms_checkout_created', 'info', [
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'package_code' => $packageCode,
            'billing_cycle' => $billingCycle,
            'amount' => round($amount, 2),
        ], $tenantId);

        return [
            'success' => !empty($invoice['id']),
            'order_id' => $orderId,
            'order' => $this->get_dms_commercial_order($orderId, $tenantId),
            'invoice_id' => $invoiceId,
            'invoice' => $invoice,
            'package' => $package,
            'package_code' => $packageCode,
            'billing_cycle' => $billingCycle,
            'addon_status' => $invoiceStatus === 'paid' ? 'active' : 'payment_pending',
        ];
    }

    public function activate_tenant_dms_addon_from_invoice(array $invoice, array $paymentData = []): array
    {
        $invoiceId = (int) ($invoice['id'] ?? 0);
        $tenantId = (int) ($invoice['tenant_id'] ?? 0);
        if ($invoiceId <= 0 || $tenantId <= 0) {
            return ['success' => false, 'message' => 'Invoice context is incomplete.'];
        }
        if (strtolower(trim((string) ($invoice['status'] ?? ''))) !== 'paid') {
            return ['success' => false, 'message' => 'Không thể kích hoạt DMS khi hóa đơn chưa được xác minh thanh toán.'];
        }

        $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $reason = strtolower(trim((string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''))));
        if ($reason !== 'dms_addon_purchase') {
            return ['success' => false, 'message' => 'Invoice does not belong to DMS add-on purchase flow.'];
        }

        $this->backfill_dms_commercial_orders($tenantId);
        $commercialOrder = $this->get_dms_commercial_order_by_invoice($invoiceId);
        $commercialOrderId = (int) ($commercialOrder['id'] ?? 0);
        if ($commercialOrderId > 0) {
            $paymentReference = trim((string) ($paymentData['payment_reference'] ?? ($commercialOrder['payment_reference'] ?? $invoice['invoice_number'] ?? '')));
            $this->update_dms_commercial_order($commercialOrderId, [
                'status' => 'payment_verified',
                'payment_status' => 'paid',
                'verification_status' => 'verified',
                'payment_reference' => $paymentReference,
                'last_payment_checked_at' => date('Y-m-d H:i:s'),
            ], 'payment_verified', 'Thanh toán DMS đã được xác minh.', [
                'invoice_id' => $invoiceId,
                'dedupe' => 'payment_verified|' . $invoiceId,
            ]);
        }

        $packageCode = strtolower(trim((string) ($payload['package_code'] ?? ($payload['context']['package_code'] ?? ''))));
        $billingCycle = strtolower(trim((string) ($payload['billing_cycle'] ?? ($payload['context']['billing_cycle'] ?? 'monthly'))));
        if (!in_array($billingCycle, ['monthly', 'quarterly', 'yearly'], true)) {
            $billingCycle = 'monthly';
        }

        $addonResult = $this->upsert_tenant_dms_addon($tenantId, $packageCode, 'active', [
            'billing_cycle' => $billingCycle,
            'subscription_id' => (int) ($invoice['subscription_id'] ?? 0),
            'provider_order_code' => (string) ($invoice['invoice_number'] ?? ''),
            'notes' => 'Activated from DMS add-on invoice #' . $invoiceId . ' after successful payment.',
        ]);

        if (empty($addonResult['success'])) {
            return $addonResult;
        }

        $moduleResult = $this->save_tenant_module_override($tenantId, 'kt_integration_hub', 'enable');
        if (empty($moduleResult['success'])) {
            return $moduleResult;
        }

        $tenant = $this->get_tenant($tenantId);
        $this->sync_tenant_module_registry($tenantId, $tenant ?: []);

        $jobs = $this->get_tenant_provision_jobs($tenantId, ['install_salesflow_dms'], 10);
        $hasReusableProvisionJob = false;
        $latestProvisionJobId = null;
        foreach ($jobs as $job) {
            $jobStatus = strtolower(trim((string) ($job['status'] ?? '')));
            if (in_array($jobStatus, ['queued', 'running', 'done'], true)) {
                $hasReusableProvisionJob = true;
                $latestProvisionJobId = (int) ($job['id'] ?? 0);
                break;
            }
        }

        if (!$hasReusableProvisionJob) {
            $latestProvisionJobId = (int) $this->create_provision_job($tenantId, 'install_salesflow_dms', [
                'tenant_id' => $tenantId,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'app_slug' => 'salesflow-dms',
                'source' => 'dms_addon_invoice_payment',
                'package_code' => $packageCode,
                'invoice_id' => $invoiceId,
            ]);

            if ($latestProvisionJobId > 0) {
                $this->upsert_tenant_dms_addon($tenantId, $packageCode, 'active', [
                    'billing_cycle' => $billingCycle,
                    'subscription_id' => (int) ($invoice['subscription_id'] ?? 0),
                    'provider_order_code' => (string) ($invoice['invoice_number'] ?? ''),
                    'provisioning_job_id' => $latestProvisionJobId,
                    'notes' => 'Activated from DMS add-on invoice #' . $invoiceId . ' and queued for provisioning.',
                ]);
            }
        }

        if ($commercialOrderId > 0) {
            $this->update_dms_commercial_order($commercialOrderId, [
                'status' => $latestProvisionJobId > 0 ? 'provisioning_pending' : 'subscription_active',
                'subscription_status' => 'active',
                'provisioning_status' => $latestProvisionJobId > 0 ? 'provisioning_pending' : 'not_started',
                'workspace_status' => 'not_ready',
                'provision_job_id' => $latestProvisionJobId ?: null,
                'activated_at' => date('Y-m-d H:i:s'),
                'blocked_reason' => null,
            ], 'subscription_activated', 'Gói DMS đã được kích hoạt.', [
                'invoice_id' => $invoiceId,
                'provision_job_id' => $latestProvisionJobId,
                'dedupe' => 'subscription_activated|' . $invoiceId,
            ]);
            if ($latestProvisionJobId > 0) {
                $this->append_dms_order_event($commercialOrderId, 'workspace_provision_started', 'Workspace DMS đang được khởi tạo.', [
                    'provision_job_id' => $latestProvisionJobId,
                ], 'workspace_provision_started|' . $latestProvisionJobId);
            }
        }

        $this->log_activity('marketplace.salesflow_dms_addon_activated', 'success', [
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            'package_code' => $packageCode,
            'billing_cycle' => $billingCycle,
            'provision_job_id' => $latestProvisionJobId,
            'payment_reference' => (string) ($paymentData['payment_reference'] ?? ''),
        ], $tenantId);

        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'package_code' => $packageCode,
            'provision_job_id' => $latestProvisionJobId,
            'dms_order_id' => $commercialOrderId,
        ];
    }

    public function tenant_has_active_dms_addon($tenantId): bool
    {
        $context = $this->resolve_tenant_dms_addon_context($tenantId);

        return !empty($context['is_active']);
    }

    public function get_legacy_dms_tenant_inventory(array $tenantIds = []): array
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $entitlementService = new TenantEntitlementService();
        $db = $this->landlord_db()
            ->where('deleted_at IS NULL', null, false);

        $tenantIds = array_values(array_unique(array_filter(array_map('intval', $tenantIds))));
        if (!empty($tenantIds)) {
            $db->where_in('id', $tenantIds);
        }

        $tenants = $db->order_by('id', 'asc')->get(db_prefix() . 'kt_saas_tenants')->result_array();
        $inventory = [];
        foreach ($tenants as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId <= 0) {
                continue;
            }

            $addonContext = $this->resolve_tenant_dms_addon_context($tenantId);
            $hasAddon = !empty($addonContext['addon']['id']);
            $legacyEnabled = !$hasAddon && (bool) $entitlementService->getFeatureValue($tenantId, 'dms.enabled', false);
            $subscription = $this->get_tenant_subscription_profile($tenantId);
            $inventory[] = [
                'tenant_id' => $tenantId,
                'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                'company_name' => (string) ($tenant['company_name'] ?? ''),
                'plan_id' => (int) ($tenant['plan_id'] ?? 0),
                'subscription_id' => (int) ($subscription['id'] ?? 0),
                'subscription_status' => (string) ($subscription['status'] ?? ''),
                'legacy_shim' => $legacyEnabled,
                'addon_row_id' => (int) ($addonContext['addon']['id'] ?? 0),
                'addon_status' => (string) ($addonContext['status'] ?? 'none'),
                'recommended_package_code' => $this->resolve_legacy_dms_package_code($tenantId, $entitlementService),
            ];
        }

        return $inventory;
    }

    public function backfill_legacy_dms_addon_subscriptions(array $tenantIds = [], array $options = []): array
    {
        $inventory = $this->get_legacy_dms_tenant_inventory($tenantIds);
        $results = [
            'checked' => count($inventory),
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'rows' => [],
        ];

        foreach ($inventory as $row) {
            if (empty($row['legacy_shim']) || !empty($row['addon_row_id'])) {
                $results['skipped']++;
                $results['rows'][] = $row + [
                    'action' => 'skipped',
                    'message' => empty($row['legacy_shim'])
                        ? 'Tenant is not using the legacy DMS shim.'
                        : 'Tenant already has a DMS add-on row.',
                ];
                continue;
            }

            $packageCode = strtolower(trim((string) ($row['recommended_package_code'] ?? '')));
            if ($packageCode === '') {
                $packageCode = strtolower(trim((string) ($options['default_package_code'] ?? 'dms_standard')));
            }

            $tenant = $this->get_tenant((int) $row['tenant_id']);
            $startsAt = (string) ($tenant['created_at'] ?? '') !== '' ? (string) $tenant['created_at'] : date('Y-m-d H:i:s');
            $result = $this->upsert_tenant_dms_addon((int) $row['tenant_id'], $packageCode, 'active', [
                'subscription_id' => (int) ($row['subscription_id'] ?? 0),
                'billing_cycle' => 'monthly',
                'provider_order_code' => 'legacy-migration-' . (int) $row['tenant_id'],
                'starts_at' => $startsAt,
                'ends_at' => null,
                'notes' => 'Backfilled from legacy DMS entitlement shim.',
            ]);

            if (empty($result['success'])) {
                $results['failed']++;
                $results['rows'][] = $row + [
                    'action' => 'failed',
                    'message' => (string) ($result['message'] ?? 'Unknown legacy backfill failure.'),
                    'package_code' => $packageCode,
                ];
                continue;
            }

            $this->save_tenant_module_override((int) $row['tenant_id'], 'kt_integration_hub', 'enable');
            $this->sync_tenant_module_registry((int) $row['tenant_id'], $tenant ?: []);
            $this->log_activity('marketplace.salesflow_dms_legacy_backfilled', 'warning', [
                'tenant_id' => (int) $row['tenant_id'],
                'package_code' => $packageCode,
                'source' => 'legacy_migration',
            ], (int) $row['tenant_id']);

            $results['created']++;
            $results['rows'][] = $row + [
                'action' => 'created',
                'message' => 'Created active DMS add-on row from legacy shim.',
                'package_code' => $packageCode,
            ];
        }

        return $results;
    }

    private function canQueryDmsAddonTable(): bool
    {
        try {
            $db = $this->landlord_db();
            if (!is_object($db)) {
                return false;
            }

            if (method_exists($db, 'table_exists')) {
                return $db->table_exists(db_prefix() . 'kt_saas_tenant_addons');
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function resolve_dms_addon_price(array $package, $billingCycle): float
    {
        $billingCycle = strtolower(trim((string) $billingCycle));
        if ($billingCycle === 'quarterly' && array_key_exists('quarterly_price', $package)) {
            $amount = (float) ($package['quarterly_price'] ?? 0);
        } elseif ($billingCycle === 'yearly') {
            $amount = (float) ($package['yearly_price'] ?? 0);
        } else {
            $amount = (float) ($package['monthly_price'] ?? 0);
        }

        return round(max(0, $amount), 2);
    }

    private function resolve_legacy_dms_package_code($tenantId, $entitlementService = null): string
    {
        if ($entitlementService === null) {
            require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
            $entitlementService = new TenantEntitlementService();
        }

        $featureValue = strtolower(trim((string) $entitlementService->getFeatureValue((int) $tenantId, 'dms.package_code', '')));
        if ($featureValue !== '' && !empty($this->get_dms_addon_package($featureValue))) {
            return $featureValue;
        }

        return 'dms_standard';
    }

    public function get_plan_feature_value_map($planId, $featureKeys = [])
    {
        $planId = (int) $planId;
        if ($planId <= 0 || !$this->hasPlanFeatureValueColumn()) {
            return [];
        }

        $featureKeys = array_values(array_filter(array_map('strval', is_array($featureKeys) ? $featureKeys : [])));
        if (empty($featureKeys)) {
            return [];
        }

        $rows = $this->landlord_db()
            ->select('feature_key, feature_value')
            ->from(db_prefix() . 'kt_saas_plan_features')
            ->where('plan_id', $planId)
            ->where_in('feature_key', $featureKeys)
            ->get()
            ->result_array();

        $values = [];
        foreach ($rows as $row) {
            $featureKey = (string) ($row['feature_key'] ?? '');
            if ($featureKey === '') {
                continue;
            }

            $values[$featureKey] = $row['feature_value'];
        }

        return $values;
    }

    protected function hasPlanFeatureValueColumn()
    {
        if ($this->planFeatureValueColumnExists !== null) {
            return $this->planFeatureValueColumnExists;
        }

        $db = $this->landlord_db();
        $planFeaturesTable = db_prefix() . 'kt_saas_plan_features';

        if (!is_object($db)) {
            $this->planFeatureValueColumnExists = false;
            return false;
        }

        if (!method_exists($db, 'table_exists') || !method_exists($db, 'field_exists')) {
            $this->planFeatureValueColumnExists = true;
            return true;
        }

        $this->planFeatureValueColumnExists = $db->table_exists($planFeaturesTable)
            && $db->field_exists('feature_value', $planFeaturesTable);

        return $this->planFeatureValueColumnExists;
    }

    public function get_plan_workspace_feature_keys($planId)
    {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return [];
        }

        $catalog = $this->get_workspace_feature_catalog();
        if (empty($catalog)) {
            return [];
        }

        $rows = $this->landlord_db()
            ->select('feature_key')
            ->from(db_prefix() . 'kt_saas_plan_features')
            ->where('plan_id', $planId)
            ->where_in('feature_key', array_keys($catalog))
            ->where('is_enabled', 1)
            ->get()
            ->result_array();

        return array_values(array_unique(array_map(static function ($row) {
            return (string) ($row['feature_key'] ?? '');
        }, $rows)));
    }

    public function get_plan_integration_feature_keys($planId)
    {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return [];
        }

        $catalog = $this->get_integration_feature_catalog();
        if (empty($catalog)) {
            return [];
        }

        $rows = $this->landlord_db()
            ->select('feature_key')
            ->from(db_prefix() . 'kt_saas_plan_features')
            ->where('plan_id', $planId)
            ->where_in('feature_key', array_keys($catalog))
            ->where('is_enabled', 1)
            ->get()
            ->result_array();

        return array_values(array_unique(array_map(static function ($row) {
            return (string) ($row['feature_key'] ?? '');
        }, $rows)));
    }

    public function get_tenant_billing_invoices($tenantId, $limit = 50)
    {
        return $this->landlord_db()
            ->select('i.*, s.status as subscription_status, p.plan_name')
            ->from(db_prefix() . 'kt_saas_invoices i')
            ->join(db_prefix() . 'kt_saas_subscriptions s', 's.id = i.subscription_id', 'left')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('i.tenant_id', (int) $tenantId)
            ->where('i.deleted_at IS NULL', null, false)
            ->order_by('i.id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function get_tenant_billing_payments($tenantId, $limit = 50)
    {
        return $this->landlord_db()
            ->select('p.*, i.invoice_number')
            ->from(db_prefix() . 'kt_saas_payments p')
            ->join(db_prefix() . 'kt_saas_invoices i', 'i.id = p.invoice_id', 'left')
            ->where('p.tenant_id', (int) $tenantId)
            ->where('p.deleted_at IS NULL', null, false)
            ->order_by('p.id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function get_tenant_usage_history($tenantId, $limit = 60)
    {
        return $this->landlord_db()
            ->select('tenant_id, module_name, metric_key, used_value as metric_value, DATE(updated_at) as snapshot_date, period_start, period_end, updated_at', false)
            ->where('tenant_id', (int) $tenantId)
            ->order_by('updated_at', 'desc')
            ->order_by('metric_key', 'asc')
            ->limit((int) $limit)
            ->get(db_prefix() . 'kt_saas_usage')
            ->result_array();
    }

    public function get_latest_tenant_usage_snapshot($tenantId)
    {
        $snapshotDateRow = $this->landlord_db()
            ->select('MAX(DATE(updated_at)) as snapshot_date', false)
            ->where('tenant_id', (int) $tenantId)
            ->get(db_prefix() . 'kt_saas_usage')
            ->row_array();

        $snapshotDate = $snapshotDateRow['snapshot_date'] ?? null;
        if (empty($snapshotDate)) {
            return ['snapshot_date' => null, 'metrics' => []];
        }

        $rows = $this->landlord_db()
            ->select('metric_key, used_value as metric_value, DATE(updated_at) as snapshot_date', false)
            ->where('tenant_id', (int) $tenantId)
            ->where('updated_at >=', $snapshotDate . ' 00:00:00')
            ->where('updated_at <=', $snapshotDate . ' 23:59:59')
            ->get(db_prefix() . 'kt_saas_usage')
            ->result_array();

        $metrics = [];
        foreach ($rows as $row) {
            $metrics[$row['metric_key']] = (float) $row['metric_value'];
        }

        return [
            'snapshot_date' => $snapshotDate,
            'metrics'       => $metrics,
        ];
    }

    public function sync_tenant_dms_usage_snapshot($tenantId, array $options = []): array
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return ['metrics' => [], 'limits' => [], 'package' => [], 'renewal_date' => null];
        }

        $addonContext = is_array($options['addon_context'] ?? null)
            ? $options['addon_context']
            : $this->resolve_tenant_dms_addon_context($tenantId);
        $package = is_array($addonContext['package'] ?? null) ? $addonContext['package'] : [];

        $limits = [
            'dms_user' => max(0, (int) ($package['user_limit'] ?? 0)),
            'dms_store' => max(0, (int) ($package['store_limit'] ?? 0)),
            'dms_product' => max(0, (int) ($package['product_limit'] ?? 0)),
            'dms_checkin' => max(0, (int) ($package['checkin_monthly_limit'] ?? 0)),
            'dms_order' => max(0, (int) ($package['order_monthly_limit'] ?? 0)),
            'dms_photo' => max(0, (int) ($package['photo_limit'] ?? 0)),
            'dms_export' => max(0, (int) ($package['report_export_limit'] ?? 0)),
        ];

        $metrics = [
            'dms_user' => $this->count_dms_entity_links($tenantId, 'staff'),
            'dms_store' => $this->count_dms_entity_links($tenantId, 'customer'),
            'dms_product' => $this->count_dms_entity_links($tenantId, 'product'),
            'dms_checkin' => $this->count_tenant_dms_checkins_this_month($tenantId),
            'dms_order' => $this->count_tenant_dms_orders_this_month($tenantId),
            'dms_photo' => $this->current_usage_metric_value($tenantId, 'dms_photo'),
            'dms_export' => $this->current_usage_metric_value($tenantId, 'dms_export'),
        ];

        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');
        foreach ($metrics as $metricKey => $usedValue) {
            $periodStart = null;
            $periodEnd = null;
            if (in_array($metricKey, ['dms_checkin', 'dms_order', 'dms_photo', 'dms_export'], true)) {
                $periodStart = $monthStart;
                $periodEnd = $monthEnd;
            }

            $this->upsert_usage_metric_snapshot(
                $tenantId,
                'dms',
                $metricKey,
                (float) $usedValue,
                (float) ($limits[$metricKey] ?? 0),
                $periodStart,
                $periodEnd
            );
        }

        return [
            'metrics' => $metrics,
            'limits' => $limits,
            'package' => $package,
            'renewal_date' => !empty($addonContext['addon']['ends_at']) ? (string) $addonContext['addon']['ends_at'] : null,
        ];
    }

    public function get_tenant_dms_usage_summary($tenantId, array $options = []): array
    {
        $snapshot = $this->sync_tenant_dms_usage_snapshot((int) $tenantId, $options);
        $metrics = (array) ($snapshot['metrics'] ?? []);
        $limits = (array) ($snapshot['limits'] ?? []);
        $package = (array) ($snapshot['package'] ?? []);

        $rows = [];
        foreach ([
            'dms_user' => 'Users Used',
            'dms_store' => 'Stores Used',
            'dms_product' => 'Products Used',
            'dms_checkin' => 'Check-ins Used',
            'dms_order' => 'Orders Used',
            'dms_photo' => 'Photos Used',
            'dms_export' => 'Report Exports Used',
        ] as $metricKey => $label) {
            $used = (int) ($metrics[$metricKey] ?? 0);
            $limit = (int) ($limits[$metricKey] ?? 0);
            $rows[] = [
                'metric_key' => $metricKey,
                'label' => $label,
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
            ];
        }

        return [
            'current_package' => (string) ($package['name'] ?? 'Not active'),
            'renewal_date' => $snapshot['renewal_date'] ?? null,
            'rows' => $rows,
            'metrics' => $metrics,
            'limits' => $limits,
        ];
    }

    private function count_dms_entity_links($tenantId, $entityType)
    {
        $table = db_prefix() . 'kt_integration_entity_links';
        if (!$this->landlord_db()->table_exists($table)) {
            return 0;
        }

        $row = $this->landlord_db()
            ->select('COUNT(DISTINCT local_id) as total', false)
            ->where('tenant_id', (int) $tenantId)
            ->where('provider_code', 'salesflow_dms')
            ->where('entity_type', trim((string) $entityType))
            ->get($table)
            ->row_array();

        return (int) ($row['total'] ?? 0);
    }

    private function count_tenant_dms_checkins_this_month($tenantId)
    {
        $table = db_prefix() . 'dms_checkin_events';
        if (!$this->landlord_db()->table_exists($table)) {
            return 0;
        }

        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        return (int) $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('checkin_at >=', $monthStart)
            ->where('checkin_at <=', $monthEnd)
            ->count_all_results($table);
    }

    private function count_tenant_dms_orders_this_month($tenantId)
    {
        $db = $this->landlord_db();
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        $projectionTable = db_prefix() . 'dms_order_projection';
        if ($db->table_exists($projectionTable)) {
            return (int) $db
                ->where('tenant_id', (int) $tenantId)
                ->where('COALESCE(submitted_at, synced_at, created_at) >= ' . $db->escape($monthStart), null, false)
                ->where('COALESCE(submitted_at, synced_at, created_at) <= ' . $db->escape($monthEnd), null, false)
                ->count_all_results($projectionTable);
        }

        $bridgeTable = db_prefix() . 'dms_order_bridge';
        if (!$db->table_exists($bridgeTable)) {
            return 0;
        }

        return (int) $db
            ->where('tenant_id', (int) $tenantId)
            ->where('created_at >=', $monthStart)
            ->where('created_at <=', $monthEnd)
            ->count_all_results($bridgeTable);
    }

    private function current_usage_metric_value($tenantId, $metricKey)
    {
        $row = $this->landlord_db()
            ->select('used_value')
            ->where('tenant_id', (int) $tenantId)
            ->where('module_name', 'dms')
            ->where('metric_key', trim((string) $metricKey))
            ->get(db_prefix() . 'kt_saas_usage')
            ->row_array();

        return isset($row['used_value']) ? (float) $row['used_value'] : 0.0;
    }

    private function upsert_usage_metric_snapshot($tenantId, $moduleName, $metricKey, $usedValue, $limitValue = 0.0, $periodStart = null, $periodEnd = null)
    {
        $db = $this->landlord_db();
        $now = date('Y-m-d H:i:s');
        $existing = $db
            ->where('tenant_id', (int) $tenantId)
            ->where('module_name', trim((string) $moduleName))
            ->where('metric_key', trim((string) $metricKey))
            ->get(db_prefix() . 'kt_saas_usage')
            ->row_array();

        $record = [
            'tenant_id' => (int) $tenantId,
            'module_name' => trim((string) $moduleName),
            'metric_key' => trim((string) $metricKey),
            'used_value' => round((float) $usedValue, 2),
            'limit_value' => round((float) $limitValue, 2),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'updated_at' => $now,
        ];

        if ($existing) {
            $db->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_usage', $record);
            return true;
        }

        return $db->insert(db_prefix() . 'kt_saas_usage', $record);
    }

    public function find_open_tenant_invoice_by_reason($tenantId, $subscriptionId, $reason, $targetPlanId = null)
    {
        $rows = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('subscription_id', (int) $subscriptionId)
            ->where('deleted_at IS NULL', null, false)
            ->where_in('status', ['draft', 'issued', 'pending_payment', 'overdue'])
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_invoices')
            ->result_array();

        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $payloadReason = (string) ($payload['reason'] ?? ($payload['context']['reason'] ?? 'subscription_renewal'));
            if ($payloadReason !== (string) $reason) {
                continue;
            }

            if ($targetPlanId !== null) {
                $payloadTargetPlanId = (int) ($payload['target_plan_id'] ?? 0);
                if ($payloadTargetPlanId !== (int) $targetPlanId) {
                    continue;
                }
            }

            return $row;
        }

        return null;
    }

    public function get_open_tenant_plan_change_requests($tenantId, $subscriptionId)
    {
        $rows = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('subscription_id', (int) $subscriptionId)
            ->where('deleted_at IS NULL', null, false)
            ->where_in('status', ['draft', 'issued', 'pending_payment', 'overdue'])
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_invoices')
            ->result_array();

        $requests = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }

            $reason = (string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''));
            if ($reason !== 'plan_change_request') {
                continue;
            }

            $targetPlanId = (int) ($payload['target_plan_id'] ?? 0);
            if ($targetPlanId <= 0) {
                continue;
            }

            $requests[$targetPlanId] = $row;
        }

        return $requests;
    }

    public function find_tenant_invoice_by_reason_period($tenantId, $subscriptionId, $reason, $period)
    {
        $rows = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('subscription_id', (int) $subscriptionId)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_invoices')
            ->result_array();

        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }

            if ((string) ($payload['reason'] ?? '') !== (string) $reason) {
                continue;
            }

            if ((string) ($payload['overage_period'] ?? '') !== (string) $period) {
                continue;
            }

            return $row;
        }

        return null;
    }

    public function get_dashboard_summary()
    {
        return [
            'tenants'          => $this->safe_count('kt_saas_tenants'),
            'plans'            => $this->safe_count('kt_saas_plans'),
            'subscriptions'    => $this->safe_count('kt_saas_subscriptions'),
            'provision_jobs'   => $this->safe_count('kt_saas_provision_jobs'),
            'domains'          => $this->safe_count('kt_saas_domains'),
            'usage_snapshots'  => $this->safe_count('kt_saas_usage'),
            'billing_invoices' => $this->safe_count('kt_saas_invoices'),
        ];
    }

    public function get_landlord_dashboard_kpis()
    {
        $db = $this->landlord_db();
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        $activeTenants = (int) $db
            ->where('status', 'active')
            ->where('deleted_at IS NULL', null, false)
            ->count_all_results(db_prefix() . 'kt_saas_tenants');

        $subscriptions = $db
            ->select('s.billing_cycle, p.price, p.billing_cycle as plan_billing_cycle')
            ->from(db_prefix() . 'kt_saas_subscriptions s')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('s.status', 'active')
            ->where('s.deleted_at IS NULL', null, false)
            ->get()
            ->result_array();

        $mrr = 0.0;
        foreach ($subscriptions as $subscription) {
            $price = max(0.0, (float) ($subscription['price'] ?? 0));
            if ($price <= 0) {
                continue;
            }
            $cycle = strtolower((string) ($subscription['billing_cycle'] ?: ($subscription['plan_billing_cycle'] ?? 'monthly')));
            if (in_array($cycle, ['yearly', 'annual', 'annually'], true)) {
                $mrr += $price / 12;
            } elseif (!in_array($cycle, ['trial', 'free'], true)) {
                $mrr += $price;
            }
        }

        $revenueThisMonth = (float) $db
            ->select_sum('amount', 'total')
            ->where('status', 'paid')
            ->where('deleted_at IS NULL', null, false)
            ->where('paid_at >=', $monthStart)
            ->where('paid_at <=', $monthEnd)
            ->get(db_prefix() . 'kt_saas_payments')
            ->row('total');

        if ($db->table_exists(db_prefix() . 'kt_saas_orders')) {
            $addonRevenue = (float) $db
                ->select_sum('grand_total', 'total')
                ->where('payment_status', 'paid')
                ->where('paid_at >=', $monthStart)
                ->where('paid_at <=', $monthEnd)
                ->get(db_prefix() . 'kt_saas_orders')
                ->row('total');
            $revenueThisMonth += $addonRevenue;
        }

        return [
            'active_tenants' => $activeTenants,
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'revenue_this_month' => $revenueThisMonth,
            'currency' => get_base_currency()->name ?? 'VND',
        ];
    }

    public function get_landlord_customer_status_summary()
    {
        $db = $this->landlord_db();
        $today = date('Y-m-d H:i:s');
        $soon = date('Y-m-d H:i:s', strtotime('+7 days'));

        return [
            'trial' => (int) $db->where('status', 'trial')->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_subscriptions'),
            'active' => (int) $db->where('status', 'active')->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_tenants'),
            'expiring_soon' => (int) $db
                ->where('status', 'active')
                ->where('deleted_at IS NULL', null, false)
                ->where('current_period_end_at IS NOT NULL', null, false)
                ->where('current_period_end_at >=', $today)
                ->where('current_period_end_at <=', $soon)
                ->count_all_results(db_prefix() . 'kt_saas_subscriptions'),
            'overdue' => (int) $db
                ->where_in('status', ['issued', 'pending_payment', 'partial', 'overdue'])
                ->where('deleted_at IS NULL', null, false)
                ->where('due_date IS NOT NULL', null, false)
                ->where('due_date <', date('Y-m-d'))
                ->count_all_results(db_prefix() . 'kt_saas_invoices'),
            'suspended' => (int) $db->where('status', 'suspended')->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_tenants'),
        ];
    }

    public function get_landlord_billing_health()
    {
        $db = $this->landlord_db();
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        $pendingInvoices = (int) $db
            ->where_in('status', ['issued', 'pending_payment', 'partial'])
            ->where('deleted_at IS NULL', null, false)
            ->count_all_results(db_prefix() . 'kt_saas_invoices');

        $overdueInvoices = (int) $db
            ->where_in('status', ['issued', 'pending_payment', 'partial', 'overdue'])
            ->where('deleted_at IS NULL', null, false)
            ->where('due_date IS NOT NULL', null, false)
            ->where('due_date <', date('Y-m-d'))
            ->count_all_results(db_prefix() . 'kt_saas_invoices');

        $paidThisMonth = (int) $db
            ->where('status', 'paid')
            ->where('deleted_at IS NULL', null, false)
            ->where('paid_at >=', $monthStart)
            ->where('paid_at <=', $monthEnd)
            ->count_all_results(db_prefix() . 'kt_saas_payments');

        $paymentIssues = 0;
        if ($db->table_exists(db_prefix() . 'kt_sepay_payment_requests')) {
            $paymentIssues += (int) $db
                ->where_in('status', ['failed', 'expired', 'cancelled'])
                ->count_all_results(db_prefix() . 'kt_sepay_payment_requests');
        }
        if ($db->table_exists(db_prefix() . 'kt_sepay_transactions')) {
            $paymentIssues += (int) $db
                ->where_in('status', ['unmatched', 'failed'])
                ->count_all_results(db_prefix() . 'kt_sepay_transactions');
        }

        return [
            'pending_invoices' => $pendingInvoices,
            'overdue_invoices' => $overdueInvoices,
            'paid_this_month' => $paidThisMonth,
            'payment_issues' => $paymentIssues,
        ];
    }

    public function get_landlord_operations_alerts()
    {
        $db = $this->landlord_db();
        $provision = $this->get_provisioning_alerts();
        $overageRows = $this->get_overage_dashboard_rows();

        $domainIssues = (int) $db
            ->where('deleted_at IS NULL', null, false)
            ->group_start()
                ->where_in('readiness_status', ['pending', 'failed', 'error'])
                ->or_where_in('dns_status', ['pending', 'failed', 'error'])
            ->group_end()
            ->count_all_results(db_prefix() . 'kt_saas_domains');

        $paymentUnmatched = 0;
        if ($db->table_exists(db_prefix() . 'kt_sepay_transactions')) {
            $paymentUnmatched = (int) $db
                ->where_in('status', ['unmatched', 'failed'])
                ->count_all_results(db_prefix() . 'kt_sepay_transactions');
        }

        $alerts = [];
        if ((int) ($provision['failed_jobs'] ?? 0) > 0) {
            $alerts[] = ['label' => 'Provisioning lỗi', 'count' => (int) $provision['failed_jobs'], 'level' => 'danger', 'url' => admin_url('kt_saas/provision_jobs')];
        }
        $queued = (int) ($provision['queued_backlog'] ?? 0);
        if ($queued > 0) {
            $alerts[] = ['label' => 'Provisioning đang chờ quá lâu', 'count' => $queued, 'level' => 'warning', 'url' => admin_url('kt_saas/provision_jobs')];
        }
        if ($domainIssues > 0) {
            $alerts[] = ['label' => 'Tên miền chưa sẵn sàng', 'count' => $domainIssues, 'level' => 'warning', 'url' => admin_url('kt_saas/domains')];
        }
        if ($paymentUnmatched > 0) {
            $alerts[] = ['label' => 'Thanh toán chưa khớp', 'count' => $paymentUnmatched, 'level' => 'danger', 'url' => admin_url('kt_sepay/transactions')];
        }
        if (count($overageRows) > 0) {
            $alerts[] = ['label' => 'Doanh nghiệp vượt giới hạn', 'count' => count($overageRows), 'level' => 'warning', 'url' => admin_url('kt_saas/tenant_usage')];
        }

        return [
            'items' => $alerts,
            'overage_rows' => $overageRows,
            'provision' => $provision,
        ];
    }

    public function get_landlord_recent_business_activity($limit = 10)
    {
        $rows = $this->landlord_db()
            ->select('l.*, t.company_name, t.tenant_code')
            ->from(db_prefix() . 'kt_saas_activity_logs l')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = l.tenant_id', 'left')
            ->where_not_in('l.event_key', ['usage.retention_cleanup', 'plan.features_rehydrated', 'activity_logs.purged'])
            ->order_by('l.id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->result_array();

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'created_at' => $row['created_at'],
                'severity' => $row['severity'],
                'tenant' => trim((string) (($row['company_name'] ?? '') ?: ($row['tenant_code'] ?? ''))),
                'message' => $this->businessActivityMessage($row),
            ];
        }

        return $mapped;
    }

    public function get_signup_funnel_overview($days = 7)
    {
        $days = max(1, (int) $days);
        $from = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $db = $this->landlord_db();

        $tenantRows = $db
            ->select('id,status,provisioning_status')
            ->from(db_prefix() . 'kt_saas_tenants')
            ->where('deleted_at IS NULL', null, false)
            ->where('created_at >=', $from)
            ->get()
            ->result_array();

        $tenantIds = array_map('intval', array_column($tenantRows, 'id'));
        $invoiceRows = [];
        if (!empty($tenantIds)) {
            $invoiceRows = $db
                ->select('tenant_id,status,payload_json')
                ->from(db_prefix() . 'kt_saas_invoices')
                ->where('deleted_at IS NULL', null, false)
                ->where_in('tenant_id', $tenantIds)
                ->get()
                ->result_array();
        }

        $publicSignupInvoiceByTenant = [];
        $paidPublicSignupByTenant = [];
        foreach ($invoiceRows as $invoice) {
            $payload = json_decode((string) ($invoice['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $reason = (string) ($payload['reason'] ?? ($payload['context']['reason'] ?? ''));
            if ($reason !== 'public_signup') {
                continue;
            }
            $tenantId = (int) ($invoice['tenant_id'] ?? 0);
            if ($tenantId <= 0) {
                continue;
            }
            $publicSignupInvoiceByTenant[$tenantId] = true;
            if ((string) ($invoice['status'] ?? '') === 'paid') {
                $paidPublicSignupByTenant[$tenantId] = true;
            }
        }

        $result = [
            'window_days'             => $days,
            'signup_created'          => count($tenantRows),
            'signup_draft'            => 0,
            'invoice_public_signup'   => count($publicSignupInvoiceByTenant),
            'paid_public_signup'      => count($paidPublicSignupByTenant),
            'provisioning_in_queue'   => 0,
            'provisioning_done'       => 0,
            'tenant_active'           => 0,
        ];

        foreach ($tenantRows as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            $status = (string) ($tenant['status'] ?? '');
            $provisioning = (string) ($tenant['provisioning_status'] ?? '');

            if ($status === 'draft') {
                $result['signup_draft']++;
            }

            if (!isset($paidPublicSignupByTenant[$tenantId])) {
                continue;
            }

            if (in_array($provisioning, ['queued', 'running'], true)) {
                $result['provisioning_in_queue']++;
            }
            if ($provisioning === 'done') {
                $result['provisioning_done']++;
            }
            if ($status === 'active' && $provisioning === 'done') {
                $result['tenant_active']++;
            }
        }

        return $result;
    }

    public function get_provisioning_alerts()
    {
        $now = date('Y-m-d H:i:s');
        $runningThreshold = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $queuedThreshold = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        $db = $this->landlord_db();

        $failedJobs = (int) $db
            ->where('status', 'failed')
            ->count_all_results(db_prefix() . 'kt_saas_provision_jobs');

        $staleRunningJobs = (int) $db
            ->where('status', 'running')
            ->where('started_at IS NOT NULL', null, false)
            ->where('started_at <', $runningThreshold)
            ->count_all_results(db_prefix() . 'kt_saas_provision_jobs');

        $queuedBacklog = (int) $db
            ->where('status', 'queued')
            ->group_start()
                ->where('scheduled_at IS NULL', null, false)
                ->or_where('scheduled_at <', $queuedThreshold)
            ->group_end()
            ->count_all_results(db_prefix() . 'kt_saas_provision_jobs');

        $retryHotspots = $db
            ->select('j.id,j.tenant_id,j.job_type,j.status,j.attempts,j.max_attempts,j.updated_at,j.error_message,t.tenant_code,t.company_name')
            ->from(db_prefix() . 'kt_saas_provision_jobs j')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = j.tenant_id', 'left')
            ->where('j.status', 'failed')
            ->where('j.attempts >=', 3)
            ->order_by('j.updated_at', 'desc')
            ->limit(5)
            ->get()
            ->result_array();

        return [
            'generated_at'       => $now,
            'failed_jobs'        => $failedJobs,
            'stale_running_jobs' => $staleRunningJobs,
            'queued_backlog'     => $queuedBacklog,
            'retry_hotspots'     => $retryHotspots,
        ];
    }

    public function get_billing_dashboard_overview()
    {
        $db = $this->landlord_db();
        $invoiceRows = $db
            ->select('status, COUNT(*) as total_count, SUM(grand_total) as total_amount')
            ->from(db_prefix() . 'kt_saas_invoices')
            ->where('deleted_at IS NULL', null, false)
            ->group_by('status')
            ->get()
            ->result_array();

        $paymentRows = $db
            ->select('status, COUNT(*) as total_count, SUM(amount) as total_amount')
            ->from(db_prefix() . 'kt_saas_payments')
            ->where('deleted_at IS NULL', null, false)
            ->group_by('status')
            ->get()
            ->result_array();

        $overview = [
            'invoices' => [
                'pending_payment' => ['count' => 0, 'amount' => 0.0],
                'overdue'         => ['count' => 0, 'amount' => 0.0],
                'paid'            => ['count' => 0, 'amount' => 0.0],
            ],
            'payments' => [
                'pending' => ['count' => 0, 'amount' => 0.0],
                'paid'    => ['count' => 0, 'amount' => 0.0],
                'failed'  => ['count' => 0, 'amount' => 0.0],
            ],
        ];

        foreach ($invoiceRows as $row) {
            $status = (string) $row['status'];
            if (!isset($overview['invoices'][$status])) {
                $overview['invoices'][$status] = ['count' => 0, 'amount' => 0.0];
            }
            $overview['invoices'][$status] = [
                'count'  => (int) $row['total_count'],
                'amount' => (float) $row['total_amount'],
            ];
        }

        foreach ($paymentRows as $row) {
            $status = (string) $row['status'];
            if (!isset($overview['payments'][$status])) {
                $overview['payments'][$status] = ['count' => 0, 'amount' => 0.0];
            }
            $overview['payments'][$status] = [
                'count'  => (int) $row['total_count'],
                'amount' => (float) $row['total_amount'],
            ];
        }

        return $overview;
    }

    public function get_recent_activity_logs($limit = 15)
    {
        return $this->db
            ->order_by('id', 'desc')
            ->limit((int) $limit)
            ->get(db_prefix() . 'kt_saas_activity_logs')
            ->result_array();
    }

    public function get_activity_logs_paginated(array $filters = [], $limit = 50, $offset = 0)
    {
        $db = $this->activityLogQuery($filters);
        return $db
            ->order_by('id', 'desc')
            ->limit(max(1, (int) $limit), max(0, (int) $offset))
            ->get(db_prefix() . 'kt_saas_activity_logs')
            ->result_array();
    }

    public function count_activity_logs(array $filters = [])
    {
        return (int) $this->activityLogQuery($filters)->count_all_results(db_prefix() . 'kt_saas_activity_logs');
    }

    public function purge_activity_logs(array $options = [])
    {
        $mode = (string) ($options['mode'] ?? 'older_than');
        $days = max(1, (int) ($options['days'] ?? 30));
        $confirm = trim((string) ($options['confirm'] ?? ''));
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_saas_activity_logs';

        if (!$db->table_exists($table)) {
            return ['success' => false, 'message' => 'Activity log table not found.'];
        }

        if ($mode === 'all') {
            if ($confirm !== 'DELETE ALL ACTIVITY LOGS') {
                return ['success' => false, 'message' => 'Confirmation does not match.'];
            }
            $count = (int) $db->count_all_results($table);
            if ($count > 0) {
                $db->empty_table($table);
            }
        } else {
            if ($confirm !== 'DELETE ACTIVITY LOGS') {
                return ['success' => false, 'message' => 'Confirmation does not match.'];
            }
            $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
            $count = (int) $db->where('created_at <', $cutoff)->count_all_results($table);
            if ($count > 0) {
                $db->where('created_at <', $cutoff)->delete($table);
            }
        }

        $this->log_activity('activity_logs.purged', 'warning', [
            'mode' => $mode,
            'days' => $mode === 'all' ? null : $days,
            'deleted' => $count,
        ]);

        return ['success' => true, 'deleted' => $count];
    }

    public function get_tenant_activity_logs($tenantId, array $filters = [], $limit = 100)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }

        $db = $this->landlord_db();
        $db->where('tenant_id', $tenantId);

        $eventKey = trim((string) ($filters['event_key'] ?? ''));
        if ($eventKey !== '') {
            $db->where('event_key', $eventKey);
        }

        $severity = trim((string) ($filters['severity'] ?? ''));
        if ($severity !== '') {
            $db->where('severity', $severity);
        }

        return $db
            ->order_by('id', 'desc')
            ->limit((int) $limit)
            ->get(db_prefix() . 'kt_saas_activity_logs')
            ->result_array();
    }

    public function get_usage_dashboard_overview()
    {
        $today = date('Y-m-d');
        $metrics = ['staff', 'clients', 'projects', 'invoices', 'warehouses', 'storage_mb'];
        $rows = $this->db
            ->select('metric_key, SUM(used_value) as total_value, COUNT(DISTINCT tenant_id) as tenant_count')
            ->from(db_prefix() . 'kt_saas_usage')
            ->where('updated_at >=', $today . ' 00:00:00')
            ->where('updated_at <=', $today . ' 23:59:59')
            ->where_in('metric_key', $metrics)
            ->group_by('metric_key')
            ->get()
            ->result_array();

        $overview = [];
        foreach ($metrics as $metric) {
            $overview[$metric] = [
                'metric_key'   => $metric,
                'total_value'  => 0,
                'tenant_count' => 0,
            ];
        }

        foreach ($rows as $row) {
            $overview[$row['metric_key']] = [
                'metric_key'   => $row['metric_key'],
                'total_value'  => (float) $row['total_value'],
                'tenant_count' => (int) $row['tenant_count'],
            ];
        }

        return $overview;
    }

    public function get_latest_usage_snapshots($limit = 50)
    {
        $subquery = '(SELECT tenant_id, metric_key, MAX(updated_at) as latest_updated_at FROM ' . db_prefix() . 'kt_saas_usage GROUP BY tenant_id, metric_key) latest';

        return $this->db
            ->select('u.*, u.used_value as metric_value, DATE(u.updated_at) as snapshot_date, t.tenant_code, t.company_name', false)
            ->from(db_prefix() . 'kt_saas_usage u')
            ->join($subquery, 'latest.tenant_id = u.tenant_id AND latest.metric_key = u.metric_key AND latest.latest_updated_at = u.updated_at', 'inner')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = u.tenant_id', 'left')
            ->order_by('u.updated_at', 'desc')
            ->order_by('u.tenant_id', 'asc')
            ->order_by('u.metric_key', 'asc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function get_overage_dashboard_rows()
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/UsageSnapshotRunner.php');
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');

        $runner = new UsageSnapshotRunner();
        $tenants = $runner->listEligibleTenants(200);

        $rows = [];
        foreach ($tenants as $tenant) {
            $latestSnapshot = $this->get_latest_tenant_usage_snapshot((int) $tenant['id']);
            if (empty($latestSnapshot['metrics'])) {
                continue;
            }

            $entitlements = new TenantEntitlementService();
            $profile = $entitlements->getRuntimeProfile($tenant);
            $overages = $entitlements->buildOverageSummary($tenant, $profile, $latestSnapshot['metrics']);
            if (empty($overages)) {
                continue;
            }

            foreach ($overages as $overage) {
                $rows[] = [
                    'tenant_id'      => (int) $tenant['id'],
                    'tenant_code'    => $tenant['tenant_code'],
                    'company_name'   => $tenant['company_name'],
                    'plan_name'      => $tenant['plan_name'] ?? '',
                    'metric_key'     => $overage['metric_key'],
                    'current_value'  => $overage['current_value'],
                    'limit_value'    => $overage['limit_value'],
                    'excess_value'   => $overage['excess_value'],
                ];
            }
        }

        return $rows;
    }

    public function count_usage_rows_older_than_retention()
    {
        $days = max((int) kt_saas_get_option('kt_saas_usage_retention_days', '90'), 7);
        $cutoffDate = date('Y-m-d', strtotime('-' . $days . ' days'));

        return (int) $this->db
            ->where('updated_at <', $cutoffDate . ' 00:00:00')
            ->count_all_results(db_prefix() . 'kt_saas_usage');
    }

    public function get_runtime_overview()
    {
        $manifestPath = module_dir_path(KT_SAAS_MODULE, 'tenant_bootstrap/manifests/');
        $manifestCount = 0;
        if (is_dir($manifestPath)) {
            $files = glob($manifestPath . '*.json');
            $manifestCount = is_array($files) ? count($files) : 0;
        }

        return [
            'runtime_enabled' => kt_saas_get_option('kt_saas_runtime_enabled', '0'),
            'landlord_host'   => kt_saas_get_option('kt_saas_landlord_host', parse_url(APP_BASE_URL, PHP_URL_HOST)),
            'base_domain'     => kt_saas_get_option('kt_saas_base_domain', 'crm.local'),
            'queue_mode'      => kt_saas_get_option('kt_saas_queue_mode', 'database'),
            'usage_retention_days' => kt_saas_get_option('kt_saas_usage_retention_days', '90'),
            'manifests'       => $manifestCount,
        ];
    }

    public function get_landlord_table_status()
    {
        $db = $this->landlord_db();
        $tables = [
            'kt_saas_tenants',
            'kt_saas_plans',
            'kt_saas_subscriptions',
            'kt_saas_invoices',
            'kt_saas_payments',
            'kt_saas_domains',
            'kt_saas_module_catalog',
            'kt_saas_modules',
            'kt_saas_plan_features',
            'kt_saas_tenant_entitlements',
            'kt_saas_usage',
            'kt_saas_activity_logs',
            'kt_saas_provision_jobs',
            'kt_saas_backups',
        ];

        $rows = [];
        foreach ($tables as $table) {
            $rows[] = [
                'table'   => db_prefix() . $table,
                'exists'  => $db->table_exists(db_prefix() . $table),
                'records' => $this->safe_count($table),
            ];
        }

        return $rows;
    }

    public function get_tenants($includeDeleted = false)
    {
        $db = $this->landlord_db();
        $db->select('t.*, p.plan_name');
        $db->from(db_prefix() . 'kt_saas_tenants t');
        $db->join(db_prefix() . 'kt_saas_plans p', 'p.id = t.plan_id', 'left');
        if (!$includeDeleted) {
            $db->where('t.deleted_at IS NULL', null, false);
        }
        $db->order_by('t.id', 'desc');

        return $db->get()->result_array();
    }

    public function get_tenant($id)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    public function ensure_landlord_client_contact($tenantId)
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $this->load->model('clients_model');
        $db = $this->landlord_db();
        $now = date('Y-m-d H:i:s');

        $linkedClientId = (int) ($tenant['landlord_client_id'] ?? 0);
        $linkedContactId = (int) ($tenant['landlord_contact_id'] ?? 0);

        $client = $linkedClientId > 0 ? $this->clients_model->get($linkedClientId) : null;
        $contact = $linkedContactId > 0 ? $this->clients_model->get_contact($linkedContactId) : null;

        if (!$contact && $client) {
            $contact = $this->find_landlord_contact_for_client((int) $client->userid, (string) ($tenant['owner_email'] ?? ''));
        }

        if ($contact && !$client) {
            $client = $this->clients_model->get((int) ($contact->userid ?? 0));
        }

        if (!$contact && !$client) {
            $contact = $this->find_safe_landlord_contact_by_email((string) ($tenant['owner_email'] ?? ''));
            if ($contact) {
                $client = $this->clients_model->get((int) ($contact->userid ?? 0));
            }
        }

        if (!$client) {
            [$firstName, $lastName] = $this->split_owner_name((string) ($tenant['owner_name'] ?? ''));
            $clientId = (int) $this->clients_model->add([
                'company' => (string) ($tenant['company_name'] ?? ''),
                'phonenumber' => (string) ($tenant['phone'] ?? ''),
                'active' => 1,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => (string) ($tenant['owner_email'] ?? ''),
                'password' => $this->generateStrongPassword(24),
                'is_primary' => 1,
                'donotsendwelcomeemail' => true,
            ], true);

            if ($clientId <= 0) {
                return ['success' => false, 'message' => 'Failed to create landlord client.'];
            }

            $client = $this->clients_model->get($clientId);
            $contact = $this->find_landlord_contact_for_client($clientId, (string) ($tenant['owner_email'] ?? ''));
        } elseif (!$contact) {
            [$firstName, $lastName] = $this->split_owner_name((string) ($tenant['owner_name'] ?? ''));
            $contactId = (int) $this->clients_model->add_contact([
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => (string) ($tenant['owner_email'] ?? ''),
                'phonenumber' => (string) ($tenant['phone'] ?? ''),
                'password' => $this->generateStrongPassword(24),
                'is_primary' => 1,
                'donotsendwelcomeemail' => true,
            ], (int) $client->userid, true);

            if ($contactId <= 0) {
                return ['success' => false, 'message' => 'Failed to create landlord contact.'];
            }

            $contact = $this->clients_model->get_contact($contactId);
        }

        if (!$client || !$contact) {
            return ['success' => false, 'message' => 'Landlord client/contact linkage is incomplete.'];
        }

        $clientId = (int) ($client->userid ?? 0);
        $contactId = (int) ($contact->id ?? 0);
        if ($clientId <= 0 || $contactId <= 0 || (int) ($contact->userid ?? 0) !== $clientId) {
            return ['success' => false, 'message' => 'Linked landlord contact does not belong to the resolved landlord client.'];
        }

        $db->where('userid', $clientId)->update(db_prefix() . 'clients', [
            'company' => (string) ($tenant['company_name'] ?? ''),
            'phonenumber' => (string) ($tenant['phone'] ?? ''),
            'active' => 1,
        ]);

        [$firstName, $lastName] = $this->split_owner_name((string) ($tenant['owner_name'] ?? ''));
        $db->where('id', $contactId)->update(db_prefix() . 'contacts', [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => (string) ($tenant['owner_email'] ?? ''),
            'phonenumber' => (string) ($tenant['phone'] ?? ''),
            'active' => 1,
            'is_primary' => 1,
        ]);

        $shouldSendVerification = empty($contact->email_verified_at);
        if (!$shouldSendVerification && $linkedContactId <= 0) {
            $shouldSendVerification = true;
        }

        if ($shouldSendVerification) {
            $db->where('id', $contactId)->update(db_prefix() . 'contacts', [
                'email_verified_at' => null,
                'email_verification_key' => app_generate_hash(),
                'email_verification_sent_at' => null,
            ]);
        }

        $db->where('id', $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
            'landlord_client_id' => $clientId,
            'landlord_contact_id' => $contactId,
            'updated_at' => $now,
            'updated_by' => get_staff_user_id() ?: null,
        ]);

        $verificationSent = true;
        if ($shouldSendVerification) {
            $verificationSent = (bool) $this->clients_model->send_verification_email($contactId);
            if (!$verificationSent) {
                return [
                    'success' => false,
                    'message' => 'Landlord contact was linked but verification email was not sent.',
                    'landlord_client_id' => $clientId,
                    'landlord_contact_id' => $contactId,
                ];
            }
        }

        return [
            'success' => true,
            'landlord_client_id' => $clientId,
            'landlord_contact_id' => $contactId,
            'verification_email_sent' => $verificationSent,
        ];
    }

    public function get_tenant_for_lifecycle($id)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    public function get_module_catalog()
    {
        return $this->landlord_db()
            ->order_by('category', 'asc')
            ->order_by('display_name', 'asc')
            ->get(db_prefix() . 'kt_saas_module_catalog')
            ->result_array();
    }

    public function sync_module_catalog()
    {
        if (!class_exists('App_modules')) {
            include_once(LIBSPATH . 'App_modules.php');
        }

        $modules = $this->app_modules->get();
        $now = date('Y-m-d H:i:s');
        $touched = 0;

        foreach ($modules as $module) {
            $systemName = (string) ($module['system_name'] ?? '');
            if ($systemName === '' || $systemName === KT_SAAS_MODULE) {
                continue;
            }

            $existing = $this->landlord_db()
                ->where('module_name', $systemName)
                ->get(db_prefix() . 'kt_saas_module_catalog')
                ->row_array();

            $payload = [
                'display_name'     => (string) ($module['headers']['module_name'] ?? $systemName),
                'slug'             => str_replace('_', '-', $systemName),
                'description'      => (string) ($module['headers']['description'] ?? ''),
                'category'         => 'general',
                'version'          => (string) ($module['headers']['version'] ?? '1.0.0'),
                'is_core'          => 0,
                // Keep the SaaS catalog switch stable across syncs instead of mirroring landlord activation.
                'is_global_active' => $existing ? (int) ($existing['is_global_active'] ?? 0) : (!empty($module['activated']) ? 1 : 0),
                'has_ui'           => 1,
                'has_routes'       => 1,
                'has_cron'         => 0,
                'detected_from'    => 'system',
                'synced_at'        => $now,
            ];

            if ($existing) {
                $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_module_catalog', $payload);
            } else {
                $payload['module_name'] = $systemName;
                $payload['created_at'] = $now;
                $this->landlord_db()->insert(db_prefix() . 'kt_saas_module_catalog', $payload);
            }
            $touched++;
        }

        return $touched;
    }

    public function set_module_catalog_status($moduleName, $isActive)
    {
        $moduleName = strtolower(trim((string) $moduleName));
        if ($moduleName === '' || $moduleName === KT_SAAS_MODULE) {
            return ['success' => false, 'message' => 'Module name is invalid.'];
        }

        $existing = $this->landlord_db()
            ->where('module_name', $moduleName)
            ->get(db_prefix() . 'kt_saas_module_catalog')
            ->row_array();

        if (!$existing) {
            return ['success' => false, 'message' => 'Module catalog record not found.'];
        }

        $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_module_catalog', [
            'is_global_active' => $isActive ? 1 : 0,
            'synced_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->sync_all_tenant_module_registries();
        $this->log_activity('module.catalog_updated', 'info', [
            'module_name'      => $moduleName,
            'is_global_active' => $isActive ? 1 : 0,
        ]);

        return ['success' => true];
    }

    public function rebuild_module_registries($planId = null)
    {
        $this->sync_all_tenant_module_registries($planId !== null ? (int) $planId : null);
    }

    public function get_tenant_module_registry($tenantId)
    {
        $tenant = $this->get_tenant((int) $tenantId);
        if (!$tenant) {
            return [];
        }

        $this->sync_tenant_module_registry((int) $tenantId);

        return $this->landlord_db()
            ->select('m.*, c.display_name, c.slug, c.description, c.category, c.version, c.is_global_active, e.is_enabled as override_enabled, e.overridden')
            ->from(db_prefix() . 'kt_saas_modules m')
            ->join(db_prefix() . 'kt_saas_module_catalog c', 'c.module_name = m.module_name', 'left')
            ->join(db_prefix() . 'kt_saas_tenant_entitlements e', 'e.tenant_id = m.tenant_id AND e.module_name = m.module_name AND e.feature_key = CONCAT(m.module_name,\'.access\')', 'left')
            ->where('m.tenant_id', (int) $tenantId)
            ->where('m.deleted_at IS NULL', null, false)
            ->order_by('c.display_name', 'asc')
            ->get()
            ->result_array();
    }

    public function save_tenant_module_override($tenantId, $moduleName, $mode)
    {
        $tenant = $this->get_tenant((int) $tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $moduleName = strtolower(trim((string) $moduleName));
        $mode = strtolower(trim((string) $mode));
        if ($moduleName === '') {
            return ['success' => false, 'message' => 'Module name is required.'];
        }

        $catalog = $this->landlord_db()
            ->where('module_name', $moduleName)
            ->get(db_prefix() . 'kt_saas_module_catalog')
            ->row_array();
        if (!$catalog) {
            return ['success' => false, 'message' => 'Module catalog record not found.'];
        }

        $featureKey = $moduleName . '.access';
        $now = date('Y-m-d H:i:s');

        if ($mode === 'inherit') {
            $this->landlord_db()
                ->where('tenant_id', (int) $tenantId)
                ->where('module_name', $moduleName)
                ->where('feature_key', $featureKey)
                ->delete(db_prefix() . 'kt_saas_tenant_entitlements');
        } else {
            $enabled = $mode === 'enable' ? 1 : 0;
            $existing = $this->landlord_db()
                ->where('tenant_id', (int) $tenantId)
                ->where('module_name', $moduleName)
                ->where('feature_key', $featureKey)
                ->get(db_prefix() . 'kt_saas_tenant_entitlements')
                ->row_array();

            $payload = [
                'tenant_id'      => (int) $tenantId,
                'module_name'    => $moduleName,
                'feature_key'    => $featureKey,
                'is_enabled'     => $enabled,
                'source_plan_id' => (int) ($tenant['plan_id'] ?? 0) ?: null,
                'overridden'     => 1,
                'updated_at'     => $now,
            ];

            if ($existing) {
                $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_tenant_entitlements', $payload);
            } else {
                $payload['created_at'] = $now;
                $this->landlord_db()->insert(db_prefix() . 'kt_saas_tenant_entitlements', $payload);
            }
        }

        $this->sync_tenant_module_registry((int) $tenantId);
        $this->log_activity('tenant.module_override_updated', 'info', [
            'tenant_id'    => (int) $tenantId,
            'module_name'  => $moduleName,
            'override_mode'=> $mode,
        ], (int) $tenantId);

        return ['success' => true];
    }

    public function save_tenant_feature_override($tenantId, $moduleName, $featureKey, $enabled, $overridden = 1)
    {
        $tenant = $this->get_tenant((int) $tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $moduleName = strtolower(trim((string) $moduleName));
        $featureKey = strtolower(trim((string) $featureKey));
        if ($moduleName === '' || $featureKey === '') {
            return ['success' => false, 'message' => 'Module name and feature key are required.'];
        }

        $existing = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('feature_key', $featureKey)
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->row_array();

        $now = date('Y-m-d H:i:s');
        $payload = [
            'tenant_id'      => (int) $tenantId,
            'module_name'    => $moduleName,
            'feature_key'    => $featureKey,
            'is_enabled'     => $enabled ? 1 : 0,
            'source_plan_id' => (int) ($tenant['plan_id'] ?? 0) ?: null,
            'overridden'     => $overridden ? 1 : 0,
            'updated_at'     => $now,
        ];

        if ($existing) {
            $this->landlord_db()->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_tenant_entitlements', $payload);
        } else {
            $payload['created_at'] = $now;
            $this->landlord_db()->insert(db_prefix() . 'kt_saas_tenant_entitlements', $payload);
        }

        return ['success' => true];
    }

    public function install_salesflow_dms_marketplace_app($tenantId, $packageCode = null, array $options = [])
    {
        $tenant = $this->get_tenant((int) $tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $subscription = $this->get_tenant_subscription_profile((int) $tenantId);
        $tenantStatus = strtolower(trim((string) ($tenant['status'] ?? 'draft')));
        $subscriptionStatus = strtolower(trim((string) ($subscription['status'] ?? 'draft')));
        if (in_array($tenantStatus, ['suspended', 'terminated', 'inactive', 'archived'], true) || in_array($subscriptionStatus, ['suspended', 'cancelled', 'terminated', 'inactive'], true)) {
            return ['success' => false, 'message' => 'Tenant subscription is not active enough to install Salesflow DMS.'];
        }

        $requestedPackageCode = strtolower(trim((string) $packageCode));
        if ($requestedPackageCode !== '') {
            $addonResult = $this->upsert_tenant_dms_addon(
                (int) $tenantId,
                $requestedPackageCode,
                'payment_pending',
                [
                    'billing_cycle' => (string) ($options['billing_cycle'] ?? 'monthly'),
                    'notes' => 'Marketplace package selected; waiting for invoice payment before activation.',
                ]
            );

            if (empty($addonResult['success'])) {
                return $addonResult;
            }
        }

        $addonContext = $this->resolve_tenant_dms_addon_context((int) $tenantId);
        if (empty($addonContext)) {
            return ['success' => false, 'message' => 'Choose a DMS package before provisioning Salesflow DMS.'];
        }

        $addonStatus = (string) ($addonContext['status'] ?? 'none');
        if ($addonStatus !== 'active') {
            $this->log_activity('marketplace.salesflow_dms_purchase_required', 'warning', [
                'tenant_id' => (int) $tenantId,
                'app_slug' => 'salesflow-dms',
                'package_code' => (string) ($addonContext['package_code'] ?? ''),
                'addon_status' => $addonStatus,
            ], (int) $tenantId);

            return [
                'success' => true,
                'requires_activation' => true,
                'addon_status' => $addonStatus,
                'message' => 'DMS package selected. Complete payment before provisioning can continue.',
            ];
        }

        $moduleResult = $this->save_tenant_module_override((int) $tenantId, 'kt_integration_hub', 'enable');
        if (empty($moduleResult['success'])) {
            return $moduleResult;
        }

        $jobId = $this->create_provision_job((int) $tenantId, 'install_salesflow_dms', [
            'tenant_id' => (int) $tenantId,
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'app_slug' => 'salesflow-dms',
            'source' => 'tenant_marketplace',
            'package_code' => (string) ($addonContext['package_code'] ?? ''),
        ]);

        $this->sync_tenant_module_registry((int) $tenantId, $tenant);
        $this->log_activity('marketplace.salesflow_dms_install_requested', 'info', [
            'tenant_id' => (int) $tenantId,
            'app_slug' => 'salesflow-dms',
            'job_id' => $jobId ?: null,
            'package_code' => (string) ($addonContext['package_code'] ?? ''),
        ], (int) $tenantId);

        return [
            'success' => true,
            'job_id' => (int) $jobId,
            'addon_status' => $addonStatus,
            'package_code' => (string) ($addonContext['package_code'] ?? ''),
        ];
    }

    public function get_tenant_provision_jobs($tenantId, array $jobTypes = [], $limit = 20)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }

        $db = $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->order_by('id', 'desc')
            ->limit((int) $limit);

        if (!empty($jobTypes)) {
            $db->where_in('job_type', array_values(array_unique(array_filter(array_map('strval', $jobTypes)))));
        }

        return $db->get(db_prefix() . 'kt_saas_provision_jobs')->result_array();
    }

    public function get_backups($tenantId = null)
    {
        $this->landlord_db()
            ->select('b.*, t.tenant_code, t.company_name')
            ->from(db_prefix() . 'kt_saas_backups b')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = b.tenant_id', 'left')
            ->order_by('b.id', 'desc');

        if ($tenantId !== null) {
            $this->landlord_db()->where('b.tenant_id', (int) $tenantId);
        }

        return $this->landlord_db()->get()->result_array();
    }

    public function get_backup($backupId)
    {
        return $this->landlord_db()
            ->where('id', (int) $backupId)
            ->get(db_prefix() . 'kt_saas_backups')
            ->row_array();
    }

    public function get_running_backup_for_tenant($tenantId)
    {
        return $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('status', 'running')
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'kt_saas_backups')
            ->row_array();
    }

    public function create_backup_record($tenantId, $backupType = 'db', $storageDriver = 'local')
    {
        $now = date('Y-m-d H:i:s');
        $this->landlord_db()->insert(db_prefix() . 'kt_saas_backups', [
            'tenant_id'        => (int) $tenantId,
            'backup_type'      => $backupType,
            'status'           => 'queued',
            'storage_driver'   => $storageDriver,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) $this->landlord_db()->insert_id();
    }

    public function update_backup_record($backupId, array $payload)
    {
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $this->landlord_db()->where('id', (int) $backupId)->update(db_prefix() . 'kt_saas_backups', $payload);
    }

    public function cleanup_expired_backups($retentionDays)
    {
        $retentionDays = max((int) $retentionDays, 1);
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $retentionDays . ' days'));

        $expired = $this->landlord_db()
            ->where('status', 'done')
            ->where('completed_at IS NOT NULL', null, false)
            ->where('completed_at <', $cutoff)
            ->get(db_prefix() . 'kt_saas_backups')
            ->result_array();

        $deleted = 0;
        foreach ($expired as $backup) {
            $path = (string) ($backup['file_path'] ?? '');
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }

            $this->landlord_db()
                ->where('id', (int) $backup['id'])
                ->delete(db_prefix() . 'kt_saas_backups');
            $deleted++;
        }

        return [
            'deleted' => $deleted,
            'cutoff'  => $cutoff,
        ];
    }

    public function save_tenant($data, $id = null)
    {
        $db = $this->landlord_db();
        $isCreate = empty($id);
        $tenantCodeInput = trim((string) ($data['tenant_code'] ?? ''));
        $companyName = trim((string) ($data['company_name'] ?? ''));
        $ownerName = trim((string) ($data['owner_name'] ?? ''));
        $ownerEmail = trim((string) ($data['owner_email'] ?? ''));
        $subdomainInput = trim((string) ($data['subdomain'] ?? ''));
        $dbNameInput = trim((string) ($data['db_name'] ?? ''));
        $dbUserInput = trim((string) ($data['db_user'] ?? ''));
        $dbPasswordInput = trim((string) ($data['db_password'] ?? ''));
        $customDomainInput = trim((string) ($data['custom_domain'] ?? ''));

        if ($companyName === '' || $ownerName === '' || $ownerEmail === '') {
            return ['success' => false, 'message' => 'Company name, owner name, and owner email are required.'];
        }

        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Owner email is invalid.'];
        }

        $slugBase = $this->slugifyTenantValue($companyName);
        if ($slugBase === '') {
            $slugBase = 'tenant';
        }

        $tenantCode = $tenantCodeInput !== ''
            ? $this->sanitizeTenantCode($tenantCodeInput)
            : $this->generateTenantCode();

        if ($tenantCode === '') {
            return ['success' => false, 'message' => 'Tenant code format is invalid.'];
        }

        if ($this->tenantCodeExists($tenantCode, $id)) {
            if ($tenantCodeInput === '') {
                $tenantCode = $this->generateUniqueTenantCode($id);
            } else {
                return [
                    'success' => false,
                    'message' => 'Tenant code already exists.',
                    'field' => 'tenant_code',
                    'suggestion' => $this->generateUniqueTenantCode($id),
                ];
            }
        }

        $subdomain = $subdomainInput !== '' ? $this->slugifyTenantValue($subdomainInput) : $slugBase;
        if ($subdomain === '') {
            $subdomain = 'tenant';
        }
        $subdomainCheck = $this->checkSubdomainAvailability($subdomain, $id);
        if (empty($subdomainCheck['available'])) {
            if ($subdomainInput === '') {
                $subdomain = $this->generateUniqueSubdomain($subdomain, $id);
            } else {
                return [
                    'success' => false,
                    'message' => (string) ($subdomainCheck['message'] ?? 'Subdomain is unavailable.'),
                    'field' => 'subdomain',
                    'suggestion' => !empty($subdomainCheck['suggestions'][0]) ? (string) $subdomainCheck['suggestions'][0] : $this->generateUniqueSubdomain($subdomain, $id),
                ];
            }
        }

        $customDomain = $customDomainInput !== '' ? strtolower($customDomainInput) : null;
        if ($customDomain !== null && $this->customDomainExists($customDomain, $id)) {
            return [
                'success' => false,
                'message' => 'Custom domain already exists.',
                'field' => 'custom_domain',
            ];
        }

        $dbName = $dbNameInput !== '' ? $this->sanitizeDbName($dbNameInput) : $this->generateDbName($subdomain);
        if (!$this->isValidDbName($dbName)) {
            return ['success' => false, 'message' => 'Database name format is invalid.', 'field' => 'db_name'];
        }
        if ($this->dbNameExists($dbName, $id)) {
            if ($dbNameInput === '') {
                $dbName = $this->generateUniqueDbName($subdomain, $id);
            } else {
                return [
                    'success' => false,
                    'message' => 'Database name already exists.',
                    'field' => 'db_name',
                    'suggestion' => $this->generateUniqueDbName($subdomain, $id),
                ];
            }
        }

        $dbUser = $dbUserInput !== '' ? $this->sanitizeDbUser($dbUserInput) : $this->generateDbUser($subdomain);
        if (!$this->isValidDbUser($dbUser)) {
            return ['success' => false, 'message' => 'Database username format is invalid.', 'field' => 'db_user'];
        }
        if ($this->dbUserExists($dbUser, $id)) {
            if ($dbUserInput === '') {
                $dbUser = $this->generateUniqueDbUser($subdomain, $id);
            } else {
                return [
                    'success' => false,
                    'message' => 'Database username already exists.',
                    'field' => 'db_user',
                    'suggestion' => $this->generateUniqueDbUser($subdomain, $id),
                ];
            }
        }

        $dbPassword = $dbPasswordInput;
        if ($isCreate && $dbPassword === '') {
            $dbPassword = $this->generateStrongPassword(24);
        }
        if ($isCreate && strlen($dbPassword) < 20) {
            return ['success' => false, 'message' => 'Database password must be at least 20 characters.', 'field' => 'db_password'];
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'tenant_code'          => $tenantCode,
            'company_name'         => $companyName,
            'owner_name'           => $ownerName,
            'owner_email'          => $ownerEmail,
            'phone'                => trim((string) ($data['phone'] ?? '')),
            'status'               => trim((string) ($data['status'] ?? 'draft')),
            'plan_id'              => !empty($data['plan_id']) ? (int) $data['plan_id'] : null,
            'db_name'              => $dbName,
            'db_host'              => trim((string) ($data['db_host'] ?? APP_DB_HOSTNAME)),
            'db_port'              => trim((string) ($data['db_port'] ?? '3306')),
            'db_user'              => $dbUser,
            'db_password_encrypted'=> $this->encryptNullable($dbPassword),
            'subdomain'            => $subdomain !== '' ? $subdomain : null,
            'custom_domain'        => $customDomain,
            'timezone'             => trim((string) ($data['timezone'] ?? 'UTC')),
            'locale'               => trim((string) ($data['locale'] ?? 'english')),
            'currency'             => trim((string) ($data['currency'] ?? 'USD')),
            'storage_driver'       => trim((string) ($data['storage_driver'] ?? 'local')),
            'storage_path'         => trim((string) ($data['storage_path'] ?? '')),
            'expires_at'           => !empty($data['expires_at']) ? $data['expires_at'] . ' 23:59:59' : null,
            'updated_at'           => $now,
            'updated_by'           => get_staff_user_id() ?: null,
        ];

        if ($id) {
            $current = $this->get_tenant($id);
            if (!$current) {
                return ['success' => false, 'message' => 'Tenant not found.'];
            }

            if ($payload['db_password_encrypted'] === null) {
                $payload['db_password_encrypted'] = $current['db_password_encrypted'];
            }

            $payload = $this->normalizeTenantStateOnSave($payload, $current);

            $planChanged = false;
            $newPlanId = !empty($payload['plan_id']) ? (int) $payload['plan_id'] : null;
            $oldPlanId = !empty($current['plan_id']) ? (int) $current['plan_id'] : null;
            if ($newPlanId !== $oldPlanId) {
                $planChanged = true;
            }

            $db->where('id', (int) $id)->update(db_prefix() . 'kt_saas_tenants', $payload);

            if ($planChanged) {
                if ($newPlanId) {
                    $newPlan = $this->get_plan($newPlanId);
                    if ($newPlan) {
                        $activeSub = $this->get_current_subscription((int) $id);
                        $subscriptionId = 0;

                        $subStatus = ((int) ($newPlan['trial_days'] ?? 0) > 0) ? 'trial' : 'active';
                        $trialEndsAt = ((int) ($newPlan['trial_days'] ?? 0) > 0) ? date('Y-m-d 23:59:59', strtotime('+' . (int) $newPlan['trial_days'] . ' days')) : null;
                        $timeline = $this->buildSubscriptionTimeline($newPlan, $subStatus, new DateTimeImmutable($now));

                        $subPayload = [
                            'plan_id'                 => $newPlanId,
                            'status'                  => $subStatus,
                            'billing_cycle'           => $newPlan['billing_cycle'] ?? 'monthly',
                            'trial_ends_at'           => $trialEndsAt,
                            'current_period_start_at' => $timeline['current_period_start_at'],
                            'current_period_end_at'   => $timeline['current_period_end_at'],
                            'next_billing_at'         => $timeline['next_billing_at'],
                            'grace_ends_at'           => null,
                            'updated_at'              => $now,
                        ];

                        if ($activeSub) {
                            $db->where('id', (int) $activeSub['id'])->update(db_prefix() . 'kt_saas_subscriptions', $subPayload);
                            $subscriptionId = (int) $activeSub['id'];
                            $this->log_activity('subscription.updated', 'info', [
                                'subscription_id' => (int) $activeSub['id'],
                                'tenant_id'       => (int) $id,
                                'plan_id'         => $newPlanId,
                                'reason'          => 'tenant_plan_changed_by_admin',
                            ], (int) $id);
                        } else {
                            $subPayload['tenant_id']  = (int) $id;
                            $subPayload['started_at'] = $now;
                            $subPayload['created_at'] = $now;
                            $subPayload['created_by'] = get_staff_user_id() ?: null;
                            $subPayload['auto_renew'] = 1;
                            $db->insert(db_prefix() . 'kt_saas_subscriptions', $subPayload);
                            $subscriptionId = (int) $db->insert_id();
                        }

                        if ($subscriptionId > 0) {
                            $tenantRow = $this->get_tenant((int) $id);
                            $subscriptionRow = $this->get_current_subscription((int) $id);
                            if ($tenantRow && $subscriptionRow) {
                                $baseContext = [
                                    'tenant_id' => (int) $id,
                                    'tenant' => $tenantRow,
                                    'subscription' => $subscriptionRow,
                                    'plan' => $newPlan,
                                    'owner_name' => (string) ($tenantRow['owner_name'] ?? $tenantRow['company_name'] ?? ''),
                                    'owner_email' => (string) ($tenantRow['owner_email'] ?? ''),
                                    'tenant_name' => (string) ($tenantRow['company_name'] ?? ''),
                                    'tenant_code' => (string) ($tenantRow['tenant_code'] ?? ''),
                                    'trial_end_date' => (string) ($subscriptionRow['trial_ends_at'] ?? ''),
                                    'subscription_status' => (string) ($subscriptionRow['status'] ?? ''),
                                    'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenantRow), '/') : '',
                                    'workspace_domain' => trim((string) ($tenantRow['custom_domain'] ?? $tenantRow['subdomain'] ?? '')),
                                    'plan_name' => (string) ($newPlan['plan_name'] ?? ''),
                                    'related_type' => 'subscription',
                                    'related_id' => (string) $subscriptionId,
                                ];

                                if ((int) ($newPlan['trial_days'] ?? 0) > 0) {
                                    $trialContext = $baseContext;
                                    $trialContext['dedupe_key'] = 'tenant_trial_started|' . (int) $id . '|' . $subscriptionId . '|' . (string) ($subscriptionRow['trial_ends_at'] ?? '');
                                    $this->send_email_event('tenant_trial_started', $trialContext);
                                }

                                $planChangedContext = $baseContext;
                                $planChangedContext['dedupe_key'] = 'tenant_plan_changed|' . (int) $id . '|' . $subscriptionId . '|' . (int) $newPlanId;
                                $this->send_email_event('tenant_plan_changed', $planChangedContext);
                            }
                        }
                    }
                } else {
                    $db->where('tenant_id', (int) $id)
                        ->where('deleted_at IS NULL', null, false)
                        ->update(db_prefix() . 'kt_saas_subscriptions', [
                            'deleted_at' => $now,
                            'deleted_by' => get_staff_user_id() ?: null,
                        ]);
                }
            }

            $this->sync_tenant_domains((int) $id, $payload['subdomain'], $payload['custom_domain']);
            $this->sync_tenant_module_registry((int) $id);
            $this->log_activity('tenant.updated', 'info', [
                'tenant_id'   => (int) $id,
                'tenant_code' => $tenantCode,
            ], (int) $id);
            return ['success' => true, 'id' => (int) $id, 'created' => false];
        }

        $payload['created_at'] = $now;
        $payload['created_by'] = get_staff_user_id() ?: null;
        $payload['provisioning_status'] = 'queued';
        $payload = $this->normalizeTenantStateOnSave($payload);

        $db->insert(db_prefix() . 'kt_saas_tenants', $payload);
        $dbError = $db->error();
        if (!empty($dbError['code'])) {
            if ((int) $dbError['code'] === 1062) {
                return [
                    'success' => false,
                    'message' => 'Subdomain already exists.',
                    'field' => 'subdomain',
                    'suggestion' => $this->generateUniqueSubdomain($subdomain, $id),
                ];
            }
            return [
                'success' => false,
                'message' => 'Failed to create tenant.',
                'error' => $dbError,
            ];
        }
        $tenantId = (int) $db->insert_id();

        if ($payload['db_name'] === '') {
            $payload['db_name'] = $this->generateTenantDatabaseName($tenantCode, $tenantId, $payload['subdomain']);
            $db->where('id', $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
                'db_name'    => $payload['db_name'],
                'updated_at' => $now,
                'updated_by' => get_staff_user_id() ?: null,
            ]);
        }

        $this->sync_tenant_domains($tenantId, $payload['subdomain'], $payload['custom_domain']);
        $this->ensure_tenant_subscription($tenantId, $payload['plan_id']);
        $this->sync_tenant_module_registry($tenantId);

        $tenantRow = $this->get_tenant($tenantId);
        $subscriptionRow = $this->get_current_subscription($tenantId);
        $planRow = !empty($payload['plan_id']) ? $this->get_plan((int) $payload['plan_id']) : null;
        if ($tenantRow && $subscriptionRow && $planRow && (int) ($planRow['trial_days'] ?? 0) > 0) {
            $trialContext = [
                'tenant_id' => $tenantId,
                'tenant' => $tenantRow,
                'subscription' => $subscriptionRow,
                'plan' => $planRow,
                'owner_name' => (string) ($tenantRow['owner_name'] ?? $tenantRow['company_name'] ?? ''),
                'owner_email' => (string) ($tenantRow['owner_email'] ?? ''),
                'tenant_name' => (string) ($tenantRow['company_name'] ?? ''),
                'tenant_code' => (string) ($tenantRow['tenant_code'] ?? ''),
                'trial_end_date' => (string) ($subscriptionRow['trial_ends_at'] ?? ''),
                'subscription_status' => (string) ($subscriptionRow['status'] ?? ''),
                'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenantRow), '/') : '',
                'workspace_domain' => trim((string) ($tenantRow['custom_domain'] ?? $tenantRow['subdomain'] ?? '')),
                'plan_name' => (string) ($planRow['plan_name'] ?? ''),
                'related_type' => 'subscription',
                'related_id' => (string) ($subscriptionRow['id'] ?? $tenantId),
                'dedupe_key' => 'tenant_trial_started|' . $tenantId . '|' . (string) ($subscriptionRow['id'] ?? $tenantId) . '|' . (string) ($subscriptionRow['trial_ends_at'] ?? ''),
            ];
            $this->send_email_event('tenant_trial_started', $trialContext);
        }

        $this->create_provision_job($tenantId, 'provision_tenant', [
            'tenant_id' => $tenantId,
            'tenant_code' => $tenantCode,
        ]);

        $this->log_activity('tenant.created', 'info', [
            'tenant_id'   => $tenantId,
            'tenant_code' => $tenantCode,
            'company_name'=> $companyName,
        ], $tenantId);

        return ['success' => true, 'id' => $tenantId, 'created' => true];
    }

    public function generate_tenant_form_values($companyName, $excludeId = null)
    {
        $slug = $this->slugifyTenantValue((string) $companyName);
        if ($slug === '') {
            $slug = 'tenant';
        }

        $tenantCode = $this->generateUniqueTenantCode($excludeId);
        $subdomain = $this->generateUniqueSubdomain($slug, $excludeId);
        $dbName = $this->generateUniqueDbName($subdomain, $excludeId);
        $dbUser = $this->generateUniqueDbUser($subdomain, $excludeId);
        $dbPassword = $this->generateStrongPassword(24);

        return [
            'tenant_code' => $tenantCode,
            'subdomain' => $subdomain,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPassword,
        ];
    }

    private function find_safe_landlord_contact_by_email($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $matches = $this->landlord_db()
            ->where('LOWER(email) = ' . $this->landlord_db()->escape($email), null, false)
            ->get(db_prefix() . 'contacts')
            ->result();

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function find_landlord_contact_for_client($clientId, $email = '')
    {
        $clientId = (int) $clientId;
        if ($clientId <= 0) {
            return null;
        }

        $db = $this->landlord_db();
        $email = strtolower(trim((string) $email));

        if ($email !== '') {
            $contact = $db
                ->where('userid', $clientId)
                ->where('LOWER(email) = ' . $db->escape($email), null, false)
                ->order_by('is_primary', 'desc')
                ->order_by('id', 'asc')
                ->get(db_prefix() . 'contacts')
                ->row();
            if ($contact) {
                return $contact;
            }
        }

        return $db
            ->where('userid', $clientId)
            ->order_by('is_primary', 'desc')
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'contacts')
            ->row();
    }

    private function split_owner_name($ownerName)
    {
        $ownerName = trim((string) $ownerName);
        if ($ownerName === '') {
            return ['Owner', ''];
        }

        $parts = preg_split('/\s+/', $ownerName) ?: [];
        $firstName = (string) array_shift($parts);
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }

    private function getReservedSubdomainNames()
    {
        return [
            'admin',
            'crm',
            'api',
            'mail',
            'smtp',
            'ftp',
            'cpanel',
            'www',
            'support',
            'billing',
            'invoice',
            'checkout',
            'login',
        ];
    }

    private function isReservedSubdomain($subdomain)
    {
        $subdomain = strtolower(trim((string) $subdomain));
        return $subdomain !== '' && in_array($subdomain, $this->getReservedSubdomainNames(), true);
    }

    private function tenantDomainExists($subdomain, $excludeId = null)
    {
        $baseDomain = trim((string) kt_saas_get_option('kt_saas_base_domain', 'crm.local'));
        $candidates = [strtolower(trim((string) $subdomain))];
        if ($baseDomain !== '' && strpos($candidates[0], '.') === false) {
            $candidates[] = $candidates[0] . '.' . strtolower($baseDomain);
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            if ($this->domainExists($candidate, $excludeId)) {
                return true;
            }
        }

        $aliasTables = [
            db_prefix() . 'kt_saas_domain_aliases',
            db_prefix() . 'kt_saas_domain_alias',
            db_prefix() . 'kt_saas_aliases',
            db_prefix() . 'kt_saas_tenant_aliases',
        ];
        foreach ($aliasTables as $table) {
            if (!$this->db->table_exists($table)) {
                continue;
            }
            $query = $this->db->where('alias', $candidates[0]);
            if ($excludeId) {
                $query->where('tenant_id !=', (int) $excludeId);
            }
            if ($query->count_all_results($table) > 0) {
                return true;
            }
        }

        return false;
    }

    public function checkSubdomainAvailability($value, $excludeId = null)
    {
        $excludeId = $excludeId ? (int) $excludeId : null;
        $clean = $this->slugifyTenantValue($value);
        if (!$this->isValidSubdomain($clean)) {
            return [
                'valid' => false,
                'available' => false,
                'reason' => 'format',
                'message' => 'Subdomain format is invalid.',
                'normalized' => $clean,
                'suggestions' => $this->generateSubdomainSuggestions($clean, $excludeId),
            ];
        }

        if ($this->isReservedSubdomain($clean)) {
            return [
                'valid' => true,
                'available' => false,
                'reason' => 'reserved',
                'message' => 'Subdomain is reserved.',
                'normalized' => $clean,
                'suggestions' => $this->generateSubdomainSuggestions($clean, $excludeId),
            ];
        }

        if ($this->subdomainExists($clean, $excludeId) || $this->tenantDomainExists($clean, $excludeId)) {
            return [
                'valid' => true,
                'available' => false,
                'reason' => 'occupied',
                'message' => 'Subdomain already exists.',
                'normalized' => $clean,
                'suggestions' => $this->generateSubdomainSuggestions($clean, $excludeId),
            ];
        }

        return [
            'valid' => true,
            'available' => true,
            'reason' => 'available',
            'message' => 'Subdomain is available.',
            'normalized' => $clean,
            'suggestions' => [],
        ];
    }

    private function generateSubdomainSuggestions($base, $excludeId = null)
    {
        $base = $this->slugifyTenantValue($base);
        if ($base === '') {
            $base = 'tenant';
        }
        $candidates = [$base . '2', $base . '-crm', $base . '-office', $base . '2026'];
        for ($i = 3; $i <= 9; $i++) {
            $candidates[] = $base . (string) $i;
        }
        $suggestions = [];
        foreach ($candidates as $candidate) {
            $candidate = $this->slugifyTenantValue($candidate);
            if ($candidate === '' || !$this->isValidSubdomain($candidate)) {
                continue;
            }
            if ($this->isReservedSubdomain($candidate)) {
                continue;
            }
            if ($this->subdomainExists($candidate, $excludeId) || $this->tenantDomainExists($candidate, $excludeId)) {
                continue;
            }
            $suggestions[] = $candidate;
        }
        if (empty($suggestions)) {
            for ($i = 0; $i < 10; $i++) {
                $candidate = $this->slugifyTenantValue($base . '-' . substr(strtolower(bin2hex(random_bytes(2))), 0, 4));
                if ($candidate === '' || !$this->isValidSubdomain($candidate)) {
                    continue;
                }
                if ($this->isReservedSubdomain($candidate)) {
                    continue;
                }
                if ($this->subdomainExists($candidate, $excludeId) || $this->tenantDomainExists($candidate, $excludeId)) {
                    continue;
                }
                $suggestions[] = $candidate;
                if (count($suggestions) >= 3) {
                    break;
                }
            }
        }
        return array_values(array_unique($suggestions));
    }

    public function check_tenant_field_availability($field, $value, $excludeId = null)
    {
        $field = trim((string) $field);
        $value = trim((string) $value);
        $excludeId = $excludeId ? (int) $excludeId : null;

        if ($value === '') {
            return ['valid' => false, 'available' => false, 'message' => 'Empty value.'];
        }

        if ($field === 'tenant_code') {
            $clean = $this->sanitizeTenantCode($value);
            if ($clean === '') {
                return ['valid' => false, 'available' => false, 'message' => 'Invalid format.'];
            }
            return ['valid' => true, 'available' => !$this->tenantCodeExists($clean, $excludeId), 'normalized' => $clean];
        }

        if ($field === 'subdomain') {
            return $this->checkSubdomainAvailability($value, $excludeId);
        }

        if ($field === 'db_name') {
            $clean = $this->sanitizeDbName($value);
            if (!$this->isValidDbName($clean)) {
                return ['valid' => false, 'available' => false, 'message' => 'Invalid format.'];
            }
            return ['valid' => true, 'available' => !$this->dbNameExists($clean, $excludeId), 'normalized' => $clean];
        }

        if ($field === 'db_user') {
            $clean = $this->sanitizeDbUser($value);
            if (!$this->isValidDbUser($clean)) {
                return ['valid' => false, 'available' => false, 'message' => 'Invalid format.'];
            }
            return ['valid' => true, 'available' => !$this->dbUserExists($clean, $excludeId), 'normalized' => $clean];
        }

        if ($field === 'custom_domain') {
            $clean = strtolower($value);
            return ['valid' => true, 'available' => !$this->customDomainExists($clean, $excludeId), 'normalized' => $clean];
        }

        return ['valid' => false, 'available' => false, 'message' => 'Unsupported field.'];
    }

    public function set_tenant_status($id, $status)
    {
        $tenant = $this->get_tenant($id);
        if (!$tenant) {
            return false;
        }

        $payload = [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id() ?: null,
        ];

        if ($status === 'suspended') {
            $payload['suspended_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'terminated') {
            $payload['terminated_at'] = date('Y-m-d H:i:s');
        }

        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_saas_tenants', $payload);
        $this->log_activity('tenant.status_changed', 'warning', [
            'tenant_id'   => (int) $id,
            'tenant_code' => $tenant['tenant_code'],
            'status'      => $status,
        ], (int) $id);
        return true;
    }

    public function archive_tenant($id)
    {
        $tenant = $this->get_tenant($id);
        if (!$tenant) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_saas_tenants', [
            'status'     => 'archived',
            'deleted_at' => $now,
            'deleted_by' => get_staff_user_id() ?: null,
            'updated_at' => $now,
            'updated_by' => get_staff_user_id() ?: null,
        ]);

        if ($this->landlord_db()->table_exists(db_prefix() . 'kt_saas_domains')) {
            $this->landlord_db()->where('tenant_id', (int) $id)->update(db_prefix() . 'kt_saas_domains', $this->tenantDomainDeactivatePayload($now));
        }

        if ($this->landlord_db()->table_exists(db_prefix() . 'kt_saas_provision_jobs')) {
            $this->landlord_db()
                ->where('tenant_id', (int) $id)
                ->where_in('status', ['queued', 'running', 'pending'])
                ->update(db_prefix() . 'kt_saas_provision_jobs', [
                    'status'        => 'cancelled',
                    'error_message' => 'Tenant soft-deleted.',
                    'updated_at'    => $now,
                ]);
        }

        $this->log_activity('tenant.archived', 'warning', [
            'tenant_id'   => (int) $id,
            'tenant_code' => $tenant['tenant_code'],
        ], (int) $id);
        return true;
    }

    public function get_tenant_dependency_summary($tenantId)
    {
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return [];
        }

        $db = $this->landlord_db();
        return [
            'subscriptions' => (int) $db->where('tenant_id', $tenantId)->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_subscriptions'),
            'invoices'      => (int) $db->where('tenant_id', $tenantId)->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_invoices'),
            'payments'      => (int) $db->where('tenant_id', $tenantId)->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_payments'),
            'domains'       => (int) $db->where('tenant_id', $tenantId)->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_domains'),
            'provision_jobs'=> (int) $db->where('tenant_id', $tenantId)->count_all_results(db_prefix() . 'kt_saas_provision_jobs'),
        ];
    }

    public function delete_tenant($id, $force = false)
    {
        $tenant = $this->get_tenant((int) $id);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Không tìm thấy tenant.'];
        }

        $deps = $this->get_tenant_dependency_summary((int) $id);
        $hasDeps = array_sum($deps) > 0;
        $isActive = in_array((string) ($tenant['status'] ?? ''), ['active', 'trial', 'grace'], true);

        if (($isActive || $hasDeps) && !$force) {
            return ['success' => false, 'message' => 'Tenant đang hoạt động hoặc có dữ liệu liên quan. Hãy dùng lưu trữ hoặc xác nhận ép xóa mềm.', 'dependencies' => $deps];
        }

        $ok = $this->archive_tenant((int) $id);
        if (!$ok) {
            return ['success' => false, 'message' => 'Không thể xóa tenant.'];
        }

        $this->log_activity('tenant.deleted_soft', 'danger', [
            'tenant_id'      => (int) $id,
            'tenant_code'    => $tenant['tenant_code'] ?? null,
            'forced'         => $force ? 1 : 0,
            'dependencies'   => $deps,
        ], (int) $id);

        return ['success' => true, 'soft_deleted' => true, 'dependencies' => $deps];
    }

    public function tenant_purge_allowed()
    {
        if (defined('KT_SAAS_ALLOW_HARD_DELETE') && KT_SAAS_ALLOW_HARD_DELETE) {
            return true;
        }

        $configFlag = $this->config->item('KT_SAAS_ALLOW_HARD_DELETE');
        if ($configFlag === null) {
            $configFlag = $this->config->item('kt_saas_allow_hard_delete');
        }

        if (filter_var($configFlag, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return defined('ENVIRONMENT') && ENVIRONMENT !== 'production';
    }

    public function purge_tenant($id, $confirmation)
    {
        $tenantId = (int) $id;
        if (!$this->tenant_purge_allowed()) {
            return ['success' => false, 'message' => 'Hard purge is disabled in production.'];
        }

        $tenant = $this->get_tenant_for_lifecycle($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $tenantCode = trim((string) ($tenant['tenant_code'] ?? ''));
        if (!hash_equals('PURGE ' . $tenantCode, (string) $confirmation) && !hash_equals('PURGE ' . $tenantId, (string) $confirmation)) {
            return ['success' => false, 'message' => 'Purge confirmation does not match.'];
        }

        $db = $this->landlord_db();
        $now = date('Y-m-d H:i:s');
        $summary = [
            'tenant_id' => $tenantId,
            'tenant_code' => $tenantCode,
            'deleted_rows' => [],
            'files' => [],
            'database' => null,
        ];

        $db->where('id', $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
            'status' => 'purging',
            'updated_at' => $now,
            'updated_by' => get_staff_user_id() ?: null,
        ]);

        if ($db->table_exists(db_prefix() . 'kt_saas_domains')) {
            $db->where('tenant_id', $tenantId)->update(db_prefix() . 'kt_saas_domains', $this->tenantDomainDeactivatePayload($now));
        }

        if ($db->table_exists(db_prefix() . 'kt_saas_provision_jobs')) {
            $db->where('tenant_id', $tenantId)
                ->where_in('status', ['queued', 'running', 'pending'])
                ->update(db_prefix() . 'kt_saas_provision_jobs', [
                    'status' => 'cancelled',
                    'error_message' => 'Tenant purge requested.',
                    'updated_at' => $now,
                ]);
        }

        $invoiceIds = $this->purgeIdsForTenant('kt_saas_invoices', 'tenant_id', $tenantId);
        $subscriptionIds = $this->purgeIdsForTenant('kt_saas_subscriptions', 'tenant_id', $tenantId);
        $orderIds = $this->purgeIdsForTenant('kt_saas_orders', 'tenant_id', $tenantId);
        $addonIds = $this->purgeIdsForTenant('kt_saas_tenant_addons', 'tenant_id', $tenantId);
        $matbaoRecordIds = $this->purgeIdsForTenant('kt_matbao_invoice_records', 'tenant_id', $tenantId);
        $backupFiles = $this->purgeBackupFilesForTenant($tenantId);
        $paymentRequestIds = $this->purgePaymentRequestIds($tenantId, $invoiceIds, $subscriptionIds, $orderIds);

        $this->purgeDeleteWhereIn('kt_matbao_invoice_items_snapshot', 'record_id', $matbaoRecordIds, $summary);
        $this->purgeDeleteTenantRows([
            'kt_matbao_invoice_logs',
            'kt_matbao_invoice_webhook_logs',
            'kt_matbao_invoice_records',
            'kt_matbao_invoice_usage',
            'kt_matbao_invoice_templates',
            'kt_matbao_invoice_settings',
            'kt_matbao_invoice_hddt_accounts',
            'kt_matbao_invoice_ca_accounts',
        ], $tenantId, $summary);

        $this->purgeDeleteWhereIn('kt_saas_order_items', 'order_id', $orderIds, $summary);
        $this->purgeDeleteWhereIn('kt_saas_addon_usage_logs', 'addon_id', $addonIds, $summary);
        $this->purgeDeleteTenantRows([
            'kt_saas_provider_provisioning_jobs',
            'kt_saas_tenant_addons',
            'kt_saas_orders',
            'kt_saas_tenant_entitlements',
            'kt_saas_modules',
            'kt_saas_usage',
            'kt_saas_email_event_guards',
            'kt_saas_tenant_email_config_audit',
            'kt_saas_tenant_email_settings',
            'kt_saas_backups',
            'kt_saas_provision_jobs',
            'kt_saas_domains',
        ], $tenantId, $summary);

        $this->purgeDeleteWhereIn('kt_saas_invoice_items', 'invoice_id', $invoiceIds, $summary);
        $this->purgeDeleteTenantRows([
            'kt_saas_payments',
            'kt_saas_invoices',
            'kt_saas_subscriptions',
        ], $tenantId, $summary);

        $this->purgeDeleteWhereIn('kt_sepay_transactions', 'payment_request_id', $paymentRequestIds, $summary);
        $this->purgeDeleteWhereIn('kt_sepay_payment_requests', 'id', $paymentRequestIds, $summary);
        $this->purgeDeleteTenantRows([
            'kt_sepay_transactions',
            'kt_sepay_reconciliation_logs',
            'kt_sepay_health_logs',
            'kt_sepay_settings',
        ], $tenantId, $summary);

        $this->purgeDeleteTenantRows([
            'kt_einvoice_jobs',
            'kt_einvoice_records',
            'kt_einvoice_quota_usage',
            'kt_einvoice_batch_sessions',
            'kt_einvoice_batch_items',
            'kt_einvoice_cron_logs',
            'kt_einvoice_api_logs',
            'kt_einvoice_provider_settings',
        ], $tenantId, $summary);

        $this->purgeDeleteTenantRows([
            'kt_saas_email_logs',
            'kt_saas_activity_logs',
        ], $tenantId, $summary);

        $dropResult = $this->purgeDropTenantDatabase($tenant);
        $summary['database'] = $dropResult;
        if (empty($dropResult['success'])) {
            return ['success' => false, 'message' => $dropResult['message'] ?? 'Tenant database purge failed.', 'summary' => $summary];
        }

        foreach ($this->purgeTenantFileTargets($tenant, $backupFiles) as $target) {
            $summary['files'][] = $this->purgeSafePath($target['path'], $target['base'], $target['label']);
        }

        $this->purgeDeleteRowsById('kt_saas_tenants', [$tenantId], $summary);
        $this->log_activity('tenant.purged', 'danger', $summary);

        return ['success' => true, 'summary' => $summary];
    }

    public function scan_orphan_tenant_data()
    {
        $tenantIds = [];
        foreach ($this->landlord_db()->select('id')->get(db_prefix() . 'kt_saas_tenants')->result_array() as $row) {
            $tenantIds[(int) $row['id']] = true;
        }

        $report = [];
        $tables = [
            'kt_saas_domains',
            'kt_saas_subscriptions',
            'kt_saas_invoices',
            'kt_saas_usage',
            'kt_saas_payments',
            'kt_saas_activity_logs',
            'kt_saas_email_logs',
            'kt_saas_provision_jobs',
            'kt_saas_backups',
            'kt_saas_orders',
            'kt_saas_tenant_addons',
            'kt_sepay_settings',
            'kt_sepay_payment_requests',
            'kt_sepay_transactions',
            'kt_sepay_reconciliation_logs',
            'kt_sepay_health_logs',
            'kt_matbao_invoice_settings',
            'kt_matbao_invoice_records',
            'kt_matbao_invoice_logs',
            'kt_matbao_invoice_usage',
        ];

        foreach ($tables as $table) {
            $full = db_prefix() . $table;
            if (!$this->landlord_db()->table_exists($full) || !$this->landlord_db()->field_exists('tenant_id', $full)) {
                continue;
            }

            $rows = $this->landlord_db()
                ->select('tenant_id, COUNT(*) as total')
                ->where('tenant_id IS NOT NULL', null, false)
                ->group_by('tenant_id')
                ->get($full)
                ->result_array();

            foreach ($rows as $row) {
                $tid = (int) $row['tenant_id'];
                if ($tid > 0 && empty($tenantIds[$tid])) {
                    $report[] = [
                        'resource' => $table,
                        'key' => 'tenant_id=' . $tid,
                        'count' => (int) $row['total'],
                        'action' => 'report_only',
                    ];
                }
            }
        }

        return $report;
    }

    public function get_plans()
    {
        return $this->landlord_db()->where('deleted_at IS NULL', null, false)->order_by('price', 'asc')->get(db_prefix() . 'kt_saas_plans')->result_array();
    }

    public function get_plan($id)
    {
        return $this->landlord_db()->where('id', (int) $id)->where('deleted_at IS NULL', null, false)->get(db_prefix() . 'kt_saas_plans')->row_array();
    }

    public function save_plan($data, $id = null)
    {
        $db = $this->landlord_db();
        $planCode = strtolower(trim((string) ($data['plan_code'] ?? '')));
        $planName = trim((string) ($data['plan_name'] ?? ''));
        if ($planCode === '' || $planName === '') {
            return ['success' => false, 'message' => 'Plan code and plan name are required.'];
        }

        if ($this->planCodeExists($planCode, $id)) {
            return ['success' => false, 'message' => 'Plan code already exists.'];
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'plan_code'                => $planCode,
            'plan_name'                => $planName,
            'billing_cycle'            => trim((string) ($data['billing_cycle'] ?? 'monthly')),
            'price'                    => (float) ($data['price'] ?? 0),
            'setup_fee'                => (float) ($data['setup_fee'] ?? 0),
            'currency'                 => trim((string) ($data['currency'] ?? 'USD')),
            'trial_days'               => (int) ($data['trial_days'] ?? 0),
            'grace_days'               => (int) ($data['grace_days'] ?? 0),
            'is_public'                => isset($data['is_public']) ? 1 : 0,
            'is_active'                => isset($data['is_active']) ? 1 : 0,
            'module_json'              => $this->normalizeModuleJson($data['module_codes'] ?? ''),
            'limit_staff'              => (int) ($data['limit_staff'] ?? 0),
            'limit_clients'            => (int) ($data['limit_clients'] ?? 0),
            'limit_storage_mb'         => (int) ($data['limit_storage_mb'] ?? 0),
            'limit_invoices'           => (int) ($data['limit_invoices'] ?? 0),
            'limit_projects'           => (int) ($data['limit_projects'] ?? 0),
            'limit_api_requests_daily' => (int) ($data['limit_api_requests_daily'] ?? 0),
            'limit_warehouses'         => (int) ($data['limit_warehouses'] ?? 0),
            'limit_automations'        => (int) ($data['limit_automations'] ?? 0),
            'limit_roles'              => (int) ($data['limit_roles'] ?? 0),
            'limit_departments'        => (int) ($data['limit_departments'] ?? 0),
            'limit_governance_viewers' => (int) ($data['limit_governance_viewers'] ?? 0),
            'limit_governance_managers'=> (int) ($data['limit_governance_managers'] ?? 0),
            'notes'                    => trim((string) ($data['notes'] ?? '')),
            'updated_at'               => $now,
            'updated_by'               => get_staff_user_id() ?: null,
        ];

        if ($id) {
            $db->where('id', (int) $id)->update(db_prefix() . 'kt_saas_plans', $payload);
            $this->sync_plan_features(
                (int) $id,
                $payload['module_json'],
                $data['workspace_feature_keys'] ?? [],
                $data['integration_feature_keys'] ?? [],
                $data['integration_feature_values'] ?? []
            );
            $this->sync_all_tenant_module_registries((int) $id);
            $this->log_activity('plan.updated', 'info', [
                'plan_id'   => (int) $id,
                'plan_code' => $planCode,
            ]);
            return ['success' => true, 'id' => (int) $id];
        }

        $payload['created_at'] = $now;
        $payload['created_by'] = get_staff_user_id() ?: null;
        $db->insert(db_prefix() . 'kt_saas_plans', $payload);

        $planId = (int) $db->insert_id();
        $this->sync_plan_features(
            $planId,
            $payload['module_json'],
            $data['workspace_feature_keys'] ?? [],
            $data['integration_feature_keys'] ?? [],
            $data['integration_feature_values'] ?? []
        );
        $this->sync_all_tenant_module_registries($planId);
        $this->log_activity('plan.created', 'info', [
            'plan_id'   => $planId,
            'plan_code' => $planCode,
        ]);

        return ['success' => true, 'id' => $planId];
    }

    public function get_plan_dependency_summary($planId)
    {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return [];
        }

        $db = $this->landlord_db();
        $invoiceCount = (int) $db
            ->from(db_prefix() . 'kt_saas_invoices i')
            ->join(db_prefix() . 'kt_saas_subscriptions s', 's.id = i.subscription_id', 'left')
            ->where('s.plan_id', $planId)
            ->where('i.deleted_at IS NULL', null, false)
            ->count_all_results();

        return [
            'tenants' => (int) $db->where('plan_id', $planId)->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_tenants'),
            'subscriptions' => (int) $db->where('plan_id', $planId)->where('deleted_at IS NULL', null, false)->count_all_results(db_prefix() . 'kt_saas_subscriptions'),
            'invoices' => $invoiceCount,
        ];
    }

    public function archive_plan($id)
    {
        $plan = $this->get_plan((int) $id);
        if (!$plan) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_saas_plans', [
            'is_active'  => 0,
            'is_public'  => 0,
            'deleted_at' => $now,
            'deleted_by' => get_staff_user_id() ?: null,
            'updated_at' => $now,
            'updated_by' => get_staff_user_id() ?: null,
        ]);
        $this->log_activity('plan.archived', 'warning', ['plan_id' => (int) $id, 'plan_code' => $plan['plan_code'] ?? null]);
        return true;
    }

    public function set_plan_visibility($id, $isPublic)
    {
        $plan = $this->get_plan((int) $id);
        if (!$plan) {
            return false;
        }

        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_saas_plans', [
            'is_public'  => $isPublic ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => get_staff_user_id() ?: null,
        ]);
        $this->log_activity('plan.visibility_changed', 'info', ['plan_id' => (int) $id, 'is_public' => $isPublic ? 1 : 0]);
        return true;
    }

    public function duplicate_plan($id)
    {
        $plan = $this->get_plan((int) $id);
        if (!$plan) {
            return ['success' => false, 'message' => 'Không tìm thấy gói.'];
        }

        $newCode = $plan['plan_code'] . '_copy_' . date('His');
        $payload = $plan;
        unset($payload['id']);
        $payload['plan_code'] = $newCode;
        $payload['plan_name'] = ($plan['plan_name'] ?? 'Plan') . ' (Copy)';
        $payload['is_active'] = 0;
        $payload['is_public'] = 0;
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['created_by'] = get_staff_user_id() ?: null;
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $payload['updated_by'] = get_staff_user_id() ?: null;
        $payload['deleted_at'] = null;
        $payload['deleted_by'] = null;

        $this->landlord_db()->insert(db_prefix() . 'kt_saas_plans', $payload);
        $newId = (int) $this->landlord_db()->insert_id();
        if ($newId <= 0) {
            return ['success' => false, 'message' => 'Không thể nhân bản gói.'];
        }

        $features = $this->landlord_db()->where('plan_id', (int) $id)->get(db_prefix() . 'kt_saas_plan_features')->result_array();
        foreach ($features as $feature) {
            unset($feature['id']);
            $feature['plan_id'] = $newId;
            $feature['created_at'] = date('Y-m-d H:i:s');
            $this->landlord_db()->insert(db_prefix() . 'kt_saas_plan_features', $feature);
        }

        $this->log_activity('plan.duplicated', 'info', ['source_plan_id' => (int) $id, 'new_plan_id' => $newId]);
        return ['success' => true, 'id' => $newId];
    }

    public function delete_plan($id)
    {
        $plan = $this->get_plan((int) $id);
        if (!$plan) {
            return ['success' => false, 'message' => 'Không tìm thấy gói.'];
        }

        $deps = $this->get_plan_dependency_summary((int) $id);
        if (array_sum($deps) > 0) {
            $this->set_plan_visibility((int) $id, false);
            return ['success' => false, 'message' => 'Gói đã có tenant/subscription/invoice. Đã tự động ẩn gói thay vì xóa.', 'dependencies' => $deps, 'archived' => false, 'hidden' => true];
        }

        $ok = $this->archive_plan((int) $id);
        if (!$ok) {
            return ['success' => false, 'message' => 'Không thể xóa gói.'];
        }

        $this->log_activity('plan.deleted_soft', 'danger', ['plan_id' => (int) $id, 'plan_code' => $plan['plan_code'] ?? null]);
        return ['success' => true, 'soft_deleted' => true, 'dependencies' => $deps];
    }

    protected function sync_plan_features($planId, $moduleJson, $workspaceFeatureKeys = [], $integrationFeatureKeys = [], $integrationFeatureValues = [])
    {
        $db = $this->landlord_db();
        $db->where('plan_id', $planId)->delete(db_prefix() . 'kt_saas_plan_features');
        if ($moduleJson) {
            $modules = json_decode($moduleJson, true);
            if (is_array($modules)) {
                foreach ($modules as $modName) {
                    $db->insert(db_prefix() . 'kt_saas_plan_features', [
                        'plan_id'     => $planId,
                        'module_name' => $modName,
                        'feature_key' => $modName . '.access',
                        'is_enabled'  => 1,
                        'created_at'  => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        $catalog = $this->get_workspace_feature_catalog();
        $workspaceFeatureKeys = is_array($workspaceFeatureKeys) ? $workspaceFeatureKeys : [];
        $selectedWorkspaceFeatures = [];
        foreach ($workspaceFeatureKeys as $featureKey) {
            $featureKey = trim((string) $featureKey);
            if ($featureKey !== '' && isset($catalog[$featureKey])) {
                $selectedWorkspaceFeatures[$featureKey] = true;
            }
        }

        foreach ($catalog as $featureKey => $meta) {
            $db->insert(db_prefix() . 'kt_saas_plan_features', [
                'plan_id'     => $planId,
                'module_name' => (string) ($meta['module_name'] ?? 'workspace'),
                'feature_key' => $featureKey,
                'is_enabled'  => isset($selectedWorkspaceFeatures[$featureKey]) ? 1 : 0,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        $integrationCatalog = $this->get_integration_feature_catalog();
        $integrationFeatureKeys = is_array($integrationFeatureKeys) ? $integrationFeatureKeys : [];
        $selectedIntegrationFeatures = [];
        foreach ($integrationFeatureKeys as $featureKey) {
            $featureKey = trim((string) $featureKey);
            if ($featureKey !== '' && isset($integrationCatalog[$featureKey])) {
                $selectedIntegrationFeatures[$featureKey] = true;
            }
        }

        foreach ($integrationCatalog as $featureKey => $meta) {
            if (strtolower((string) ($meta['module_name'] ?? '')) === 'dms') {
                continue;
            }
            $db->insert(db_prefix() . 'kt_saas_plan_features', [
                'plan_id'     => $planId,
                'module_name' => (string) ($meta['module_name'] ?? ''),
                'feature_key' => $featureKey,
                'is_enabled'  => isset($selectedIntegrationFeatures[$featureKey]) ? 1 : 0,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

    }

    public function rehydrate_all_plan_features()
    {
        $plans = $this->landlord_db()
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_plans')
            ->result_array();

        $processed = 0;
        foreach ($plans as $plan) {
            $planId = (int) ($plan['id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }

            $selectedWorkspace = $this->get_plan_workspace_feature_keys($planId);
            $selectedIntegration = $this->get_plan_integration_feature_keys($planId);
            $moduleJson = (string) ($plan['module_json'] ?? '[]');
            $this->sync_plan_features($planId, $moduleJson, $selectedWorkspace, $selectedIntegration);
            $processed++;
        }

        $this->log_activity('plan.features_rehydrated', 'info', [
            'processed' => $processed,
        ]);

        return ['success' => true, 'processed' => $processed];
    }

    public function cleanup_plan_dms_feature_ownership(): array
    {
        $db = $this->landlord_db();
        $table = db_prefix() . 'kt_saas_plan_features';

        $db->group_start()
            ->where('module_name', 'dms')
            ->or_like('feature_key', 'dms.', 'after')
            ->group_end();
        $count = (int) $db->count_all_results($table);

        if ($count > 0) {
            $db->group_start()
                ->where('module_name', 'dms')
                ->or_like('feature_key', 'dms.', 'after')
                ->group_end()
                ->delete($table);
        }

        return ['success' => true, 'removed' => $count];
    }

    public function get_subscriptions()
    {
        return $this->landlord_db()
            ->select('s.*, t.tenant_code, t.company_name, p.plan_name')
            ->from(db_prefix() . 'kt_saas_subscriptions s')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = s.tenant_id', 'left')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('s.deleted_at IS NULL', null, false)
            ->order_by('s.id', 'desc')
            ->get()
            ->result_array();
    }

    public function get_subscription($id)
    {
        return $this->landlord_db()->where('id', (int) $id)->where('deleted_at IS NULL', null, false)->get(db_prefix() . 'kt_saas_subscriptions')->row_array();
    }

    public function save_subscription($data, $id = null)
    {
        $db = $this->landlord_db();
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $planId = (int) ($data['plan_id'] ?? 0);
        if (!$this->get_tenant($tenantId) || !$this->get_plan($planId)) {
            return ['success' => false, 'message' => 'Tenant or plan is invalid.'];
        }

        $startedAt = !empty($data['started_at']) ? $data['started_at'] . ' 00:00:00' : date('Y-m-d 00:00:00');
        $payload = [
            'tenant_id'                => $tenantId,
            'plan_id'                  => $planId,
            'status'                   => trim((string) ($data['status'] ?? 'trial')),
            'billing_cycle'            => trim((string) ($data['billing_cycle'] ?? 'monthly')),
            'started_at'               => $startedAt,
            'trial_ends_at'            => !empty($data['trial_ends_at']) ? $data['trial_ends_at'] . ' 23:59:59' : null,
            'current_period_start_at'  => !empty($data['current_period_start_at']) ? $data['current_period_start_at'] . ' 00:00:00' : null,
            'current_period_end_at'    => !empty($data['current_period_end_at']) ? $data['current_period_end_at'] . ' 23:59:59' : null,
            'grace_ends_at'            => !empty($data['grace_ends_at']) ? $data['grace_ends_at'] . ' 23:59:59' : null,
            'next_billing_at'          => !empty($data['next_billing_at']) ? $data['next_billing_at'] . ' 00:00:00' : null,
            'auto_renew'               => isset($data['auto_renew']) ? 1 : 0,
            'metadata_json'            => trim((string) ($data['metadata_json'] ?? '')) ?: null,
            'updated_at'               => date('Y-m-d H:i:s'),
            'updated_by'               => get_staff_user_id() ?: null,
        ];

        if ($id) {
            $db->where('id', (int) $id)->update(db_prefix() . 'kt_saas_subscriptions', $payload);
            $db->where('id', (int) $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
                'plan_id'    => $planId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->sync_tenant_module_registry($tenantId);
            $this->log_activity('subscription.updated', 'info', [
                'subscription_id' => (int) $id,
                'tenant_id'       => $tenantId,
                'plan_id'         => $planId,
            ], $tenantId);
            return ['success' => true, 'id' => (int) $id];
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['created_by'] = get_staff_user_id() ?: null;
        $this->landlord_db()
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->update(db_prefix() . 'kt_saas_subscriptions', [
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => get_staff_user_id() ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => get_staff_user_id() ?: null,
            ]);
        $db->insert(db_prefix() . 'kt_saas_subscriptions', $payload);
        $subscriptionId = (int) $db->insert_id();
        $db->where('id', (int) $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
            'plan_id'    => $planId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->sync_tenant_module_registry($tenantId);
        $this->log_activity('subscription.created', 'info', [
            'subscription_id' => $subscriptionId,
            'tenant_id'       => $tenantId,
            'plan_id'         => $planId,
        ], $tenantId);
        return ['success' => true, 'id' => $subscriptionId];
    }

    public function get_invoices()
    {
        return $this->landlord_db()
            ->select('i.*, t.tenant_code, t.company_name, t.subdomain, t.custom_domain, s.status as subscription_status, p.plan_name')
            ->from(db_prefix() . 'kt_saas_invoices i')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = i.tenant_id', 'left')
            ->join(db_prefix() . 'kt_saas_subscriptions s', 's.id = i.subscription_id', 'left')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('i.deleted_at IS NULL', null, false)
            ->order_by('i.id', 'desc')
            ->get()
            ->result_array();
    }

    public function get_invoice($id)
    {
        return $this->landlord_db()
            ->select('i.*, t.tenant_code, t.company_name, t.subdomain, t.custom_domain, s.status as subscription_status, s.plan_id, p.plan_name')
            ->from(db_prefix() . 'kt_saas_invoices i')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = i.tenant_id', 'left')
            ->join(db_prefix() . 'kt_saas_subscriptions s', 's.id = i.subscription_id', 'left')
            ->join(db_prefix() . 'kt_saas_plans p', 'p.id = s.plan_id', 'left')
            ->where('i.id', (int) $id)
            ->where('i.deleted_at IS NULL', null, false)
            ->get()
            ->row_array();
    }

    public function send_email_event($eventKey, array $context = [], array $options = [])
    {
        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEmailProviderService.php');
        $service = new TenantEmailProviderService();

        return $service->sendRegisteredEventEmail($eventKey, $context, $options);
    }

    public function get_recent_invoices($limit = 20)
    {
        return $this->landlord_db()
            ->select('i.*, t.tenant_code, t.company_name, t.subdomain, t.custom_domain')
            ->from(db_prefix() . 'kt_saas_invoices i')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = i.tenant_id', 'left')
            ->where('i.deleted_at IS NULL', null, false)
            ->order_by('i.id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function get_overdue_invoices($limit = 100)
    {
        return $this->landlord_db()
            ->select('i.*, t.tenant_code, t.company_name, t.subdomain, t.custom_domain')
            ->from(db_prefix() . 'kt_saas_invoices i')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = i.tenant_id', 'left')
            ->where('i.deleted_at IS NULL', null, false)
            ->where_in('i.status', ['issued', 'pending_payment', 'partial', 'overdue'])
            ->where('i.due_date IS NOT NULL', null, false)
            ->where('i.due_date <', date('Y-m-d'))
            ->order_by('i.id', 'asc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function get_payments()
    {
        return $this->landlord_db()
            ->select('p.*, i.invoice_number, t.tenant_code, t.company_name')
            ->from(db_prefix() . 'kt_saas_payments p')
            ->join(db_prefix() . 'kt_saas_invoices i', 'i.id = p.invoice_id', 'left')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = p.tenant_id', 'left')
            ->where('p.deleted_at IS NULL', null, false)
            ->order_by('p.id', 'desc')
            ->get()
            ->result_array();
    }

    public function get_recent_payments($limit = 20)
    {
        return $this->landlord_db()
            ->select('p.*, i.invoice_number, t.tenant_code, t.company_name')
            ->from(db_prefix() . 'kt_saas_payments p')
            ->join(db_prefix() . 'kt_saas_invoices i', 'i.id = p.invoice_id', 'left')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = p.tenant_id', 'left')
            ->where('p.deleted_at IS NULL', null, false)
            ->order_by('p.id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->result_array();
    }

    public function update_invoice($invoiceId, array $payload)
    {
        $payload['updated_at'] = $payload['updated_at'] ?? date('Y-m-d H:i:s');
        $payload['updated_by'] = array_key_exists('updated_by', $payload) ? $payload['updated_by'] : (get_staff_user_id() ?: null);

        $this->landlord_db()->where('id', (int) $invoiceId)->update(db_prefix() . 'kt_saas_invoices', $payload);

        return $this->landlord_db()->affected_rows() >= 0;
    }

    public function update_subscription($subscriptionId, array $payload)
    {
        $payload['updated_at'] = $payload['updated_at'] ?? date('Y-m-d H:i:s');
        $payload['updated_by'] = array_key_exists('updated_by', $payload) ? $payload['updated_by'] : (get_staff_user_id() ?: null);

        $this->landlord_db()->where('id', (int) $subscriptionId)->update(db_prefix() . 'kt_saas_subscriptions', $payload);

        return $this->landlord_db()->affected_rows() >= 0;
    }

    public function update_tenant($tenantId, array $payload)
    {
        $payload['updated_at'] = $payload['updated_at'] ?? date('Y-m-d H:i:s');
        $payload['updated_by'] = array_key_exists('updated_by', $payload) ? $payload['updated_by'] : (get_staff_user_id() ?: null);

        $this->landlord_db()->where('id', (int) $tenantId)->update(db_prefix() . 'kt_saas_tenants', $payload);

        return $this->landlord_db()->affected_rows() >= 0;
    }

    public function set_subscription_status($subscriptionId, $status)
    {
        $subscription = $this->get_subscription((int) $subscriptionId);
        if (!$subscription) {
            return false;
        }

        $status = trim((string) $status);
        if (!array_key_exists($status, kt_saas_subscription_statuses())) {
            return false;
        }

        $tenantId = (int) ($subscription['tenant_id'] ?? 0);
        $this->update_subscription((int) $subscriptionId, ['status' => $status]);

        if ($tenantId > 0) {
            $tenantStatus = $status;
            if ($status === 'cancelled' || $status === 'expired') {
                $tenantStatus = 'suspended';
            }
            if (array_key_exists($tenantStatus, kt_saas_tenant_statuses())) {
                $this->update_tenant($tenantId, ['status' => $tenantStatus]);
            }
            $this->sync_tenant_module_registry($tenantId);
        }

        $this->log_activity('subscription.status_changed', 'warning', [
            'subscription_id' => (int) $subscriptionId,
            'tenant_id'       => $tenantId ?: null,
            'status'          => $status,
        ], $tenantId ?: null);

        if ($status === 'expired' && $tenantId > 0) {
            $tenant = $this->get_tenant($tenantId);
            $plan = $this->get_plan((int) ($subscription['plan_id'] ?? 0));
            if ($tenant) {
                $this->send_email_event('tenant_subscription_expired', [
                    'tenant_id' => $tenantId,
                    'tenant' => $tenant,
                    'subscription' => $subscription,
                    'plan' => $plan ?: [],
                    'owner_name' => (string) ($tenant['owner_name'] ?? $tenant['company_name'] ?? ''),
                    'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                    'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                    'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                    'trial_end_date' => (string) ($subscription['trial_ends_at'] ?? ''),
                    'subscription_status' => 'expired',
                    'workspace_url' => function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '',
                    'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                    'plan_name' => (string) ($plan['plan_name'] ?? ''),
                    'related_type' => 'subscription',
                    'related_id' => (string) $subscriptionId,
                    'dedupe_key' => 'tenant_subscription_expired|' . $tenantId . '|' . (int) $subscriptionId . '|status_expired',
                ]);
            }
        }

        return true;
    }

    public function create_payment(array $payload)
    {
        $now = date('Y-m-d H:i:s');
        $paymentReference = trim((string) ($payload['payment_reference'] ?? ''));
        if ($paymentReference !== '') {
            $existing = $this->get_payment_by_reference($paymentReference);
            if ($existing) {
                return (int) $existing['id'];
            }
        }

        $record = [
            'tenant_id'             => (int) ($payload['tenant_id'] ?? 0),
            'invoice_id'            => !empty($payload['invoice_id']) ? (int) $payload['invoice_id'] : null,
            'payment_reference'     => $paymentReference,
            'gateway'               => trim((string) ($payload['gateway'] ?? 'manual')),
            'status'                => trim((string) ($payload['status'] ?? 'paid')),
            'amount'                => (float) ($payload['amount'] ?? 0),
            'currency'              => trim((string) ($payload['currency'] ?? 'USD')),
            'gateway_payload_json'  => isset($payload['gateway_payload_json']) ? $payload['gateway_payload_json'] : null,
            'paid_at'               => array_key_exists('paid_at', $payload) ? $payload['paid_at'] : (((string) ($payload['status'] ?? 'paid')) === 'paid' ? $now : null),
            'failed_at'             => $payload['failed_at'] ?? null,
            'created_at'            => $now,
            'updated_at'            => $now,
        ];

        $this->landlord_db()->insert(db_prefix() . 'kt_saas_payments', $record);
        return (int) $this->landlord_db()->insert_id();
    }

    public function get_payment_by_reference($paymentReference)
    {
        $paymentReference = trim((string) $paymentReference);
        if ($paymentReference === '') {
            return null;
        }

        return $this->landlord_db()
            ->where('payment_reference', $paymentReference)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_payments')
            ->row_array();
    }

    public function get_domains()
    {
        return $this->landlord_db()
            ->select('d.*, t.tenant_code, t.company_name')
            ->from(db_prefix() . 'kt_saas_domains d')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = d.tenant_id', 'left')
            ->where('d.deleted_at IS NULL', null, false)
            ->order_by('d.id', 'desc')
            ->get()
            ->result_array();
    }

    public function get_domain($id)
    {
        return $this->landlord_db()
            ->where('id', (int) $id)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_domains')
            ->row_array();
    }

    public function save_domain($data, $id = null)
    {
        $db = $this->landlord_db();
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $domain = strtolower(trim((string) ($data['domain'] ?? '')));
        $domainType = trim((string) ($data['domain_type'] ?? 'subdomain'));

        if (!$this->get_tenant($tenantId) || $domain === '') {
            return ['success' => false, 'message' => 'Tenant and domain are required.'];
        }

        if ($this->domainExists($domain, $id)) {
            return ['success' => false, 'message' => 'Domain already exists.'];
        }

        $payload = [
            'tenant_id'    => $tenantId,
            'domain'       => $domain,
            'domain_type'  => $domainType,
            'is_primary'   => isset($data['is_primary']) ? 1 : 0,
            'readiness_status' => trim((string) ($data['readiness_status'] ?? 'pending')),
            'expected_target'  => trim((string) ($data['expected_target'] ?? '')) ?: null,
            'ssl_status'   => trim((string) ($data['ssl_status'] ?? 'pending')),
            'dns_status'   => trim((string) ($data['dns_status'] ?? 'pending')),
            'last_checked_at' => !empty($data['last_checked_at']) ? $data['last_checked_at'] : null,
            'verified_at'  => !empty($data['verified_at']) ? $data['verified_at'] . ' 00:00:00' : null,
            'verification_message' => trim((string) ($data['verification_message'] ?? '')) ?: null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if ($payload['is_primary']) {
            $db->where('tenant_id', $tenantId)->update(db_prefix() . 'kt_saas_domains', ['is_primary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        if ($id) {
            $db->where('id', (int) $id)->update(db_prefix() . 'kt_saas_domains', $payload);
            $this->log_activity('domain.updated', 'info', ['domain_id' => (int) $id, 'tenant_id' => $tenantId, 'domain' => $domain], $tenantId);
            return ['success' => true, 'id' => (int) $id];
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $db->insert(db_prefix() . 'kt_saas_domains', $payload);
        $domainId = (int) $db->insert_id();
        $this->log_activity('domain.created', 'info', ['domain_id' => $domainId, 'tenant_id' => $tenantId, 'domain' => $domain], $tenantId);
        return ['success' => true, 'id' => $domainId];
    }

    public function save_domain_verification($id, array $verification)
    {
        $domain = $this->get_domain($id);
        if (!$domain) {
            return false;
        }

        $payload = [
            'readiness_status' => trim((string) ($verification['readiness_status'] ?? $domain['readiness_status'] ?? 'pending')),
            'expected_target'  => trim((string) ($verification['expected_target'] ?? $domain['expected_target'] ?? '')) ?: null,
            'dns_status'  => trim((string) ($verification['dns_status'] ?? $domain['dns_status'])),
            'ssl_status'  => trim((string) ($verification['ssl_status'] ?? $domain['ssl_status'])),
            'last_checked_at' => !empty($verification['checked_at']) ? $verification['checked_at'] : date('Y-m-d H:i:s'),
            'verified_at' => !empty($verification['verified_at']) ? $verification['verified_at'] : null,
            'dns_records_json' => isset($verification['dns_records']) ? json_encode($verification['dns_records'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : ($domain['dns_records_json'] ?? null),
            'ssl_details_json' => isset($verification['ssl_details']) ? json_encode($verification['ssl_details'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : ($domain['ssl_details_json'] ?? null),
            'verification_message' => trim((string) ($verification['message'] ?? '')) ?: null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $this->landlord_db()->where('id', (int) $id)->update(db_prefix() . 'kt_saas_domains', $payload);

        $this->log_activity('domain.verified', !empty($verification['success']) ? 'info' : 'warning', [
            'domain_id'     => (int) $id,
            'tenant_id'     => (int) $domain['tenant_id'],
            'domain'        => $domain['domain'],
            'readiness_status' => $payload['readiness_status'],
            'expected_target' => $payload['expected_target'],
            'dns_status'    => $payload['dns_status'],
            'ssl_status'    => $payload['ssl_status'],
            'resolved_ips'  => $verification['resolved_ips'] ?? [],
            'expected_ips'  => $verification['expected_ips'] ?? [],
            'dns_records'   => $verification['dns_records'] ?? [],
            'ssl_details'   => $verification['ssl_details'] ?? [],
            'message'       => $payload['verification_message'],
            'ssl_message'   => $verification['ssl_message'] ?? '',
            'checked_at'    => $verification['checked_at'] ?? date('Y-m-d H:i:s'),
        ], (int) $domain['tenant_id']);

        return true;
    }

    public function get_domains_needing_verification($limit = 50, $staleHours = 12)
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) $staleHours) . ' hours'));

        return $this->db
            ->select('d.*, t.tenant_code, t.company_name')
            ->from(db_prefix() . 'kt_saas_domains d')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = d.tenant_id', 'left')
            ->where('d.deleted_at IS NULL', null, false)
            ->group_start()
                ->where('d.last_checked_at IS NULL', null, false)
                ->or_where('d.last_checked_at <', $threshold)
                ->or_where_in('d.readiness_status', ['pending', 'dns_pending', 'ssl_pending', 'attention'])
            ->group_end()
            ->order_by('d.id', 'asc')
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();
    }

    public function get_provision_jobs()
    {
        $db = $this->landlord_db();
        $db->select('j.*, t.tenant_code, t.company_name');
        $db->from(db_prefix() . 'kt_saas_provision_jobs j');
        $db->join(db_prefix() . 'kt_saas_tenants t', 't.id = j.tenant_id', 'left');
        $db->order_by('j.id', 'desc');
        return $db->get()->result_array();
    }

    private function is_core_tenant_provision_job($jobType): bool
    {
        return trim((string) $jobType) === 'provision_tenant';
    }

    public function create_provision_job($tenantId, $jobType, array $payload)
    {
        $db = $this->landlord_db();
        $now = date('Y-m-d H:i:s');
        $isCoreTenantProvision = $this->is_core_tenant_provision_job($jobType);
        if ($tenantId) {
            $tenant = $this->get_tenant((int) $tenantId);
            if ($tenant && (string) ($tenant['status'] ?? '') === 'archived') {
                $this->log_activity('provision.job_skip_archived_tenant', 'warning', [
                    'tenant_id' => (int) $tenantId,
                    'job_type'  => $jobType,
                ], (int) $tenantId);

                return 0;
            }
        }

        // A ready tenant must not be re-queued as a new core provisioning job.
        // Explicit rebuild/reprovision flows use their own retry path.
        if ($tenantId && $isCoreTenantProvision && empty($payload['allow_reprovision'])) {
            $tenant = $tenant ?: $this->get_tenant((int) $tenantId);
            if ($tenant
                && strtolower(trim((string) ($tenant['status'] ?? ''))) === 'active'
                && strtolower(trim((string) ($tenant['provisioning_status'] ?? ''))) === 'done'
                && $this->tenantConnectionMetadataReady($tenant)
            ) {
                $completed = $db
                    ->where('tenant_id', (int) $tenantId)
                    ->where('job_type', $jobType)
                    ->where_in('status', ['done', 'completed'])
                    ->order_by('id', 'desc')
                    ->limit(1)
                    ->get(db_prefix() . 'kt_saas_provision_jobs')
                    ->row_array();

                return (int) ($completed['id'] ?? 0);
            }
        }

        $existing = $db
            ->where('tenant_id', $tenantId ?: null)
            ->where('job_type', $jobType)
            ->where_in('status', ['queued', 'running'])
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_provision_jobs')
            ->row_array();

        if ($existing) {
            if ($tenantId && $isCoreTenantProvision) {
                $db->where('id', (int) $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
                    'provisioning_status' => $existing['status'] === 'running' ? 'running' : 'queued',
                    'updated_at'          => $now,
                    'updated_by'          => get_staff_user_id() ?: null,
                ]);
            }

            return (int) $existing['id'];
        }

        $db->insert(db_prefix() . 'kt_saas_provision_jobs', [
            'tenant_id'     => $tenantId ?: null,
            'job_type'      => $jobType,
            'status'        => 'queued',
            'attempts'      => 0,
            'max_attempts'  => 5,
            'payload_json'  => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'scheduled_at'  => $now,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $jobId = (int) $db->insert_id();

        if ($tenantId && $isCoreTenantProvision) {
            $db->where('id', (int) $tenantId)->update(db_prefix() . 'kt_saas_tenants', [
                'provisioning_status' => 'queued',
                'updated_at'          => $now,
                'updated_by'          => get_staff_user_id() ?: null,
            ]);
        }

        $this->log_activity('provision.job_queued', 'info', [
            'job_id'     => $jobId,
            'tenant_id'  => $tenantId ?: null,
            'job_type'   => $jobType,
        ], $tenantId ?: null);

        return $jobId;
    }

    public function retry_provision_job($jobId)
    {
        $db = $this->landlord_db();
        $job = $db->where('id', (int) $jobId)->get(db_prefix() . 'kt_saas_provision_jobs')->row_array();
        if (!$job) {
            return false;
        }
        $isCoreTenantProvision = $this->is_core_tenant_provision_job((string) ($job['job_type'] ?? ''));

        $db->where('id', (int) $jobId)->update(db_prefix() . 'kt_saas_provision_jobs', [
            'status'        => 'queued',
            'error_message' => null,
            'result_json'   => null,
            'started_at'    => null,
            'finished_at'   => null,
            'scheduled_at'  => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        if (!empty($job['tenant_id']) && $isCoreTenantProvision) {
            $db->where('id', (int) $job['tenant_id'])->update(db_prefix() . 'kt_saas_tenants', [
                'provisioning_status' => 'queued',
                'updated_at'          => date('Y-m-d H:i:s'),
                'updated_by'          => get_staff_user_id() ?: null,
            ]);
        }

        $this->log_activity('provision.job_requeued', 'warning', [
            'job_id'    => (int) $jobId,
            'tenant_id' => !empty($job['tenant_id']) ? (int) $job['tenant_id'] : null,
        ], !empty($job['tenant_id']) ? (int) $job['tenant_id'] : null);

        return true;
    }

    public function mark_provision_job_running($jobId)
    {
        $db = $this->landlord_db();
        $now = date('Y-m-d H:i:s');
        $job = $db->where('id', (int) $jobId)->get(db_prefix() . 'kt_saas_provision_jobs')->row_array();
        if (!$job) {
            return null;
        }

        if (!in_array((string) $job['status'], ['queued', 'failed'], true)) {
            return null;
        }
        $isCoreTenantProvision = $this->is_core_tenant_provision_job((string) ($job['job_type'] ?? ''));

        $db->where('id', (int) $jobId)->update(db_prefix() . 'kt_saas_provision_jobs', [
            'status'     => 'running',
            'attempts'   => (int) $job['attempts'] + 1,
            'started_at' => $now,
            'finished_at'=> null,
            'updated_at' => $now,
        ]);

        if (!empty($job['tenant_id']) && $isCoreTenantProvision) {
            $db->where('id', (int) $job['tenant_id'])->update(db_prefix() . 'kt_saas_tenants', [
                'provisioning_status' => 'running',
                'last_provisioned_at' => $now,
                'updated_at'          => $now,
            ]);
        }

        $job['status'] = 'running';
        $job['attempts'] = (int) $job['attempts'] + 1;
        $job['started_at'] = $now;
        return $job;
    }

    public function mark_provision_job_done($jobId, array $result = [])
    {
        $db = $this->landlord_db();
        $now = date('Y-m-d H:i:s');
        $job = $db->where('id', (int) $jobId)->get(db_prefix() . 'kt_saas_provision_jobs')->row_array();
        if (!$job) {
            return false;
        }
        $isCoreTenantProvision = $this->is_core_tenant_provision_job((string) ($job['job_type'] ?? ''));

        $tenantReady = true;
        $tenantStatus = 'active';
        if (!empty($job['tenant_id']) && $isCoreTenantProvision) {
            $tenant = $this->get_tenant((int) $job['tenant_id']);
            $tenantReady = $tenant ? $this->tenantConnectionMetadataReady($tenant) : false;
            $tenantStatus = $tenantReady ? $this->normalizeTenantActiveState((int) $job['tenant_id']) : 'draft';
        }

        $storedResult = $this->redactProvisionOnboardingSecrets($result);

        $db->where('id', (int) $jobId)->update(db_prefix() . 'kt_saas_provision_jobs', [
            'status'      => $tenantReady ? 'done' : 'failed',
            'error_message' => $tenantReady ? null : 'Provision job completed without valid tenant database metadata.',
            'result_json' => json_encode($storedResult, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'finished_at' => $now,
            'updated_at'  => $now,
        ]);

        if (!empty($job['tenant_id']) && $isCoreTenantProvision) {
            $db->where('id', (int) $job['tenant_id'])->update(db_prefix() . 'kt_saas_tenants', [
                'provisioning_status' => $tenantReady ? 'done' : 'failed',
                'status'              => $tenantStatus,
                'last_provisioned_at' => $now,
                'updated_at'          => $now,
            ]);
        }

        $this->log_activity($tenantReady ? 'provision.job_done' : 'provision.job_failed', $tenantReady ? 'info' : 'danger', [
            'job_id'    => (int) $jobId,
            'tenant_id' => !empty($job['tenant_id']) ? (int) $job['tenant_id'] : null,
            'result'    => $storedResult,
            'tenant_ready' => $tenantReady,
        ], !empty($job['tenant_id']) ? (int) $job['tenant_id'] : null);

        if ($tenantReady && !empty($job['tenant_id']) && $isCoreTenantProvision) {
            $tenant = $this->get_tenant((int) $job['tenant_id']);
            if ($tenant) {
                $plan = !empty($tenant['plan_id']) ? $this->get_plan((int) $tenant['plan_id']) : null;
                $workspaceUrl = function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '';
                $tenantAdmin = isset($result['tenant_admin']) && is_array($result['tenant_admin']) ? $result['tenant_admin'] : [];
                $baseContext = [
                    'tenant_id' => (int) $tenant['id'],
                    'tenant' => $tenant,
                    'plan' => $plan ?: [],
                    'owner_name' => (string) ($tenant['owner_name'] ?? ''),
                    'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                    'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                    'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                    'workspace_name' => (string) ($tenant['company_name'] ?? ($tenant['tenant_name'] ?? '')),
                    'workspace_url' => $workspaceUrl,
                    'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                    'plan_name' => (string) ($plan['plan_name'] ?? ''),
                    'set_password_url' => (string) ($tenantAdmin['set_password_url'] ?? ''),
                    'admin_login_url' => (string) ($tenantAdmin['admin_login_url'] ?? ($workspaceUrl !== '' ? $workspaceUrl . '/' . trim(get_admin_uri(), '/') . '/authentication' : '')),
                    'support_email' => $this->onboardingSupportEmail(),
                    'password_link_expires_in' => '48 giờ',
                    'related_type' => 'tenant',
                    'related_id' => (string) $tenant['id'],
                ];

                $provisionGuard = isset($result['email_event_guard']) && is_array($result['email_event_guard']) ? $result['email_event_guard'] : null;
                $provisionContext = $baseContext;
                if ($provisionGuard) {
                    $provisionContext['email_event_guard'] = $provisionGuard;
                    $provisionContext['dedupe_key'] = (string) ($provisionGuard['dedupe_key'] ?? '');
                }

                $result['email_dispatch']['provisioning_completed'] = $this->send_email_event('provisioning_completed', $provisionContext);

                $welcomeContext = $baseContext;
                $result['email_dispatch']['tenant_welcome'] = $this->send_email_event('tenant_welcome', $welcomeContext);
            }
        }

        return true;
    }

    protected function redactProvisionOnboardingSecrets(array $result)
    {
        foreach (['tenant_admin', 'onboarding'] as $key) {
            if (!isset($result[$key]) || !is_array($result[$key])) {
                continue;
            }

            $linkGenerated = !empty($result[$key]['set_password_url']) || !empty($result[$key]['new_pass_key']);
            unset($result[$key]['new_pass_key'], $result[$key]['set_password_url']);
            if ($linkGenerated) {
                $result[$key]['onboarding_link_generated'] = true;
            }
        }

        return $this->redactSensitiveRuntimeContext($result);
    }

    protected function redactSensitiveRuntimeContext($value, $key = '')
    {
        $sensitiveKeys = [
            'new_pass_key',
            'set_password_url',
            'reset_password_url',
            'password_reset_token',
            'password_setup_token',
        ];

        if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $this->redactSensitiveRuntimeContext($childValue, $childKey);
            }

            return $value;
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        return preg_replace(
            '~(/authentication/(?:set|reset)_password/[^/?#\s]+/[^/?#\s]+/)[^/?#\s]+~i',
            '$1[redacted]',
            $value
        );
    }

    protected function onboardingSupportEmail()
    {
        foreach (['smtp_email', 'email_from_address', 'companyemail'] as $option) {
            $value = trim((string) get_option($option));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function mark_provision_job_failed($jobId, $errorMessage, array $result = [])
    {
        $db = $this->landlord_db();
        $now = date('Y-m-d H:i:s');
        $job = $db->where('id', (int) $jobId)->get(db_prefix() . 'kt_saas_provision_jobs')->row_array();
        if (!$job) {
            return false;
        }
        $isCoreTenantProvision = $this->is_core_tenant_provision_job((string) ($job['job_type'] ?? ''));

        $storedResult = $this->redactProvisionOnboardingSecrets($result);

        $db->where('id', (int) $jobId)->update(db_prefix() . 'kt_saas_provision_jobs', [
            'status'        => 'failed',
            'error_message' => $errorMessage,
            'result_json'   => json_encode($storedResult, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'finished_at'   => $now,
            'updated_at'    => $now,
        ]);

        if (!empty($job['tenant_id']) && $isCoreTenantProvision) {
            $tenant = $this->get_tenant((int) $job['tenant_id']);
            $tenantStatus = $tenant && !$this->tenantConnectionMetadataReady($tenant) ? 'draft' : (($tenant['status'] ?? 'draft'));
            if (in_array($tenantStatus, ['active', 'trial', 'grace'], true)) {
                $tenantStatus = 'draft';
            }
            $db->where('id', (int) $job['tenant_id'])->update(db_prefix() . 'kt_saas_tenants', [
                'provisioning_status' => 'failed',
                'status'              => $tenantStatus,
                'updated_at'          => $now,
            ]);
        }

        $this->log_activity('provision.job_failed', 'danger', [
            'job_id'    => (int) $jobId,
            'tenant_id' => !empty($job['tenant_id']) ? (int) $job['tenant_id'] : null,
            'error'     => $errorMessage,
        ], !empty($job['tenant_id']) ? (int) $job['tenant_id'] : null);

        if (!empty($job['tenant_id']) && $isCoreTenantProvision) {
            $tenant = $this->get_tenant((int) $job['tenant_id']);
            if ($tenant) {
                $plan = !empty($tenant['plan_id']) ? $this->get_plan((int) $tenant['plan_id']) : null;
                $workspaceUrl = function_exists('kt_saas_tenant_public_base_url') ? rtrim((string) kt_saas_tenant_public_base_url($tenant), '/') : '';
                $opsEmails = trim((string) get_option('bcc_emails'));
                $cc = [];
                if ($opsEmails !== '') {
                    $cc = array_values(array_filter(array_map('trim', explode(',', $opsEmails))));
                }

                $context = [
                    'tenant_id' => (int) $tenant['id'],
                    'tenant' => $tenant,
                    'plan' => $plan ?: [],
                    'owner_name' => (string) ($tenant['owner_name'] ?? ''),
                    'owner_email' => (string) ($tenant['owner_email'] ?? ''),
                    'tenant_name' => (string) ($tenant['company_name'] ?? ''),
                    'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
                    'workspace_url' => $workspaceUrl,
                    'workspace_domain' => trim((string) ($tenant['custom_domain'] ?? $tenant['subdomain'] ?? '')),
                    'plan_name' => (string) ($plan['plan_name'] ?? ''),
                    'error_message' => (string) $errorMessage,
                    'related_type' => 'tenant',
                    'related_id' => (string) $tenant['id'],
                ];

                if (!empty($cc)) {
                    $context['cc'] = $cc;
                }

                $this->send_email_event('provisioning_failed', $context);
            }
        }

        return true;
    }

    public function get_due_provision_jobs($limit = 20)
    {
        $now = date('Y-m-d H:i:s');

        $this->db
            ->select('j.*')
            ->from(db_prefix() . 'kt_saas_provision_jobs j')
            ->join(db_prefix() . 'kt_saas_tenants t', 't.id = j.tenant_id', 'left')
            ->where('j.status', 'queued')
            ->group_start()
            ->where('j.tenant_id IS NULL', null, false)
            ->or_group_start()
            ->where('t.id IS NOT NULL', null, false)
            ->where('t.status !=', 'archived')
            ->group_end()
            ->group_end();
        $this->db->group_start();
        $this->db->where('j.scheduled_at IS NULL', null, false);
        $this->db->or_where('j.scheduled_at <=', $now);
        $this->db->group_end();
        $this->db->order_by('j.id', 'asc');
        return $this->db->limit((int) $limit)->get()->result_array();
    }

    public function repair_provisioning_state_consistency()
    {
        $now = date('Y-m-d H:i:s');
        $repairs = [
            'jobs_done'        => 0,
            'jobs_failed'      => 0,
            'jobs_running'     => 0,
            'jobs_queued'      => 0,
            'tenants_failed'   => 0,
            'tenants_draft'    => 0,
            'tenants_active'   => 0,
        ];

        $jobs = $this->db->order_by('id', 'asc')->get(db_prefix() . 'kt_saas_provision_jobs')->result_array();
        foreach ($jobs as $job) {
            $jobId = (int) $job['id'];
            if (!$this->is_core_tenant_provision_job((string) ($job['job_type'] ?? ''))) {
                continue;
            }
            $tenant = !empty($job['tenant_id']) ? $this->get_tenant((int) $job['tenant_id']) : null;
            $tenantReady = $tenant ? $this->tenantConnectionMetadataReady($tenant) : false;
            $resultJson = trim((string) ($job['result_json'] ?? ''));
            $hasResult = $resultJson !== '' && $resultJson !== 'null';
            $hasFinished = !empty($job['finished_at']);
            $hasStarted = !empty($job['started_at']);
            $status = (string) $job['status'];
            $tenantHasCompletedJob = false;

            if (!empty($job['tenant_id'])) {
                $tenantHasCompletedJob = $this->db
                    ->where('tenant_id', (int) $job['tenant_id'])
                    ->where('job_type', 'provision_tenant')
                    ->where('status', 'done')
                    ->where('id !=', $jobId)
                    ->count_all_results(db_prefix() . 'kt_saas_provision_jobs') > 0;
            }

            $targetStatus = $status;
            if (in_array($status, ['queued', 'running'], true) && $tenantReady && $tenantHasCompletedJob) {
                $targetStatus = 'failed';
            } elseif ($status === 'queued' && $hasFinished) {
                $targetStatus = ($hasResult && $tenantReady) ? 'done' : 'failed';
            } elseif ($status === 'queued' && $hasStarted && !$hasFinished) {
                $targetStatus = 'running';
            } elseif ($status === 'done' && !$tenantReady) {
                $targetStatus = 'failed';
            }

            if ($targetStatus !== $status) {
                $update = [
                    'status'     => $targetStatus,
                    'updated_at' => $now,
                ];
                if ($targetStatus === 'running') {
                    $update['finished_at'] = null;
                }
                if ($targetStatus === 'failed' && empty($job['error_message'])) {
                    $update['error_message'] = 'Repaired inconsistent provisioning job state.';
                }

                $this->db->where('id', $jobId)->update(db_prefix() . 'kt_saas_provision_jobs', $update);
                $repairs['jobs_' . $targetStatus]++;
            }
        }

        $db = $this->landlord_db();
        $tenants = $db->order_by('id', 'asc')->get(db_prefix() . 'kt_saas_tenants')->result_array();
        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];
            $update = [];
            if (trim((string) ($tenant['db_name'] ?? '')) === '') {
                $update['db_name'] = $this->generateTenantDatabaseName((string) ($tenant['tenant_code'] ?? ''), $tenantId, $tenant['subdomain'] ?? null);
                $tenant['db_name'] = $update['db_name'];
            }

            $tenantReady = $this->tenantConnectionMetadataReady($tenant);
            $status = (string) $tenant['status'];
            $provisioningStatus = (string) $tenant['provisioning_status'];

            $openJob = $db
                ->where('tenant_id', $tenantId)
                ->where('job_type', 'provision_tenant')
                ->where_in('status', ['queued', 'running'])
                ->order_by('id', 'desc')
                ->get(db_prefix() . 'kt_saas_provision_jobs')
                ->row_array();

            if (!$tenantReady && $provisioningStatus === 'done') {
                $provisioningStatus = 'failed';
            } elseif ($openJob) {
                $provisioningStatus = (string) $openJob['status'] === 'running' ? 'running' : 'queued';
            }

            if ($provisioningStatus !== 'done' && in_array($status, ['active', 'trial', 'grace'], true)) {
                $status = 'draft';
            } elseif ($provisioningStatus === 'done' && $tenantReady) {
                $status = $this->normalizeTenantActiveState($tenantId);
            }

            if ($provisioningStatus !== (string) $tenant['provisioning_status']) {
                $update['provisioning_status'] = $provisioningStatus;
            }
            if ($status !== (string) $tenant['status']) {
                $update['status'] = $status;
            }

            if (!empty($update)) {
                $update['updated_at'] = $now;
                $update['updated_by'] = get_staff_user_id() ?: null;
                $db->where('id', $tenantId)->update(db_prefix() . 'kt_saas_tenants', $update);
                if (($update['provisioning_status'] ?? null) === 'failed') {
                    $repairs['tenants_failed']++;
                }
                if (($update['status'] ?? null) === 'draft') {
                    $repairs['tenants_draft']++;
                }
                if (($update['status'] ?? null) === 'active') {
                    $repairs['tenants_active']++;
                }
            }
        }

        $this->log_activity('provision.state_repaired', 'warning', $repairs);

        return $repairs;
    }

    public function log_activity($eventKey, $severity = 'info', array $context = [], $tenantId = null)
    {
        $context = $this->redactSensitiveRuntimeContext($context);

        $this->landlord_db()->insert(db_prefix() . 'kt_saas_activity_logs', [
            'tenant_id'   => $tenantId ?: null,
            'actor_type'  => is_staff_logged_in() ? 'staff' : 'system',
            'actor_id'    => is_staff_logged_in() ? get_staff_user_id() : null,
            'event_key'   => $eventKey,
            'severity'    => $severity,
            'ip_address'  => $this->input->ip_address(),
            'user_agent'  => substr((string) $this->input->user_agent(), 0, 255),
            'context_json'=> json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function sync_tenant_domains($tenantId, $subdomain = null, $customDomain = null)
    {
        $baseDomain = trim((string) kt_saas_get_option('kt_saas_base_domain', 'crm.local'));

        if ($subdomain) {
            $fullDomain = $subdomain;
            if ($baseDomain !== '' && strpos($subdomain, '.') === false) {
                $fullDomain = $subdomain . '.' . $baseDomain;
            }
            $this->upsert_domain_record($tenantId, $fullDomain, 'subdomain', 1);
        }

        if ($customDomain) {
            $this->upsert_domain_record($tenantId, $customDomain, 'custom', empty($subdomain) ? 1 : 0);
        }
    }

    private function upsert_domain_record($tenantId, $domain, $domainType, $isPrimary)
    {
        $db = $this->landlord_db();
        $existing = $db
            ->where('tenant_id', (int) $tenantId)
            ->where('domain', $domain)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_domains')
            ->row_array();

        if ($isPrimary) {
            $db->where('tenant_id', (int) $tenantId)->update(db_prefix() . 'kt_saas_domains', [
                'is_primary' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $payload = [
            'tenant_id'   => (int) $tenantId,
            'domain'      => strtolower(trim((string) $domain)),
            'domain_type' => $domainType,
            'is_primary'  => $isPrimary ? 1 : 0,
            'ssl_status'  => 'pending',
            'dns_status'  => 'pending',
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $db->where('id', (int) $existing['id'])->update(db_prefix() . 'kt_saas_domains', $payload);
            return (int) $existing['id'];
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $db->insert(db_prefix() . 'kt_saas_domains', $payload);
        return (int) $db->insert_id();
    }

    private function ensure_tenant_subscription($tenantId, $planId)
    {
        if (!$planId) {
            return;
        }

        if ($this->get_current_subscription((int) $tenantId)) {
            return;
        }

        $plan = $this->get_plan($planId);
        $status = ((int) ($plan['trial_days'] ?? 0) > 0) ? 'trial' : 'active';
        $now = date('Y-m-d H:i:s');
        $trialEndsAt = ((int) ($plan['trial_days'] ?? 0) > 0) ? date('Y-m-d 23:59:59', strtotime('+' . (int) $plan['trial_days'] . ' days')) : null;
        $timeline = $this->buildSubscriptionTimeline($plan, $status, new DateTimeImmutable($now));

        $this->landlord_db()->insert(db_prefix() . 'kt_saas_subscriptions', [
            'tenant_id'               => (int) $tenantId,
            'plan_id'                 => (int) $planId,
            'status'                  => $status,
            'billing_cycle'           => $plan['billing_cycle'] ?? 'monthly',
            'started_at'              => $now,
            'trial_ends_at'           => $trialEndsAt,
            'current_period_start_at' => $timeline['current_period_start_at'],
            'current_period_end_at'   => $timeline['current_period_end_at'],
            'next_billing_at'         => $timeline['next_billing_at'],
            'auto_renew'              => 1,
            'created_at'              => $now,
            'updated_at'              => $now,
            'created_by'              => get_staff_user_id() ?: null,
        ]);
    }

    private function normalizeTenantActiveState($tenantId)
    {
        $tenant = $this->get_tenant($tenantId);
        if (!$tenant) {
            return 'draft';
        }
        if (in_array($tenant['status'], ['suspended', 'terminated', 'archived'], true)) {
            return $tenant['status'];
        }

        $subscription = $this->get_current_subscription((int) $tenantId);

        if (!$subscription) {
            return 'active';
        }

        return in_array($subscription['status'], ['trial', 'grace'], true) ? $subscription['status'] : 'active';
    }

    public function get_current_subscription($tenantId)
    {
        $rows = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'kt_saas_subscriptions')
            ->result_array();

        if (!$rows) {
            return null;
        }

        usort($rows, function ($a, $b) {
            $scoreA = $this->subscriptionPriorityScore($a);
            $scoreB = $this->subscriptionPriorityScore($b);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            $dateA = strtotime((string) ($a['current_period_end_at'] ?: $a['trial_ends_at'] ?: $a['grace_ends_at'] ?: $a['updated_at'] ?: $a['created_at'] ?: $a['started_at'] ?: '1970-01-01 00:00:00'));
            $dateB = strtotime((string) ($b['current_period_end_at'] ?: $b['trial_ends_at'] ?: $b['grace_ends_at'] ?: $b['updated_at'] ?: $b['created_at'] ?: $b['started_at'] ?: '1970-01-01 00:00:00'));
            if ($dateA !== $dateB) {
                return $dateB <=> $dateA;
            }

            return ((int) $b['id']) <=> ((int) $a['id']);
        });

        return $rows[0];
    }

    private function normalizeTenantStateOnSave(array $payload, array $current = [])
    {
        $merged = array_merge($current, $payload);
        $status = trim((string) ($payload['status'] ?? ($current['status'] ?? 'draft')));
        $provisioningStatus = trim((string) ($payload['provisioning_status'] ?? ($current['provisioning_status'] ?? 'queued')));

        if (!$this->tenantConnectionMetadataReady($merged) && $provisioningStatus === 'done') {
            $provisioningStatus = 'queued';
        }

        if ($provisioningStatus !== 'done' && in_array($status, ['active', 'trial', 'grace'], true)) {
            $status = 'draft';
        }

        $payload['status'] = $status;
        $payload['provisioning_status'] = $provisioningStatus;

        return $payload;
    }

    private function buildSubscriptionTimeline(array $plan, $status, ?DateTimeImmutable $from = null)
    {
        $from = $from ?: new DateTimeImmutable(date('Y-m-d H:i:s'));
        $cycle = trim((string) ($plan['billing_cycle'] ?? 'monthly'));

        if ($status !== 'active') {
            return [
                'current_period_start_at' => null,
                'current_period_end_at'   => null,
                'next_billing_at'         => null,
            ];
        }

        $end = $this->nextBillingDateForCycle($cycle, $from);

        return [
            'current_period_start_at' => $from->format('Y-m-d H:i:s'),
            'current_period_end_at'   => $end->format('Y-m-d H:i:s'),
            'next_billing_at'         => $end->format('Y-m-d H:i:s'),
        ];
    }

    private function nextBillingDateForCycle($cycle, DateTimeImmutable $from)
    {
        switch (trim((string) $cycle)) {
            case 'yearly':
                return $from->modify('+1 year');
            case 'quarterly':
                return $from->modify('+3 months');
            case 'monthly':
            default:
                return $from->modify('+1 month');
        }
    }

    private function subscriptionPriorityScore(array $subscription)
    {
        $status = trim((string) ($subscription['status'] ?? ''));
        switch ($status) {
            case 'active':
                return 500;
            case 'trial':
                return 450;
            case 'grace':
                return 400;
            case 'suspended':
                return 300;
            case 'cancelled':
                return 200;
            case 'terminated':
                return 100;
            default:
                return 50;
        }
    }

    public function tenantConnectionMetadataReady(array $tenant)
    {
        return trim((string) ($tenant['db_name'] ?? '')) !== ''
            && trim((string) ($tenant['db_host'] ?? '')) !== ''
            && trim((string) ($tenant['db_user'] ?? '')) !== '';
    }

    private function generateTenantDatabaseName($tenantCode, $tenantId, $subdomain = null)
    {
        $base = strtolower(trim((string) $subdomain));
        if ($base === '') {
            $base = strtolower(trim((string) $tenantCode));
        }

        $base = preg_replace('/[^a-z0-9]+/', '_', $base);
        $base = trim((string) $base, '_');
        if ($base === '') {
            $base = 'tenant';
        }

        $name = APP_DB_NAME . '_tenant_' . $base . '_' . (int) $tenantId;
        return substr($name, 0, 64);
    }

    private function sync_all_tenant_module_registries($planId = null)
    {
        $this->sync_module_catalog();

        $this->landlord_db()
            ->where('deleted_at IS NULL', null, false);
        if ($planId !== null) {
            $this->landlord_db()->where('plan_id', (int) $planId);
        }

        $tenants = $this->landlord_db()->get(db_prefix() . 'kt_saas_tenants')->result_array();
        foreach ($tenants as $tenant) {
            $this->sync_tenant_module_registry((int) $tenant['id'], $tenant);
        }
    }

    private function sync_tenant_module_registry($tenantId, array $tenant = null)
    {
        $tenant = $tenant ?: $this->get_tenant((int) $tenantId);
        if (!$tenant) {
            return;
        }

        $this->sync_module_catalog();

        $subscription = $this->get_current_subscription((int) $tenantId);
        $plan = null;
        if ($subscription) {
            $plan = $this->get_plan((int) ($subscription['plan_id'] ?? 0));
        }
        if (!$plan && !empty($tenant['plan_id'])) {
            $plan = $this->get_plan((int) $tenant['plan_id']);
        }

        $planId = (int) ($plan['id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $catalog = $this->get_module_catalog();

        $planFeatures = [];
        if ($planId > 0) {
            $featureRows = $this->landlord_db()
                ->where('plan_id', $planId)
                ->get(db_prefix() . 'kt_saas_plan_features')
                ->result_array();
            foreach ($featureRows as $row) {
                $planFeatures[$row['module_name']] = (int) $row['is_enabled'] === 1;
            }
        }

        $overrideRows = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('feature_key LIKE', '%.access')
            ->get(db_prefix() . 'kt_saas_tenant_entitlements')
            ->result_array();
        $overrides = [];
        foreach ($overrideRows as $row) {
            $overrides[$row['module_name']] = [
                'enabled'    => (int) $row['is_enabled'] === 1,
                'overridden' => (int) $row['overridden'] === 1,
            ];
        }

        $existingRows = $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_modules')
            ->result_array();
        $existing = [];
        foreach ($existingRows as $row) {
            $existing[$row['module_name']] = $row;
        }

        $tenantRuntimeModules = [
            KT_SAAS_MODULE => true,
        ];

        foreach ($catalog as $module) {
            $moduleName = (string) $module['module_name'];
            $globalActive = (int) ($module['is_global_active'] ?? 1) === 1;
            $planAllowed = $planId > 0 ? !empty($planFeatures[$moduleName]) : false;
            $override = $overrides[$moduleName] ?? null;
            $status = 'disabled';
            $source = 'plan';

            if ($override && $override['overridden']) {
                $status = $override['enabled'] && $globalActive ? 'enabled' : 'disabled';
                $source = 'override';
            } elseif ($globalActive && $planAllowed) {
                $status = 'enabled';
                $source = 'plan';
            } elseif (!$globalActive) {
                $status = 'disabled';
                $source = 'catalog';
            }

            $payload = [
                'tenant_id'        => (int) $tenantId,
                'module_name'      => $moduleName,
                'module_type'      => ((int) ($module['is_core'] ?? 0) === 1) ? 'core' : 'addon',
                'status'           => $status,
                'source'           => $source,
                'price'            => 0.00,
                'dependency_json'  => json_encode([], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'notes'            => null,
                'updated_at'       => $now,
                'deleted_at'       => null,
            ];
            $tenantRuntimeModules[$moduleName] = $status === 'enabled';

            if (!isset($existing[$moduleName])) {
                $payload['created_at'] = $now;
                $this->landlord_db()->insert(db_prefix() . 'kt_saas_modules', $payload);
                continue;
            }

            $this->landlord_db()->where('id', (int) $existing[$moduleName]['id'])->update(db_prefix() . 'kt_saas_modules', $payload);
        }

        $this->sync_tenant_runtime_modules($tenant, $tenantRuntimeModules);
    }

    private function sync_tenant_runtime_modules(array $tenant, array $moduleStates)
    {
        $tenantDb = $this->connect_tenant_runtime_db($tenant);
        if (!$tenantDb) {
            return;
        }

        $modulesTable = db_prefix() . 'modules';
        if (!$tenantDb->table_exists($modulesTable)) {
            return;
        }

        $physicalModules = [];
        foreach ($this->app_modules->get() as $module) {
            $systemName = strtolower(trim((string) ($module['system_name'] ?? '')));
            if ($systemName === '') {
                continue;
            }

            $physicalModules[$systemName] = [
                'module_name'       => $systemName,
                'installed_version' => (string) ($module['headers']['version'] ?? '0.0.0'),
            ];
        }

        if (!isset($physicalModules[KT_SAAS_MODULE])) {
            $physicalModules[KT_SAAS_MODULE] = [
                'module_name'       => KT_SAAS_MODULE,
                'installed_version' => defined('KT_SAAS_VERSION') ? KT_SAAS_VERSION : '0.0.0',
            ];
        }

        $existingRows = $tenantDb->get($modulesTable)->result_array();
        $existing = [];
        foreach ($existingRows as $row) {
            $existing[strtolower((string) $row['module_name'])] = $row;
        }

        foreach ($physicalModules as $moduleName => $moduleData) {
            $active = !empty($moduleStates[$moduleName]) ? 1 : 0;
            $payload = [
                'module_name'       => $moduleName,
                'installed_version' => $moduleData['installed_version'],
                'active'            => $active,
            ];

            if (isset($existing[$moduleName])) {
                $tenantDb->where('module_name', $moduleName)->update($modulesTable, $payload);
                continue;
            }

            $tenantDb->insert($modulesTable, $payload);
        }

        $this->ensure_tenant_module_schema($tenant, $tenantDb, $moduleStates);
    }

    private function ensure_tenant_module_schema(array $tenant, $tenantDb, array $moduleStates)
    {
        foreach (['goals', 'kt_inventory', 'kt_sepay'] as $moduleName) {
            if (empty($moduleStates[$moduleName])) {
                continue;
            }

            $this->run_tenant_module_installer($tenant, $tenantDb, $moduleName);
        }
    }

    private function run_tenant_module_installer(array $tenant, $tenantDb, $moduleName)
    {
        $moduleName = strtolower(trim((string) $moduleName));
        if ($moduleName === '') {
            return;
        }

        $installPath = module_dir_path($moduleName, 'install.php');
        if (!file_exists($installPath)) {
            return;
        }

        $CI = &get_instance();
        $originalDb = $CI->db;
        $originalModelDb = $this->db;

        $CI->db = $tenantDb;
        $this->db = $tenantDb;

        try {
            if ($moduleName === 'kt_sepay') {
                require_once $installPath;
                if (function_exists('kt_sepay_run_install')) {
                    kt_sepay_run_install();
                }
            } else {
                include $installPath;
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'KT SaaS tenant module installer failed for tenant [' . ($tenant['tenant_code'] ?? 'unknown') . '] module [' . $moduleName . ']: ' . $e->getMessage()
            );
        }

        $CI->db = $originalDb;
        $this->db = $originalModelDb;
    }

    private function connect_tenant_runtime_db(array $tenant)
    {
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        $dbHost = trim((string) ($tenant['db_host'] ?? ''));
        $dbUser = trim((string) ($tenant['db_user'] ?? ''));
        $dbPort = trim((string) ($tenant['db_port'] ?? '3306'));
        $encryptedPassword = $tenant['db_password_encrypted'] ?? null;

        if ($dbName === '' || $dbHost === '' || $dbUser === '' || empty($encryptedPassword)) {
            return null;
        }

        if (!isset($this->encryption)) {
            $this->load->library('encryption');
        }

        $password = $this->encryption->decrypt($encryptedPassword);
        if ($password === false) {
            return null;
        }

        $config = [
            'dsn'          => '',
            'hostname'     => $dbHost,
            'username'     => $dbUser,
            'password'     => $password,
            'database'     => $dbName,
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => false,
            'cache_on'     => false,
            'cachedir'     => '',
            'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre'     => '',
            'encrypt'      => false,
            'compress'     => false,
            'stricton'     => false,
            'failover'     => [],
            'save_queries' => false,
            'port'         => $dbPort,
        ];

        try {
            $tenantDb = $this->load->database($config, true);
            $tenantDb->initialize();
            return $tenantDb;
        } catch (Throwable $e) {
            log_message('error', 'KT SaaS tenant module sync DB connection failed for tenant [' . ($tenant['tenant_code'] ?? 'unknown') . ']: ' . $e->getMessage());
            return null;
        }
    }

    public function save_settings($data)
    {
        $globalSenderEmail = trim((string) ($data['kt_saas_global_sender_email'] ?? ''));
        if ($globalSenderEmail !== '' && !filter_var($globalSenderEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Global sender email is invalid.'];
        }

        $globalReplyToEmail = trim((string) ($data['kt_saas_global_reply_to_email'] ?? ''));
        if ($globalReplyToEmail !== '' && !filter_var($globalReplyToEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Global reply-to email is invalid.'];
        }

        update_option('kt_saas_base_domain', trim((string) ($data['kt_saas_base_domain'] ?? '')));
        update_option('kt_saas_default_db_host', trim((string) ($data['kt_saas_default_db_host'] ?? '127.0.0.1')));
        update_option('kt_saas_default_db_port', trim((string) ($data['kt_saas_default_db_port'] ?? '3306')));
        update_option('kt_saas_default_locale', trim((string) ($data['kt_saas_default_locale'] ?? 'english')));
        update_option('kt_saas_default_timezone', trim((string) ($data['kt_saas_default_timezone'] ?? 'UTC')));
        update_option('kt_saas_default_currency', trim((string) ($data['kt_saas_default_currency'] ?? 'USD')));
        update_option('kt_saas_default_storage_driver', trim((string) ($data['kt_saas_default_storage_driver'] ?? 'local')));
        update_option('kt_saas_queue_mode', trim((string) ($data['kt_saas_queue_mode'] ?? 'database')));
        update_option('kt_saas_auto_create_db_user', isset($data['kt_saas_auto_create_db_user']) ? '1' : '0');
        update_option('kt_saas_db_user_prefix', trim((string) ($data['kt_saas_db_user_prefix'] ?? 'tenant_')));
        update_option('kt_saas_default_db_client_hosts', trim((string) ($data['kt_saas_default_db_client_hosts'] ?? 'localhost,127.0.0.1')));
        update_option('kt_saas_allow_custom_domains', isset($data['kt_saas_allow_custom_domains']) ? '1' : '0');
        update_option('kt_saas_runtime_enabled', isset($data['kt_saas_runtime_enabled']) ? '1' : '0');
        update_option('kt_saas_landlord_host', trim((string) ($data['kt_saas_landlord_host'] ?? parse_url(APP_BASE_URL, PHP_URL_HOST))));
        update_option('kt_saas_usage_retention_days', (string) max((int) ($data['kt_saas_usage_retention_days'] ?? 90), 7));
        update_option('kt_saas_backup_retention_days', (string) max((int) ($data['kt_saas_backup_retention_days'] ?? 30), 1));
        update_option('kt_saas_billing_due_days', (string) max((int) ($data['kt_saas_billing_due_days'] ?? 7), 0));
        update_option('kt_saas_billing_dunning_interval_days', (string) max((int) ($data['kt_saas_billing_dunning_interval_days'] ?? 2), 1));
        update_option('kt_saas_billing_dunning_max_attempts', (string) max((int) ($data['kt_saas_billing_dunning_max_attempts'] ?? 3), 1));
        update_option('kt_saas_payment_link_secret', trim((string) ($data['kt_saas_payment_link_secret'] ?? APP_ENC_KEY)));
        update_option('kt_saas_payment_webhook_secret', trim((string) ($data['kt_saas_payment_webhook_secret'] ?? APP_ENC_KEY)));
        update_option('kt_saas_overage_rate_json', trim((string) ($data['kt_saas_overage_rate_json'] ?? '')));
        $globalProvider = trim((string) ($data['kt_saas_global_email_provider'] ?? 'system_smtp'));
        if (!in_array($globalProvider, ['system_smtp', 'brevo_smtp', 'brevo_api'], true)) {
            $globalProvider = 'system_smtp';
        }

        if ($globalProvider === 'brevo_api') {
            $existingApiKeyEnc = (string) get_option('kt_saas_global_brevo_api_key_enc');
            $newApiKey = isset($data['kt_saas_global_brevo_api_key']) ? trim((string) $data['kt_saas_global_brevo_api_key']) : '';
            if ($newApiKey === '' && $existingApiKeyEnc === '') {
                return ['success' => false, 'message' => 'Brevo API key is required for Brevo API provider.'];
            }
        }

        update_option('kt_saas_global_email_provider', $globalProvider);
        update_option('kt_saas_global_sender_name', trim((string) ($data['kt_saas_global_sender_name'] ?? '')));
        update_option('kt_saas_global_sender_email', trim((string) ($data['kt_saas_global_sender_email'] ?? '')));
        update_option('kt_saas_global_reply_to_email', trim((string) ($data['kt_saas_global_reply_to_email'] ?? '')));
        update_option('kt_saas_global_email_fallback_policy', trim((string) ($data['kt_saas_global_email_fallback_policy'] ?? 'use_landlord')));
        if ($globalProvider === 'brevo_smtp') {
            update_option('kt_saas_global_brevo_smtp_host', trim((string) ($data['kt_saas_global_brevo_smtp_host'] ?? 'smtp-relay.brevo.com')));
            update_option('kt_saas_global_brevo_smtp_port', trim((string) ($data['kt_saas_global_brevo_smtp_port'] ?? '587')));
            update_option('kt_saas_global_brevo_smtp_encryption', trim((string) ($data['kt_saas_global_brevo_smtp_encryption'] ?? 'tls')));
            update_option('kt_saas_global_brevo_smtp_user', trim((string) ($data['kt_saas_global_brevo_smtp_user'] ?? '')));
            if (isset($data['kt_saas_global_brevo_smtp_pass']) && trim((string) $data['kt_saas_global_brevo_smtp_pass']) !== '') {
                update_option('kt_saas_global_brevo_smtp_pass_enc', $this->encryption->encrypt(trim((string) $data['kt_saas_global_brevo_smtp_pass'])));
            }
        } elseif ($globalProvider === 'brevo_api') {
            update_option('kt_saas_global_brevo_sender_name', trim((string) ($data['kt_saas_global_brevo_sender_name'] ?? '')));
            update_option('kt_saas_global_brevo_sender_email', trim((string) ($data['kt_saas_global_brevo_sender_email'] ?? '')));
            update_option('kt_saas_global_brevo_reply_to_email', trim((string) ($data['kt_saas_global_brevo_reply_to_email'] ?? '')));
            if (isset($data['kt_saas_global_brevo_api_key']) && trim((string) $data['kt_saas_global_brevo_api_key']) !== '') {
                update_option('kt_saas_global_brevo_api_key_enc', $this->encryption->encrypt(trim((string) $data['kt_saas_global_brevo_api_key'])));
            }
        }

        return ['success' => true, 'message' => 'Global email settings saved.'];
    }

    public function ensure_tenant_email_schema()
    {
        $db = $this->landlord_db();
        $charset = 'utf8mb4';
        $collation = 'utf8mb4_unicode_ci';

        $tenantEmailTable = db_prefix() . 'kt_saas_tenant_email_settings';
        if (!$db->table_exists($tenantEmailTable)) {
            $db->query("CREATE TABLE `{$tenantEmailTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` BIGINT UNSIGNED NOT NULL,
                `config_scope` VARCHAR(30) NOT NULL DEFAULT 'tenant_custom',
                `provider` VARCHAR(30) NOT NULL DEFAULT 'system_smtp',
                `auth_type` VARCHAR(30) NOT NULL DEFAULT 'smtp_password',
                `provider_status` VARCHAR(30) NOT NULL DEFAULT 'inactive',
                `fallback_policy` VARCHAR(30) NOT NULL DEFAULT 'use_landlord',
                `sender_name` VARCHAR(191) NULL,
                `sender_email` VARCHAR(191) NULL,
                `reply_to_email` VARCHAR(191) NULL,
                `smtp_host` VARCHAR(191) NULL,
                `smtp_port` INT NULL,
                `smtp_encryption` VARCHAR(10) NULL,
                `smtp_username` VARCHAR(191) NULL,
                `smtp_password_encrypted` TEXT NULL,
                `brevo_api_key_encrypted` TEXT NULL,
                `brevo_sender_id` VARCHAR(191) NULL,
                `daily_quota` INT NOT NULL DEFAULT 0,
                `monthly_quota` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT NOT NULL DEFAULT 0,
                `last_test_status` VARCHAR(30) NULL,
                `last_test_message` TEXT NULL,
                `last_test_at` DATETIME NULL,
                `last_verified_at` DATETIME NULL,
                `verified_by` INT NULL,
                `failed_count` INT NOT NULL DEFAULT 0,
                `last_error_code` VARCHAR(50) NULL,
                `last_error_message` TEXT NULL,
                `sender_domain` VARCHAR(191) NULL,
                `domain_verified` TINYINT NOT NULL DEFAULT 0,
                `dkim_status` VARCHAR(30) NULL,
                `spf_status` VARCHAR(30) NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `tenant_email_unique` (`tenant_id`),
                KEY `tenant_id_idx` (`tenant_id`),
                KEY `provider_status_idx` (`provider_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};");
        }

        $logsTable = db_prefix() . 'kt_saas_email_logs';
        if (!$db->table_exists($logsTable)) {
            $db->query("CREATE TABLE `{$logsTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` BIGINT UNSIGNED NULL,
                `provider` VARCHAR(40) NOT NULL,
                `email_type` VARCHAR(30) NOT NULL DEFAULT 'transactional',
                `from_email` VARCHAR(191) NULL,
                `recipient` VARCHAR(191) NOT NULL,
                `subject` TEXT NULL,
                `status` VARCHAR(30) NOT NULL,
                `error_message` TEXT NULL,
                `message_id` VARCHAR(191) NULL,
                `related_type` VARCHAR(50) NULL,
                `related_id` VARCHAR(191) NULL,
                `sent_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tenant_idx` (`tenant_id`),
                KEY `status_idx` (`status`),
                KEY `created_idx` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};");
        } else {
            foreach ([
                'from_email' => "ALTER TABLE `{$logsTable}` ADD COLUMN `from_email` VARCHAR(191) NULL AFTER `email_type`",
                'related_type' => "ALTER TABLE `{$logsTable}` ADD COLUMN `related_type` VARCHAR(50) NULL AFTER `message_id`",
                'related_id' => "ALTER TABLE `{$logsTable}` ADD COLUMN `related_id` VARCHAR(191) NULL AFTER `related_type`",
            ] as $column => $sql) {
                if (!$db->field_exists($column, $logsTable)) {
                    $db->query($sql);
                }
            }
        }

        $auditTable = db_prefix() . 'kt_saas_tenant_email_config_audit';
        if (!$db->table_exists($auditTable)) {
            $db->query("CREATE TABLE `{$auditTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` BIGINT UNSIGNED NOT NULL,
                `changed_by` INT NULL,
                `action` VARCHAR(50) NOT NULL,
                `changed_fields` LONGTEXT NULL,
                `ip_address` VARCHAR(64) NULL,
                `user_agent` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `tenant_idx` (`tenant_id`),
                KEY `action_idx` (`action`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};");
        }

        $guardTable = db_prefix() . 'kt_saas_email_event_guards';
        if (!$db->table_exists($guardTable)) {
            $db->query("CREATE TABLE `{$guardTable}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_key` VARCHAR(100) NOT NULL,
                `dedupe_key` VARCHAR(64) NOT NULL,
                `tenant_id` BIGINT UNSIGNED NULL,
                `resource_type` VARCHAR(100) NULL,
                `resource_id` VARCHAR(191) NULL,
                `recipient_scope` VARCHAR(50) NOT NULL DEFAULT 'tenant_admin',
                `branding_context` VARCHAR(30) NOT NULL DEFAULT 'landlord',
                `provider_context` VARCHAR(50) NOT NULL DEFAULT 'landlord_global',
                `status` VARCHAR(30) NOT NULL DEFAULT 'reserved',
                `context_json` LONGTEXT NULL,
                `last_error_message` TEXT NULL,
                `reserved_at` DATETIME NULL,
                `sent_at` DATETIME NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `event_guard_unique` (`event_key`, `dedupe_key`),
                KEY `tenant_idx` (`tenant_id`),
                KEY `status_idx` (`status`),
                KEY `event_key_idx` (`event_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};");
        } else {
            $guardColumns = [
                'resource_type' => "ALTER TABLE `{$guardTable}` ADD COLUMN `resource_type` VARCHAR(100) NULL AFTER `tenant_id`",
                'resource_id' => "ALTER TABLE `{$guardTable}` ADD COLUMN `resource_id` VARCHAR(191) NULL AFTER `resource_type`",
                'recipient_scope' => "ALTER TABLE `{$guardTable}` ADD COLUMN `recipient_scope` VARCHAR(50) NOT NULL DEFAULT 'tenant_admin' AFTER `resource_id`",
                'branding_context' => "ALTER TABLE `{$guardTable}` ADD COLUMN `branding_context` VARCHAR(30) NOT NULL DEFAULT 'landlord' AFTER `recipient_scope`",
                'provider_context' => "ALTER TABLE `{$guardTable}` ADD COLUMN `provider_context` VARCHAR(50) NOT NULL DEFAULT 'landlord_global' AFTER `branding_context`",
                'status' => "ALTER TABLE `{$guardTable}` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'reserved' AFTER `provider_context`",
                'context_json' => "ALTER TABLE `{$guardTable}` ADD COLUMN `context_json` LONGTEXT NULL AFTER `status`",
                'last_error_message' => "ALTER TABLE `{$guardTable}` ADD COLUMN `last_error_message` TEXT NULL AFTER `context_json`",
                'reserved_at' => "ALTER TABLE `{$guardTable}` ADD COLUMN `reserved_at` DATETIME NULL AFTER `last_error_message`",
                'sent_at' => "ALTER TABLE `{$guardTable}` ADD COLUMN `sent_at` DATETIME NULL AFTER `reserved_at`",
                'updated_at' => "ALTER TABLE `{$guardTable}` ADD COLUMN `updated_at` DATETIME NOT NULL AFTER `sent_at`",
            ];

            foreach ($guardColumns as $column => $sql) {
                if (!$db->field_exists($column, $guardTable)) {
                    $db->query($sql);
                }
            }
        }

        $planFeaturesTable = db_prefix() . 'kt_saas_plan_features';
        if ($db->table_exists($planFeaturesTable) && !$db->field_exists('feature_value', $planFeaturesTable)) {
            $db->query("ALTER TABLE `{$planFeaturesTable}` ADD COLUMN `feature_value` VARCHAR(191) NULL AFTER `is_enabled`;");
        }
    }

    public function get_active_tenant_email_setting($tenantId)
    {
        $this->ensure_tenant_email_schema();
        return $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->where('is_active', 1)
            ->get(db_prefix() . 'kt_saas_tenant_email_settings')
            ->row_array();
    }

    public function get_tenant_email_setting($tenantId)
    {
        $this->ensure_tenant_email_schema();
        return $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->get(db_prefix() . 'kt_saas_tenant_email_settings')
            ->row_array();
    }

    public function set_tenant_email_provider_status($tenantId, $status, $message = '')
    {
        $this->ensure_tenant_email_schema();
        $this->landlord_db()
            ->where('tenant_id', (int) $tenantId)
            ->update(db_prefix() . 'kt_saas_tenant_email_settings', [
                'provider_status' => (string) $status,
                'last_error_message' => $message !== '' ? (string) $message : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function save_tenant_email_settings($tenantId, array $data, array $entitlements = [])
    {
        $this->ensure_tenant_email_schema();
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return ['success' => false, 'message' => 'Invalid tenant.'];
        }

        $ownCredentials = !empty($entitlements['own_credentials']);
        $customSender = !empty($entitlements['custom_sender']);
        $customSmtp = !empty($entitlements['custom_smtp']) || !empty($entitlements['brevo_smtp']);
        $brevoApi = !empty($entitlements['brevo_api']);
        if (!$ownCredentials && !$customSender) {
            return ['success' => false, 'message' => 'Plan does not allow tenant email configuration.'];
        }

        $existing = $this->landlord_db()->where('tenant_id', $tenantId)->get(db_prefix() . 'kt_saas_tenant_email_settings')->row_array();
        $isActive = isset($data['is_active']);
        $provider = trim((string) ($data['provider'] ?? 'system_smtp'));
        $provider = in_array($provider, ['system_smtp', 'brevo_smtp', 'brevo_api'], true) ? $provider : 'system_smtp';
        if (!$ownCredentials) {
            $provider = 'system_smtp';
        } elseif ($provider === 'brevo_api' && !$brevoApi) {
            return ['success' => false, 'message' => 'Plan does not allow Brevo API provider.'];
        } elseif (in_array($provider, ['system_smtp', 'brevo_smtp'], true) && !$customSmtp) {
            return ['success' => false, 'message' => 'Plan does not allow custom SMTP provider.'];
        }

        $senderEmail = trim((string) ($data['sender_email'] ?? ''));
        if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Tenant sender email is invalid.'];
        }
        $replyToEmail = trim((string) ($data['reply_to_email'] ?? ''));
        if ($replyToEmail !== '' && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Tenant reply-to email is invalid.'];
        }
        if ($isActive && $provider === 'brevo_api') {
            $newApiKey = trim((string) ($data['brevo_api_key'] ?? ''));
            if ($newApiKey === '' && empty($existing['brevo_api_key_encrypted'])) {
                return ['success' => false, 'message' => 'Brevo API key is required for Brevo API provider.'];
            }
        }

        $payload = [
            'tenant_id' => $tenantId,
            'config_scope' => 'tenant_custom',
            'provider' => $provider,
            'auth_type' => $provider === 'brevo_api' ? 'api_key' : 'smtp_password',
            'provider_status' => $isActive ? 'active' : 'inactive',
            'fallback_policy' => trim((string) ($data['fallback_policy'] ?? 'use_landlord')) === 'block_sending' ? 'block_sending' : 'use_landlord',
            'sender_name' => trim((string) ($data['sender_name'] ?? '')),
            'sender_email' => trim((string) ($data['sender_email'] ?? '')),
            'reply_to_email' => trim((string) ($data['reply_to_email'] ?? '')),
            'smtp_host' => trim((string) ($data['smtp_host'] ?? '')),
            'smtp_port' => (int) ($data['smtp_port'] ?? 0),
            'smtp_encryption' => trim((string) ($data['smtp_encryption'] ?? '')),
            'smtp_username' => trim((string) ($data['smtp_username'] ?? '')),
            'brevo_sender_id' => trim((string) ($data['brevo_sender_id'] ?? '')),
            'daily_quota' => max(0, (int) ($data['daily_quota'] ?? 0)),
            'monthly_quota' => max(0, (int) ($data['monthly_quota'] ?? 0)),
            'is_active' => $isActive ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($provider === 'brevo_api') {
            $payload['smtp_host'] = null;
            $payload['smtp_port'] = null;
            $payload['smtp_encryption'] = null;
            $payload['smtp_username'] = null;
        } else {
            $payload['brevo_sender_id'] = null;
        }

        if (!$ownCredentials && $customSender) {
            $payload['provider'] = 'system_smtp';
            $payload['smtp_host'] = null;
            $payload['smtp_port'] = null;
            $payload['smtp_encryption'] = null;
            $payload['smtp_username'] = null;
            $payload['brevo_sender_id'] = null;
            $payload['is_active'] = 1;
            $payload['provider_status'] = 'active';
            if (!empty($existing['smtp_password_encrypted'])) {
                $payload['smtp_password_encrypted'] = $existing['smtp_password_encrypted'];
            }
            if (!empty($existing['brevo_api_key_encrypted'])) {
                $payload['brevo_api_key_encrypted'] = $existing['brevo_api_key_encrypted'];
            }
        } else {
            if ($provider !== 'brevo_api') {
                $smtpPass = trim((string) ($data['smtp_password'] ?? ''));
                if ($smtpPass !== '') {
                    $payload['smtp_password_encrypted'] = $this->encryption->encrypt($smtpPass);
                } elseif (!empty($existing['smtp_password_encrypted'])) {
                    $payload['smtp_password_encrypted'] = $existing['smtp_password_encrypted'];
                }
            }

            if ($provider === 'brevo_api') {
                $apiKey = trim((string) ($data['brevo_api_key'] ?? ''));
                if ($apiKey !== '') {
                    $payload['brevo_api_key_encrypted'] = $this->encryption->encrypt($apiKey);
                } elseif (!empty($existing['brevo_api_key_encrypted'])) {
                    $payload['brevo_api_key_encrypted'] = $existing['brevo_api_key_encrypted'];
                }
            }
        }

        if (!$existing) {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $this->landlord_db()->insert(db_prefix() . 'kt_saas_tenant_email_settings', $payload);
            $action = 'create';
        } else {
            $this->landlord_db()->where('tenant_id', $tenantId)->update(db_prefix() . 'kt_saas_tenant_email_settings', $payload);
            $action = 'update';
        }

        $this->landlord_db()->insert(db_prefix() . 'kt_saas_tenant_email_config_audit', [
            'tenant_id' => $tenantId,
            'changed_by' => get_staff_user_id() ?: null,
            'action' => $action,
            'changed_fields' => json_encode(array_keys($payload), JSON_UNESCAPED_UNICODE),
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr((string) ($this->input->user_agent() ?? ''), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Tenant email settings saved.'];
    }

    private function tenantCodeExists($tenantCode, $excludeId = null)
    {
        $db = $this->landlord_db();
        $db->where('tenant_code', $tenantCode);
        if ($excludeId) {
            $db->where('id !=', (int) $excludeId);
        }
        return $db->count_all_results(db_prefix() . 'kt_saas_tenants') > 0;
    }

    private function subdomainExists($subdomain, $excludeId = null)
    {
        $db = $this->landlord_db();
        $db->where('subdomain', $subdomain);
        if ($excludeId) {
            $db->where('id !=', (int) $excludeId);
        }
        return $db->count_all_results(db_prefix() . 'kt_saas_tenants') > 0;
    }

    private function customDomainExists($customDomain, $excludeId = null)
    {
        $db = $this->landlord_db();
        $db->where('custom_domain', $customDomain);
        if ($excludeId) {
            $db->where('id !=', (int) $excludeId);
        }
        return $db->count_all_results(db_prefix() . 'kt_saas_tenants') > 0;
    }

    private function dbNameExists($dbName, $excludeId = null)
    {
        $db = $this->landlord_db();
        $db->where('db_name', $dbName);
        if ($excludeId) {
            $db->where('id !=', (int) $excludeId);
        }
        return $db->count_all_results(db_prefix() . 'kt_saas_tenants') > 0;
    }

    private function dbUserExists($dbUser, $excludeId = null)
    {
        $db = $this->landlord_db();
        $db->where('db_user', $dbUser);
        if ($excludeId) {
            $db->where('id !=', (int) $excludeId);
        }
        return $db->count_all_results(db_prefix() . 'kt_saas_tenants') > 0;
    }

    private function sanitizeTenantCode($value)
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^A-Z0-9\-]/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim((string) $value, '-');
    }

    private function slugifyTenantValue($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim((string) $value, '-');
    }

    private function isValidSubdomain($value)
    {
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?$/', (string) $value);
    }

    private function sanitizeDbName($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9_]/', '_', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function isValidDbName($value)
    {
        $value = (string) $value;
        return $value !== '' && strlen($value) <= 64 && (bool) preg_match('/^[a-z0-9_]+$/', $value);
    }

    private function sanitizeDbUser($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9_]/', '_', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function isValidDbUser($value)
    {
        $value = (string) $value;
        return $value !== '' && strlen($value) <= 32 && (bool) preg_match('/^[a-z0-9_]+$/', $value);
    }

    private function generateTenantCode()
    {
        return 'TEN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function generateUniqueTenantCode($excludeId = null)
    {
        for ($i = 0; $i < 20; $i++) {
            $code = $this->generateTenantCode();
            if (!$this->tenantCodeExists($code, $excludeId)) {
                return $code;
            }
        }
        return $this->generateTenantCode() . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    private function generateUniqueSubdomain($baseSlug, $excludeId = null)
    {
        $baseSlug = $this->slugifyTenantValue($baseSlug);
        if ($baseSlug === '') {
            $baseSlug = 'tenant';
        }
        $candidate = substr($baseSlug, 0, 50);
        if ($this->isValidSubdomain($candidate) && !$this->isReservedSubdomain($candidate) && !$this->subdomainExists($candidate, $excludeId) && !$this->tenantDomainExists($candidate, $excludeId)) {
            return $candidate;
        }
        for ($i = 0; $i < 20; $i++) {
            $suffix = substr(strtolower(bin2hex(random_bytes(3))), 0, 5);
            $trial = substr($baseSlug, 0, 56) . '-' . $suffix;
            $trial = trim($trial, '-');
            if ($this->isValidSubdomain($trial) && !$this->isReservedSubdomain($trial) && !$this->subdomainExists($trial, $excludeId) && !$this->tenantDomainExists($trial, $excludeId)) {
                return $trial;
            }
        }
        for ($i = 0; $i < 10; $i++) {
            $fallback = 'tenant-' . substr(strtolower(bin2hex(random_bytes(3))), 0, 6);
            if (!$this->isReservedSubdomain($fallback) && !$this->subdomainExists($fallback, $excludeId) && !$this->tenantDomainExists($fallback, $excludeId)) {
                return $fallback;
            }
        }
        return 'tenant-' . substr(strtolower(bin2hex(random_bytes(3))), 0, 6);
    }

    private function generateDbName($slug)
    {
        $slug = $this->sanitizeDbName($slug);
        if ($slug === '') {
            $slug = 'tenant';
        }
        $base = 'kt_tenant_' . $slug . '_' . substr(strtolower(bin2hex(random_bytes(3))), 0, 6);
        return substr($base, 0, 64);
    }

    private function generateUniqueDbName($slug, $excludeId = null)
    {
        for ($i = 0; $i < 30; $i++) {
            $name = $this->generateDbName($slug);
            if (!$this->dbNameExists($name, $excludeId)) {
                return $name;
            }
        }
        return substr('kt_tenant_' . substr(strtolower(bin2hex(random_bytes(8))), 0, 24), 0, 64);
    }

    private function generateDbUser($slug)
    {
        $slug = $this->sanitizeDbUser($slug);
        if ($slug === '') {
            $slug = 'tenant';
        }
        $slug = substr($slug, 0, 18);
        $base = 'ktu_' . $slug . '_' . substr(strtolower(bin2hex(random_bytes(3))), 0, 6);
        return substr($base, 0, 32);
    }

    private function generateUniqueDbUser($slug, $excludeId = null)
    {
        for ($i = 0; $i < 30; $i++) {
            $user = $this->generateDbUser($slug);
            if (!$this->dbUserExists($user, $excludeId)) {
                return $user;
            }
        }
        return substr('ktu_' . substr(strtolower(bin2hex(random_bytes(12))), 0, 28), 0, 32);
    }

    private function generateStrongPassword($length = 24)
    {
        $length = max((int) $length, 20);
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnpqrstuvwxyz',
            '23456789',
            '!@#$%^&*()_+-=',
        ];
        $password = '';
        foreach ($sets as $set) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }
        $pool = implode('', $sets);
        while (strlen($password) < $length) {
            $password .= $pool[random_int(0, strlen($pool) - 1)];
        }
        return substr(str_shuffle($password), 0, $length);
    }

    private function planCodeExists($planCode, $excludeId = null)
    {
        $db = $this->landlord_db();
        $db->where('plan_code', $planCode);
        if ($excludeId) {
            $db->where('id !=', (int) $excludeId);
        }
        return $db->count_all_results(db_prefix() . 'kt_saas_plans') > 0;
    }

    private function domainExists($domain, $excludeId = null)
    {
        $db = $this->landlord_db();
        $db->where('domain', $domain);
        if ($excludeId) {
            $db->where('id !=', (int) $excludeId);
        }
        return $db->count_all_results(db_prefix() . 'kt_saas_domains') > 0;
    }

    private function encryptNullable($value)
    {
        if ($value === '') {
            return null;
        }

        return $this->encryption->encrypt($value);
    }

    private function businessActivityMessage(array $row)
    {
        $event = (string) ($row['event_key'] ?? '');
        $tenant = trim((string) (($row['company_name'] ?? '') ?: ($row['tenant_code'] ?? 'Doanh nghiệp')));
        $context = json_decode((string) ($row['context_json'] ?? ''), true);
        if (!is_array($context)) {
            $context = [];
        }

        $messages = [
            'invoice.paid' => $tenant . ' đã thanh toán hóa đơn.',
            'payment.webhook_matched' => $tenant . ' có thanh toán SePay đã khớp.',
            'provision.job_done' => $tenant . ' đã khởi tạo thành công.',
            'provision.job_failed' => $tenant . ' khởi tạo thất bại.',
            'tenant.status_changed' => $tenant . ' đã đổi trạng thái.',
            'tenant.created' => $tenant . ' vừa được tạo.',
            'landing_signup.accepted' => $tenant . ' đã gửi đăng ký mới.',
            'landing_signup.invoice_ready' => $tenant . ' đã có hóa đơn đăng ký.',
            'subscription.created' => $tenant . ' đã có gói đăng ký mới.',
            'subscription.reactivated' => $tenant . ' đã được kích hoạt lại.',
            'tenant.purged' => 'Một tenant đã được xóa vĩnh viễn.',
        ];

        if (isset($messages[$event])) {
            return $messages[$event];
        }

        return trim(str_replace(['kt_saas.', '_', '.'], ['', ' ', ' '], $event));
    }

    private function activityLogQuery(array $filters = [])
    {
        $db = $this->landlord_db();

        $eventKey = trim((string) ($filters['event_key'] ?? ''));
        if ($eventKey !== '') {
            $db->like('event_key', $eventKey);
        }

        $severity = trim((string) ($filters['severity'] ?? ''));
        if ($severity !== '') {
            $db->where('severity', $severity);
        }

        $tenantId = (int) ($filters['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            $db->where('tenant_id', $tenantId);
        }

        return $db;
    }

    private function tenantDomainDeactivatePayload($now)
    {
        $full = db_prefix() . 'kt_saas_domains';
        $payload = [];

        if ($this->landlord_db()->field_exists('readiness_status', $full)) {
            $payload['readiness_status'] = 'inactive';
        }
        if ($this->landlord_db()->field_exists('status', $full)) {
            $payload['status'] = 'inactive';
        }
        if ($this->landlord_db()->field_exists('deleted_at', $full)) {
            $payload['deleted_at'] = $now;
        }
        if ($this->landlord_db()->field_exists('deleted_by', $full)) {
            $payload['deleted_by'] = get_staff_user_id() ?: null;
        }
        if ($this->landlord_db()->field_exists('updated_at', $full)) {
            $payload['updated_at'] = $now;
        }
        if ($this->landlord_db()->field_exists('updated_by', $full)) {
            $payload['updated_by'] = get_staff_user_id() ?: null;
        }

        return $payload;
    }

    private function purgeIdsForTenant($table, $tenantColumn, $tenantId)
    {
        $full = db_prefix() . $table;
        if (!$this->landlord_db()->table_exists($full) || !$this->landlord_db()->field_exists($tenantColumn, $full)) {
            return [];
        }

        $rows = $this->landlord_db()
            ->select('id')
            ->where($tenantColumn, (int) $tenantId)
            ->get($full)
            ->result_array();

        return array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
    }

    private function purgeBackupFilesForTenant($tenantId)
    {
        $full = db_prefix() . 'kt_saas_backups';
        if (!$this->landlord_db()->table_exists($full) || !$this->landlord_db()->field_exists('file_path', $full)) {
            return [];
        }

        $rows = $this->landlord_db()
            ->select('file_path')
            ->where('tenant_id', (int) $tenantId)
            ->get($full)
            ->result_array();

        return array_values(array_filter(array_map(function ($row) {
            return (string) ($row['file_path'] ?? '');
        }, $rows)));
    }

    private function purgePaymentRequestIds($tenantId, array $invoiceIds, array $subscriptionIds, array $orderIds)
    {
        $full = db_prefix() . 'kt_sepay_payment_requests';
        if (!$this->landlord_db()->table_exists($full)) {
            return [];
        }

        $ids = $this->purgeIdsForTenant('kt_sepay_payment_requests', 'tenant_id', $tenantId);

        if ($this->landlord_db()->field_exists('invoice_id', $full) && !empty($invoiceIds)) {
            $ids = array_merge($ids, $this->purgeIdsWhereIn('kt_sepay_payment_requests', 'invoice_id', $invoiceIds));
        }
        if ($this->landlord_db()->field_exists('subscription_id', $full) && !empty($subscriptionIds)) {
            $ids = array_merge($ids, $this->purgeIdsWhereIn('kt_sepay_payment_requests', 'subscription_id', $subscriptionIds));
        }
        if ($this->landlord_db()->field_exists('context_type', $full) && !empty($orderIds)) {
            $rows = $this->landlord_db()
                ->select('id')
                ->where('context_type', 'kt_matbao_invoice_order')
                ->where_in('context_id', $orderIds)
                ->get($full)
                ->result_array();
            $ids = array_merge($ids, array_map('intval', array_column($rows, 'id')));
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function purgeIdsWhereIn($table, $column, array $values)
    {
        $full = db_prefix() . $table;
        $values = array_values(array_unique(array_filter(array_map('intval', $values))));
        if (empty($values) || !$this->landlord_db()->table_exists($full) || !$this->landlord_db()->field_exists($column, $full)) {
            return [];
        }

        $ids = [];
        foreach (array_chunk($values, 500) as $chunk) {
            $rows = $this->landlord_db()
                ->select('id')
                ->where_in($column, $chunk)
                ->get($full)
                ->result_array();
            $ids = array_merge($ids, array_map('intval', array_column($rows, 'id')));
        }

        return array_values(array_unique($ids));
    }

    private function purgeDeleteTenantRows(array $tables, $tenantId, array &$summary)
    {
        foreach ($tables as $table) {
            $full = db_prefix() . $table;
            if (!$this->landlord_db()->table_exists($full) || !$this->landlord_db()->field_exists('tenant_id', $full)) {
                continue;
            }

            $count = (int) $this->landlord_db()->where('tenant_id', (int) $tenantId)->count_all_results($full);
            if ($count > 0) {
                $this->landlord_db()->where('tenant_id', (int) $tenantId)->delete($full);
            }
            $summary['deleted_rows'][$table] = ($summary['deleted_rows'][$table] ?? 0) + $count;
        }
    }

    private function purgeDeleteWhereIn($table, $column, array $values, array &$summary)
    {
        $full = db_prefix() . $table;
        $values = array_values(array_unique(array_filter(array_map('intval', $values))));
        if (empty($values) || !$this->landlord_db()->table_exists($full) || !$this->landlord_db()->field_exists($column, $full)) {
            return;
        }

        $total = 0;
        foreach (array_chunk($values, 500) as $chunk) {
            $count = (int) $this->landlord_db()->where_in($column, $chunk)->count_all_results($full);
            if ($count > 0) {
                $this->landlord_db()->where_in($column, $chunk)->delete($full);
            }
            $total += $count;
        }

        $summary['deleted_rows'][$table] = ($summary['deleted_rows'][$table] ?? 0) + $total;
    }

    private function purgeDeleteRowsById($table, array $ids, array &$summary)
    {
        $this->purgeDeleteWhereIn($table, 'id', $ids, $summary);
    }

    private function purgeDropTenantDatabase(array $tenant)
    {
        $dbName = trim((string) ($tenant['db_name'] ?? ''));
        if ($dbName === '') {
            return ['success' => true, 'dropped' => false, 'message' => 'No tenant database configured.'];
        }

        if (!preg_match('/^(kt_tenant_|khachtot_tenant_)[A-Za-z0-9_]+$/', $dbName)) {
            return ['success' => false, 'message' => 'Tenant database name is not in an allowed purge prefix.'];
        }

        $blocked = ['khachtot', 'information_schema', 'mysql', 'performance_schema', 'sys'];
        if (defined('APP_DB_NAME')) {
            $blocked[] = strtolower((string) APP_DB_NAME);
        }
        $currentDatabase = isset($this->landlord_db()->database) ? strtolower((string) $this->landlord_db()->database) : '';
        if ($currentDatabase !== '') {
            $blocked[] = $currentDatabase;
        }

        if (in_array(strtolower($dbName), array_unique($blocked), true)) {
            return ['success' => false, 'message' => 'Refusing to drop protected database.'];
        }

        $exists = $this->landlord_db()->query('SHOW DATABASES LIKE ' . $this->landlord_db()->escape($dbName))->num_rows() > 0;
        if (!$exists) {
            return ['success' => true, 'dropped' => false, 'database' => $dbName, 'message' => 'Tenant database does not exist.'];
        }

        $escaped = str_replace('`', '``', $dbName);
        $this->landlord_db()->query('DROP DATABASE `' . $escaped . '`');

        return ['success' => true, 'dropped' => true, 'database' => $dbName];
    }

    private function purgeTenantFileTargets(array $tenant, array $backupFiles)
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $tenantCode = strtolower(trim((string) ($tenant['tenant_code'] ?? '')));
        $targets = [];

        $tenantUploadsBase = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'kt_saas' . DIRECTORY_SEPARATOR . 'tenants';
        if ($tenantId > 0) {
            $targets[] = [
                'label' => 'tenant_uploads',
                'base' => $tenantUploadsBase,
                'path' => $tenantUploadsBase . DIRECTORY_SEPARATOR . $tenantId,
            ];
        }

        $backupBase = module_dir_path(KT_SAAS_MODULE, 'storage/backups');
        if ($tenantCode !== '') {
            $targets[] = [
                'label' => 'tenant_backup_directory',
                'base' => $backupBase,
                'path' => $backupBase . DIRECTORY_SEPARATOR . $tenantCode,
            ];
        }
        foreach ($backupFiles as $filePath) {
            $targets[] = [
                'label' => 'tenant_backup_file',
                'base' => $backupBase,
                'path' => $filePath,
            ];
        }

        $manifestBase = module_dir_path(KT_SAAS_MODULE, 'tenant_bootstrap/manifests');
        if ($tenantCode !== '') {
            $targets[] = [
                'label' => 'tenant_manifest',
                'base' => $manifestBase,
                'path' => $manifestBase . DIRECTORY_SEPARATOR . $tenantCode . '.json',
            ];
        }

        return $targets;
    }

    private function purgeSafePath($path, $base, $label)
    {
        $baseReal = realpath($base);
        $path = (string) $path;
        if ($path === '' || $baseReal === false) {
            return ['label' => $label, 'path' => $path, 'status' => 'skipped'];
        }

        $real = realpath($path);
        if ($real === false) {
            return ['label' => $label, 'path' => $path, 'status' => 'missing'];
        }

        if ($real !== $baseReal && strpos($real, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
            return ['label' => $label, 'path' => $path, 'status' => 'blocked_outside_whitelist'];
        }

        if ($real === $baseReal) {
            return ['label' => $label, 'path' => $path, 'status' => 'blocked_base_path'];
        }

        $this->purgeRecursiveDelete($real);

        return ['label' => $label, 'path' => $real, 'status' => file_exists($real) ? 'failed' : 'deleted'];
    }

    private function purgeRecursiveDelete($path)
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }

    private function normalizeModuleJson($moduleCodes)
    {
        if (is_array($moduleCodes)) {
            $parts = array_values(array_filter(array_map('trim', $moduleCodes)));
            return json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        $parts = preg_split('/[\s,]+/', trim((string) $moduleCodes));
        $parts = array_values(array_filter(array_map('trim', $parts)));

        return json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function safe_count($table)
    {
        $db = $this->landlord_db();
        $full = db_prefix() . $table;
        if (!$db->table_exists($full)) {
            return 0;
        }

        return (int) $db->count_all_results($full);
    }

    private function landlord_db()
    {
        $landlordDb = $this->config->item('kt_saas_landlord_db');

        if ($landlordDb && $landlordDb->table_exists(db_prefix() . 'kt_saas_tenants')) {
            return $landlordDb;
        }

        if ($this->db->table_exists(db_prefix() . 'kt_saas_tenants')) {
            $this->config->set_item('kt_saas_landlord_db', $this->db);
            return $this->db;
        }

        $database = $this->discover_landlord_database_name();
        $config = [
            'dsn'          => '',
            'hostname'     => APP_DB_HOSTNAME,
            'username'     => APP_DB_USERNAME,
            'password'     => APP_DB_PASSWORD,
            'database'     => $database,
            'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
            'dbprefix'     => db_prefix(),
            'pconnect'     => false,
            'db_debug'     => (ENVIRONMENT !== 'production'),
            'cache_on'     => false,
            'cachedir'     => '',
            'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
            'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
            'swap_pre'     => '',
            'encrypt'      => defined('APP_DB_ENCRYPT') ? APP_DB_ENCRYPT : false,
            'compress'     => false,
            'stricton'     => false,
            'failover'     => [],
            'save_queries' => true,
        ];
        $db = $this->load->database($config, true);
        $this->config->set_item('kt_saas_landlord_db', $db);

        return $db;
    }

    private function discover_landlord_database_name()
    {
        $candidates = [];

        foreach (['KT_SAAS_LANDLORD_DB_NAME', 'LANDLORD_DB_NAME', 'APP_LANDLORD_DB_NAME'] as $envKey) {
            $value = trim((string) getenv($envKey));
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        foreach (['khachtot', 'crm', defined('APP_DB_NAME') ? (string) APP_DB_NAME : ''] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        $candidates = array_values(array_unique($candidates));
        $currentDatabase = isset($this->db->database) ? strtolower((string) $this->db->database) : '';

        foreach ($candidates as $database) {
            if (strtolower((string) $database) === $currentDatabase) {
                continue;
            }

            $config = [
                'dsn'          => '',
                'hostname'     => APP_DB_HOSTNAME,
                'username'     => APP_DB_USERNAME,
                'password'     => APP_DB_PASSWORD,
                'database'     => $database,
                'dbdriver'     => defined('APP_DB_DRIVER') ? APP_DB_DRIVER : 'mysqli',
                'dbprefix'     => db_prefix(),
                'pconnect'     => false,
                'db_debug'     => false,
                'cache_on'     => false,
                'cachedir'     => '',
                'char_set'     => defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4',
                'dbcollat'     => defined('APP_DB_COLLATION') ? APP_DB_COLLATION : 'utf8mb4_unicode_ci',
                'swap_pre'     => '',
                'encrypt'      => defined('APP_DB_ENCRYPT') ? APP_DB_ENCRYPT : false,
                'compress'     => false,
                'stricton'     => false,
                'failover'     => [],
                'save_queries' => true,
            ];

            $db = $this->load->database($config, true);
            if ($db && $db->table_exists(db_prefix() . 'kt_saas_tenants')) {
                $this->config->set_item('kt_saas_landlord_db', $db);
                return $database;
            }
        }

        return defined('APP_DB_NAME') ? APP_DB_NAME : '';
    }

    private function tenant_option($name, $default = '')
    {
        $row = $this->db
            ->select('value')
            ->where('name', (string) $name)
            ->get(db_prefix() . 'options')
            ->row_array();

        if (!is_array($row)) {
            return $default;
        }

        return (string) ($row['value'] ?? $default);
    }

    private function upsert_tenant_option($name, $value, $autoload = '1')
    {
        $existing = $this->db
            ->select('id')
            ->where('name', (string) $name)
            ->get(db_prefix() . 'options')
            ->row_array();

        $payload = [
            'value'    => (string) $value,
            'autoload' => (string) $autoload,
        ];

        if (is_array($existing) && !empty($existing['id'])) {
            $this->db->where('id', (int) $existing['id'])->update(db_prefix() . 'options', $payload);
            return;
        }

        $payload['name'] = (string) $name;
        $this->db->insert(db_prefix() . 'options', $payload);
    }

    private function sync_tenant_base_currency($currencyCode)
    {
        $currencyCode = strtoupper(trim((string) $currencyCode));
        if ($currencyCode === '') {
            return;
        }

        $table = db_prefix() . 'currencies';
        if (!$this->db->table_exists($table)) {
            return;
        }

        $target = $this->db
            ->select('id')
            ->where('name', $currencyCode)
            ->get($table)
            ->row_array();

        if (!is_array($target) || empty($target['id'])) {
            return;
        }

        $targetId = (int) $target['id'];
        $this->db->update($table, ['isdefault' => 0]);
        $this->db->where('id', $targetId)->update($table, ['isdefault' => 1]);
    }

    private function find_landlord_tenant_row($tenantId)
    {
        $landlordDb = $this->landlord_db();
        if (!$landlordDb) {
            return null;
        }

        return $landlordDb
            ->where('id', (int) $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->get(db_prefix() . 'kt_saas_tenants')
            ->row_array();
    }

    public function run_workspace_isolation_audit($tenantId)
    {
        $tenantId = (int) $tenantId;
        $tenant = $this->find_landlord_tenant_row($tenantId);
        if (!$tenant) {
            $tenant = $this->get_tenant($tenantId);
        }
        if (!$tenant) {
            return ['success' => false, 'message' => 'Tenant not found.'];
        }

        $tenantDb = $this->connect_tenant_runtime_db($tenant);
        if (!$tenantDb || !$tenantDb->table_exists(db_prefix() . 'options')) {
            return ['success' => false, 'message' => 'Tenant database is not ready or options table is missing.'];
        }

        require_once module_dir_path(KT_SAAS_MODULE, 'services/TenantEntitlementService.php');
        $entitlementService = new TenantEntitlementService();
        $profile = $entitlementService->getRuntimeProfile($tenant);
        $workspaceFeatures = (array) ($profile['workspace_features'] ?? []);

        $groups = [
            'branding' => ['company_logo', 'company_logo_dark', 'favicon'],
            'company' => ['companyname', 'company_email', 'companyphonenumber', 'company_vat'],
            'localization' => ['active_language', 'default_timezone', 'default_currency', 'dateformat', 'time_format'],
            'mail_identity' => ['kt_saas_mail_from_name', 'kt_saas_mail_reply_to_email', 'bcc_emails', 'email_signature', 'email_header', 'email_footer'],
            'invoice_defaults' => ['invoice_company_name', 'invoice_company_address', 'invoice_company_city', 'invoice_company_state', 'invoice_company_country_code', 'invoice_company_postal_code', 'invoice_company_phonenumber', 'invoice_due_after', 'estimate_due_after', 'invoice_prefix', 'next_invoice_number'],
        ];

        $featureMap = [
            'branding' => 'workspace.branding.edit',
            'company' => 'workspace.company.edit',
            'localization' => 'workspace.localization.edit',
            'mail_identity' => 'workspace.mail.identity.edit',
            'invoice_defaults' => 'workspace.finance.edit',
        ];

        $tenantRows = $tenantDb->select('name,value')->from(db_prefix() . 'options')->get()->result_array();
        $tenantOptions = [];
        foreach ($tenantRows as $row) {
            $tenantOptions[(string) ($row['name'] ?? '')] = (string) ($row['value'] ?? '');
        }

        $checks = [];
        foreach ($groups as $group => $keys) {
            $missing = [];
            foreach ($keys as $key) {
                if (!array_key_exists($key, $tenantOptions)) {
                    $missing[] = $key;
                }
            }

            $featureKey = $featureMap[$group] ?? '';
            $featureEnabled = $featureKey !== '' ? !empty($workspaceFeatures[$featureKey]) : null;
            $checks[$group] = [
                'pass' => empty($missing),
                'feature_key' => $featureKey,
                'feature_enabled' => $featureEnabled,
                'missing_options' => $missing,
            ];
        }

        // Deep check for branding: option exists + file exists on disk + isolate from landlord/default branding.
        $brandingFiles = [
            'company_logo' => (string) ($tenantOptions['company_logo'] ?? ''),
            'company_logo_dark' => (string) ($tenantOptions['company_logo_dark'] ?? ''),
            'favicon' => (string) ($tenantOptions['favicon'] ?? ''),
        ];
        $landlordBranding = [
            'company_logo' => (string) get_option('company_logo'),
            'company_logo_dark' => (string) get_option('company_logo_dark'),
            'favicon' => (string) get_option('favicon'),
        ];
        $brandingIssues = [];
        $brandingDetails = [];
        foreach ($brandingFiles as $optionKey => $filename) {
            $filename = trim($filename);
            $landlordFilename = trim((string) ($landlordBranding[$optionKey] ?? ''));
            $detail = [
                'tenant_value' => $filename,
                'landlord_value' => $landlordFilename,
                'tenant_file_exists' => false,
                'landlord_file_exists' => false,
                'same_filename_as_landlord' => false,
                'same_content_hash_as_landlord' => false,
            ];

            if ($filename === '') {
                if ($optionKey === 'favicon') {
                    $brandingIssues[] = [
                        'option' => $optionKey,
                        'issue' => 'empty_value_may_fallback',
                    ];
                }
                $brandingDetails[$optionKey] = $detail;
                continue;
            }

            $tenantPath = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $filename;
            $landlordPath = $landlordFilename !== ''
                ? FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $landlordFilename
                : '';

            if (!is_file($tenantPath)) {
                $brandingIssues[] = [
                    'option' => $optionKey,
                    'filename' => $filename,
                    'issue' => 'file_missing',
                ];
                $brandingDetails[$optionKey] = $detail;
                continue;
            }

            $detail['tenant_file_exists'] = true;
            if ($landlordPath !== '' && is_file($landlordPath)) {
                $detail['landlord_file_exists'] = true;
                $detail['same_filename_as_landlord'] = strcasecmp($filename, $landlordFilename) === 0;
                $tenantHash = @md5_file($tenantPath);
                $landlordHash = @md5_file($landlordPath);
                $detail['same_content_hash_as_landlord'] = $tenantHash !== false && $landlordHash !== false && $tenantHash === $landlordHash;
            }

            if ($detail['same_filename_as_landlord']) {
                $brandingIssues[] = [
                    'option' => $optionKey,
                    'filename' => $filename,
                    'issue' => 'same_filename_as_landlord',
                ];
            } elseif ($detail['same_content_hash_as_landlord']) {
                $brandingIssues[] = [
                    'option' => $optionKey,
                    'filename' => $filename,
                    'issue' => 'same_content_as_landlord',
                ];
            }

            $brandingDetails[$optionKey] = $detail;
        }
        $checks['branding']['files'] = $brandingFiles;
        $checks['branding']['landlord_files'] = $landlordBranding;
        $checks['branding']['details'] = $brandingDetails;
        $checks['branding']['file_issues'] = $brandingIssues;
        $checks['branding']['pass'] = empty($checks['branding']['missing_options']) && empty($brandingIssues);

        $allPass = true;
        foreach ($checks as $check) {
            if (empty($check['pass'])) {
                $allPass = false;
                break;
            }
        }

        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'tenant_code' => (string) ($tenant['tenant_code'] ?? ''),
            'all_pass' => $allPass,
            'checks' => $checks,
        ];
    }

    private function normalizeWorkspaceStaffIds($staffIds)
    {
        if (!is_array($staffIds)) {
            $staffIds = preg_split('/[\s,]+/', (string) $staffIds);
        }

        $activeStaff = [];
        foreach ($this->get_active_tenant_staff_members() as $staff) {
            $activeStaff[(int) ($staff['staffid'] ?? 0)] = true;
        }

        $allowed = [];
        foreach ((array) $staffIds as $staffId) {
            $staffId = (int) $staffId;
            if ($staffId > 0 && isset($activeStaff[$staffId])) {
                $allowed[$staffId] = $staffId;
            }
        }

        return json_encode(array_values($allowed));
    }

    private function normalizeWorkspaceEmailList($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/[\r\n,;]+/', $value);
        if (!is_array($parts)) {
            return null;
        }

        $emails = [];
        foreach ($parts as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            $emails[strtolower($email)] = $email;
        }

        return implode(', ', array_values($emails));
    }

    private function processTenantBrandingUpload($tenantId, $fieldName, $prefix)
    {
        $tenantId = (int) $tenantId;
        $labels = [
            'company_logo'      => 'logo nền sáng',
            'company_logo_dark' => 'logo nền tối',
            'favicon'           => 'biểu tượng trình duyệt',
        ];
        $label = $labels[$fieldName] ?? 'tệp thương hiệu';

        if (!isset($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['name'] === '') {
            return ['status' => 'none', 'field' => $fieldName, 'message' => '', 'filename' => ''];
        }

        $uploadError = (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_OK);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return ['status' => 'none', 'field' => $fieldName, 'message' => '', 'filename' => ''];
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            return [
                'status' => 'error',
                'field' => $fieldName,
                'message' => 'Không thể tải lên ' . $label . '. Vui lòng thử lại.',
                'filename' => '',
            ];
        }

        $tmpFilePath = $_FILES[$fieldName]['tmp_name'] ?? '';
        if ($tmpFilePath === '' || !is_uploaded_file($tmpFilePath)) {
            return [
                'status' => 'error',
                'field' => $fieldName,
                'message' => 'Không thể tải lên ' . $label . '. Vui lòng thử lại.',
                'filename' => '',
            ];
        }

        $extension = strtolower((string) pathinfo((string) $_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'ico', 'webp'];
        if (
            !function_exists('_upload_file_security_allowed')
            || !_upload_file_security_allowed((string) $_FILES[$fieldName]['name'], $tmpFilePath, $allowedExtensions)
        ) {
            return [
                'status' => 'error',
                'field' => $fieldName,
                'message' => 'Tệp ' . $label . ' không hợp lệ. Vui lòng dùng JPG, PNG, WebP hoặc ICO.',
                'filename' => '',
            ];
        }

        $path = $tenantId > 0 ? kt_saas_tenant_branding_path($tenantId) : '';
        $path = $path !== '' ? rtrim($path, '/\\') . DIRECTORY_SEPARATOR : '';
        if ($path === '' || !$this->ensureTenantBrandingUploadPath($tenantId, $path)) {
            return [
                'status' => 'error',
                'field' => $fieldName,
                'message' => 'Thư mục lưu thương hiệu chưa có quyền ghi.',
                'filename' => '',
            ];
        }

        $existing = basename($this->tenant_option($fieldName, ''));
        if ($existing !== '') {
            $existingPath = $path . $existing;
            if ($this->isTenantBrandingPath((int) $tenantId, $existingPath) && file_exists($existingPath)) {
                @unlink($existingPath);
            }
        }

        $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $newFilePath = $path . $filename;
        if (!move_uploaded_file($tmpFilePath, $newFilePath)) {
            return [
                'status' => 'error',
                'field' => $fieldName,
                'message' => 'Không thể lưu tệp thương hiệu. Vui lòng thử lại.',
                'filename' => '',
            ];
        }

        $this->upsert_tenant_option($fieldName, $filename);

        return ['status' => 'uploaded', 'field' => $fieldName, 'message' => '', 'filename' => $filename];
    }

    private function ensureTenantBrandingUploadPath($tenantId, $path)
    {
        $tenantId = (int) $tenantId;
        $path = rtrim((string) $path, '/\\');
        if ($tenantId <= 0 || $path === '') {
            return false;
        }

        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            return false;
        }

        $indexPath = $path . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($indexPath)) {
            @file_put_contents($indexPath, '');
        }

        return is_dir($path) && is_writable($path);
    }

    private function isTenantBrandingPath($tenantId, $path)
    {
        $basePath = kt_saas_tenant_branding_path((int) $tenantId);
        if ($basePath === '') {
            return false;
        }

        $baseReal = realpath($basePath);
        $pathReal = realpath($path);
        if ($baseReal === false || $pathReal === false) {
            return false;
        }

        return strpos($pathReal, rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0;
    }

    private function workspace_timezone_options()
    {
        $priority = ['UTC', 'Asia/Bangkok', 'Asia/Ho_Chi_Minh', 'Asia/Singapore', 'Europe/London'];
        $timezones = [];

        foreach ($priority as $timezone) {
            $timezones[$timezone] = $timezone;
        }

        foreach (timezone_identifiers_list() as $timezone) {
            $timezones[$timezone] = $timezone;
        }

        return array_values($timezones);
    }

    private function workspace_date_format_options()
    {
        return [
            'Y-m-d|%Y-%m-%d' => '2026-05-27',
            'd/m/Y|%d/%m/%Y' => '27/05/2026',
            'm/d/Y|%m/%d/%Y' => '05/27/2026',
            'd.m.Y|%d.%m.%Y' => '27.05.2026',
        ];
    }

    private function workspace_number_format_options()
    {
        return [
            '1' => 'Number based (000001)',
            '2' => 'Year based (YYYY/000001)',
            '3' => 'Short year based (000001-YY)',
            '4' => 'Year/month based (000001/MM/YYYY)',
        ];
    }

    private function workspace_currency_options()
    {
        $currencies = [];

        $mergeRows = static function (array $rows, array &$target): void {
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row['name'] ?? '')));
                if ($code === '') {
                    continue;
                }
                if (!isset($target[$code])) {
                    $target[$code] = [
                        'id'        => (int) ($row['id'] ?? 0),
                        'code'      => $code,
                        'symbol'    => (string) ($row['symbol'] ?? ''),
                        'isdefault' => (int) ($row['isdefault'] ?? 0) === 1,
                    ];
                } elseif ($target[$code]['symbol'] === '' && !empty($row['symbol'])) {
                    $target[$code]['symbol'] = (string) $row['symbol'];
                }
            }
        };

        $table = db_prefix() . 'currencies';
        if ($this->db->table_exists($table)) {
            $tenantRows = $this->db
                ->select('id,name,symbol,isdefault')
                ->from($table)
                ->order_by('isdefault', 'desc')
                ->order_by('name', 'asc')
                ->get()
                ->result_array();
            $mergeRows($tenantRows, $currencies);
        }

        $landlordDb = $this->config->item('kt_saas_landlord_db');
        if ($landlordDb && $landlordDb->table_exists($table)) {
            $landlordRows = $landlordDb
                ->select('id,name,symbol,isdefault')
                ->from($table)
                ->order_by('isdefault', 'desc')
                ->order_by('name', 'asc')
                ->get()
                ->result_array();
            $mergeRows($landlordRows, $currencies);
        }

        return array_values($currencies);
    }

    private function normalizeWorkspacePrefix($value, $default)
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^A-Z0-9\\-_\\/]/', '', $value);
        $value = substr((string) $value, 0, 20);

        return $value !== '' ? $value : $default;
    }

    private function normalizeWorkspaceNumberFormat($value, array $options)
    {
        $value = (string) $value;

        return array_key_exists($value, $options) ? $value : '1';
    }

    private function normalizeWorkspaceEnum($value, array $allowed, $default)
    {
        $value = trim((string) $value);

        return in_array($value, $allowed, true) ? $value : (string) $default;
    }
}
