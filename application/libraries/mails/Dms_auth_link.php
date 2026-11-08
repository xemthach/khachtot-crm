<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dms_auth_link extends App_mail_template
{
    protected $for = 'staff';

    protected string $recipientEmail;

    protected array $context;

    public $slug = 'dms-auth-link';

    public $rel_type = 'staff';

    public function __construct($recipientEmail, array $context = [])
    {
        parent::__construct();

        $this->recipientEmail = (string) $recipientEmail;
        $this->context = $context;
    }

    public function build()
    {
        $subject = (string) ($this->context['subject'] ?? 'DMS account action required');
        $headline = (string) ($this->context['headline'] ?? $subject);
        $message = (string) ($this->context['message'] ?? '');
        $actionUrl = (string) ($this->context['action_url'] ?? '');
        $actionLabel = (string) ($this->context['action_label'] ?? 'Open link');
        $loginUrl = (string) ($this->context['login_url'] ?? '');
        $expiresIn = (string) ($this->context['expires_in_minutes'] ?? '60');

        $this->to($this->recipientEmail)
            ->set_merge_fields([
                '{subject}' => $subject,
                '{headline}' => $headline,
                '{message}' => $message,
                '{action_url}' => $actionUrl,
                '{action_label}' => $actionLabel,
                '{login_url}' => $loginUrl,
                '{expires_in_minutes}' => $expiresIn,
                '{support_email}' => (string) ($this->context['support_email'] ?? ''),
                '{app_name}' => (string) ($this->context['app_name'] ?? ''),
                '{companyname}' => (string) ($this->context['companyname'] ?? ''),
                '{email_signature}' => (string) ($this->context['email_signature'] ?? ''),
                '{recipient_name}' => (string) ($this->context['name'] ?? $this->context['recipient_name'] ?? ''),
                '{recipient_email}' => $this->recipientEmail,
                '{reset_password_url}' => $actionUrl,
                '{set_password_url}' => $actionUrl,
                '{verification_url}' => $actionUrl,
            ]);
    }
}
