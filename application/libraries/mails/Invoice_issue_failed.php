<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Invoice_issue_failed extends App_mail_template
{
    protected $for = 'other';
    protected array $context = [];
    public $slug = 'invoice_issue_failed';
    public $rel_type = 'invoice';

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
        $this->set_rel_id($this->relationId());
        return $this;
    }

    protected function recipientEmail()
    {
        foreach (['recipient_email', 'owner_email', 'admin_email', 'email', 'send_to'] as $key) {
            $value = trim((string) ($this->context[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    protected function relationId()
    {
        return (int) ($this->context['invoice_id'] ?? ($this->context['invoice']['id'] ?? 0));
    }
}
