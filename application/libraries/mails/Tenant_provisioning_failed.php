<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tenant_provisioning_failed extends App_mail_template
{
    protected $for = 'other';

    protected array $context = [];

    public $slug = 'tenant_provisioning_failed';

    public $rel_type = 'tenant';

    public function __construct($context = [])
    {
        parent::__construct();
        $this->context = is_array($context) ? $context : [];
        if (!isset($this->ci->kt_saas_merge_fields)) {
            $this->ci->load->library('kt_saas/merge_fields/Kt_saas_merge_fields');
        }
        $this->set_merge_fields($this->ci->kt_saas_merge_fields->format($this->context));
    }

    public function build()
    {
        $recipient = $this->recipientEmail();
        if ($recipient !== '') {
            $this->to($recipient);
        }

        $cc = $this->ccEmails();
        if (!empty($cc)) {
            $this->cc($cc);
        }

        $this->set_rel_id($this->relationId());

        return $this;
    }

    protected function recipientEmail()
    {
        foreach (['recipient_email', 'owner_email', 'email', 'send_to'] as $key) {
            $value = trim((string) ($this->context[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function ccEmails()
    {
        $cc = $this->context['cc'] ?? [];
        if (is_string($cc)) {
            $cc = array_filter(array_map('trim', explode(',', $cc)));
        }

        return is_array($cc) ? array_values(array_filter(array_map('trim', $cc))) : [];
    }

    protected function relationId()
    {
        if (!empty($this->context['tenant_id'])) {
            return (int) $this->context['tenant_id'];
        }

        if (!empty($this->context['tenant']['id'])) {
            return (int) $this->context['tenant']['id'];
        }

        return 0;
    }
}
