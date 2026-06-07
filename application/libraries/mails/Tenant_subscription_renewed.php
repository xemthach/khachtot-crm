<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tenant_subscription_renewed extends App_mail_template
{
    protected $for = 'other';

    protected array $context = [];

    public $slug = 'tenant_subscription_renewed';

    public $rel_type = 'subscription';

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
        foreach (['recipient_email', 'owner_email', 'email', 'send_to'] as $key) {
            $value = trim((string) ($this->context[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function relationId()
    {
        if (!empty($this->context['subscription_id'])) {
            return (int) $this->context['subscription_id'];
        }

        if (!empty($this->context['subscription']['id'])) {
            return (int) $this->context['subscription']['id'];
        }

        return !empty($this->context['tenant_id']) ? (int) $this->context['tenant_id'] : 0;
    }
}
