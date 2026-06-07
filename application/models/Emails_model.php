<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Emails_model extends App_Model
{
    private $attachment = [];
    private $last_send_error = '';
    private $last_send_error_code = 0;
    private $last_send_message_id = '';

    /**
     * @deprecated 2.3.0
     */
    private $client_email_templates;

    /**
     * @deprecated 2.3.0
     */
    private $staff_email_templates;

    /**
     * @deprecated 2.3.0
     */
    private $rel_id;

    /**
     * @deprecated 2.3.0
     */
    private $rel_type;

    /**
     * @deprecated 2.3.0
     */
    private $staff_id;

    public function __construct()
    {
        parent::__construct();
        $this->client_email_templates = get_client_email_templates_slugs();
        $this->staff_email_templates  = get_staff_email_templates_slugs();
    }

    /**
     * @param  string
     * @return array
     * Get email template by type
     */
    public function get($where = [], $result_type = 'result_array')
    {
        $this->db->where($where);

        return $this->db->get(db_prefix() . 'emailtemplates')->{$result_type}();
    }

    /**
     * @param  integer
     * @return object
     * Get email template by id
     */
    public function get_email_template_by_id($id)
    {
        $this->db->where('emailtemplateid', $id);

        return $this->db->get(db_prefix() . 'emailtemplates')->row();
    }

    /**
     * Create new email template
     * @param mixed $data
     */
    public function add_template($data)
    {
        $this->db->insert(db_prefix() . 'emailtemplates', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            return $insert_id;
        }

        return false;
    }

    /**
     * @param  array $_POST data
     * @param  integer ID
     * @return boolean
     * Update email template
     */
    public function update($data)
    {
        $data['plaintext'] = isset($data['plaintext']) ? 1 : 0;

        if (isset($data['disabled'])) {
            $data['active'] = 0;
            unset($data['disabled']);
        } else {
            $data['active'] = 1;
        }

        $main_id      = false;
        $affectedRows = 0;
        $i            = 0;

        foreach ($data['subject'] as $id => $val) {
            if ($i == 0) {
                $main_id = $id;
            }

            $_data             = [];
            $_data['subject']  = $val;
            $_data['fromname'] = $data['fromname'];
            // Two factor authentication email template  don't have fromemail
            $_data['fromemail'] = isset($data['fromemail']) ? $data['fromemail'] : '';
            $_data['message']   = $data['message'][$id];
            $_data['plaintext'] = $data['plaintext'];
            $_data['active']    = $data['active'];

            $this->db->where('emailtemplateid', $id);
            $this->db->update(db_prefix() . 'emailtemplates', $_data);
            if ($this->db->affected_rows() > 0) {
                $affectedRows++;
            }

            $i++;
        }
        $main_template = $this->get_email_template_by_id($main_id);

        if ($affectedRows > 0 && $main_template) {
            log_activity('Email Template Updated [' . $main_template->name . ']');

            return true;
        }

        return false;
    }

    /**
     * Change template to active/inactive
     * @param  string $slug    template slug
     * @param  mixed $enabled enabled or disabled / 1 or 0
     * @return boolean
     */
    public function mark_as($slug, $enabled)
    {
        $this->db->where('slug', $slug);
        $this->db->update(db_prefix() . 'emailtemplates', ['active' => $enabled]);

        return $this->db->affected_rows() > 0 ? true : false;
    }

    /**
     * Change template to active/inactive
     * @param  string $type    template type
     * @param  mixed $enabled enabled or disabled / 1 or 0
     * @return boolean
     */
    public function mark_as_by_type($type, $enabled)
    {
        $this->db->where('type', $type);
        $this->db->where('slug !=', 'two-factor-authentication');
        $this->db->update(db_prefix() . 'emailtemplates', ['active' => $enabled]);

        return $this->db->affected_rows() > 0 ? true : false;
    }

    /**
     * Send email - No templates used only simple string
     * @since Version 1.0.2
     * @param  string $email   email
     * @param  string $message message
     * @param  string $subject email subject
     * @return boolean
     */
    public function send_simple_email($email, $subject, $message)
    {
        $this->last_send_error = '';
        $this->last_send_error_code = 0;
        $this->last_send_message_id = '';

        if (defined('DEMO') && DEMO) {
            return true;
        }

        $cnf = [
            'from_email' => get_option('smtp_email'),
            'from_name'  => get_option('companyname'),
            'email'      => $email,
            'subject'    => $subject,
            'message'    => $message,
        ];

        $runtimeIdentity = config_item('kt_saas_mail_runtime_identity');
        if (is_array($runtimeIdentity)) {
            if (!empty($runtimeIdentity['from_email'])) {
                $cnf['from_email'] = (string) $runtimeIdentity['from_email'];
            }
            if (!empty($runtimeIdentity['from_name'])) {
                $cnf['from_name'] = (string) $runtimeIdentity['from_name'];
            }
            if (!empty($runtimeIdentity['reply_to'])) {
                $cnf['reply_to'] = (string) $runtimeIdentity['reply_to'];
            }
        }

        // Simulate fake template to be parsed
        $template           = new StdClass();
        $template->message  = get_option('email_header') . $cnf['message'] . get_option('email_footer');
        $template->fromname = $cnf['from_name'];
        $template->subject  = $cnf['subject'];

        $template = parse_email_template($template);

        $cnf['message']   = $template->message;
        $cnf['from_name'] = $template->fromname;
        $cnf['subject']   = $template->subject;

        $cnf['message'] = check_for_links($cnf['message']);

        $cnf = hooks()->apply_filters('before_send_simple_email', $cnf);

        if (isset($cnf['prevent_sending']) && $cnf['prevent_sending'] == true) {
            $this->clear_attachments();

            return false;
        }

        $runtimeTransport = config_item('kt_saas_mail_runtime_transport');
        $brevoApiKey = '';
        if (is_array($runtimeTransport) && !empty($runtimeTransport['brevo_api_key'])) {
            $brevoApiKey = (string) $runtimeTransport['brevo_api_key'];
        } elseif (get_option('email_protocol') === 'brevo_api') {
            $enc = (string) get_option('brevo_api_key');
            $dec = $this->encryption->decrypt($enc);
            $brevoApiKey = $dec !== false ? (string) $dec : $enc;
        }
        if ($brevoApiKey !== '') {
            $bccList = [];
            if (!empty($cnf['bcc'])) {
                $bccList = is_array($cnf['bcc']) ? $cnf['bcc'] : array_map('trim', explode(',', (string) $cnf['bcc']));
            }
            $systemBCC = trim((string) get_option('bcc_emails'));
            if ($systemBCC !== '') {
                $bccList = array_merge($bccList, array_map('trim', explode(',', $systemBCC)));
            }

            $ccList = [];
            if (!empty($cnf['cc'])) {
                $ccList = is_array($cnf['cc']) ? $cnf['cc'] : array_map('trim', explode(',', (string) $cnf['cc']));
            }

            $result = $this->send_via_brevo_api([
                'api_key' => $brevoApiKey,
                'to_email' => (string) $cnf['email'],
                'from_email' => (string) $cnf['from_email'],
                'from_name' => (string) $cnf['from_name'],
                'reply_to' => (string) ($cnf['reply_to'] ?? ''),
                'subject' => (string) $cnf['subject'],
                'html_content' => (string) $cnf['message'],
                'text_content' => strip_html_tags((string) $cnf['message'], '<br/>, <br>, <br />'),
                'cc' => $ccList,
                'bcc' => $bccList,
                'attachments' => $this->attachment,
            ]);
            $this->clear_attachments();
            if (!empty($result['success'])) {
                $this->set_last_send_message_id($this->normalize_message_id((string) ($result['message_id'] ?? '')));
                hooks()->do_action('simple_email_sent', [
                    'email'   => $cnf['email'],
                    'subject' => $cnf['subject'],
                    'cnf'     => $cnf,
                    'message_id' => $this->get_last_send_message_id(),
                ]);
                return true;
            }
            $this->last_send_error = (string) ($result['message'] ?? 'brevo_api_failed');
            $this->last_send_error_code = (int) ($result['http_code'] ?? 0);
            hooks()->do_action('simple_email_failed', [
                'email'   => $cnf['email'],
                'subject' => $cnf['subject'],
                'cnf'     => $cnf,
                'error'   => $this->last_send_error,
            ]);
            return false;
        }
        $this->load->config('email');
        $this->email->clear(true);
        $this->email->set_newline(config_item('newline'));
        $this->email->from($cnf['from_email'], $cnf['from_name']);
        $this->email->to($cnf['email']);

        $bcc = '';
        // Used for action hooks
        if (isset($cnf['bcc'])) {
            $bcc = $cnf['bcc'];
            if (is_array($bcc)) {
                $bcc = implode(', ', $bcc);
            }
        }

        $systemBCC = get_option('bcc_emails');
        if ($systemBCC != '') {
            if ($bcc != '') {
                $bcc .= ', ' . $systemBCC;
            } else {
                $bcc .= $systemBCC;
            }
        }
        if ($bcc != '') {
            $this->email->bcc($bcc);
        }

        if (isset($cnf['cc'])) {
            $this->email->cc($cnf['cc']);
        }

        if (isset($cnf['reply_to'])) {
            $this->email->reply_to($cnf['reply_to']);
        }

        $this->email->subject($cnf['subject']);
        $this->email->message($cnf['message']);

        $this->email->set_alt_message(strip_html_tags($cnf['message'], '<br/>, <br>, <br />'));

        if (count($this->attachment) > 0) {
            foreach ($this->attachment as $attach) {
                if (!isset($attach['read'])) {
                    $this->email->attach($attach['attachment'], 'attachment', $attach['filename'], $attach['type']);
                } else {
                    if (!isset($attach['filename']) || (isset($attach['filename']) && empty($attach['filename']))) {
                        $attach['filename'] = basename($attach['attachment']);
                    }
                    $this->email->attach($attach['attachment'], '', $attach['filename']);
                }
            }
        }

        $this->clear_attachments();
        if ($this->email->send()) {
            $this->set_last_send_message_id($this->normalize_message_id());
            log_activity('Email sent to: ' . $cnf['email'] . ' Subject: ' . $cnf['subject']);
            hooks()->do_action('simple_email_sent', [
                'email'   => $cnf['email'],
                'subject' => $cnf['subject'],
                'cnf'     => $cnf,
                'message_id' => $this->get_last_send_message_id(),
            ]);

            return true;
        }

        $this->last_send_error = trim((string) $this->email->print_debugger(['headers', 'subject', 'body']));
        $this->last_send_error_code = 0;
        hooks()->do_action('simple_email_failed', [
            'email'   => $cnf['email'],
            'subject' => $cnf['subject'],
            'cnf'     => $cnf,
            'message_id' => '',
        ]);

        return false;
    }

    public function get_last_send_error()
    {
        return $this->last_send_error;
    }

    public function get_last_send_error_code()
    {
        return $this->last_send_error_code;
    }

    public function get_last_send_message_id()
    {
        return $this->last_send_message_id;
    }

    public function set_last_send_message_id($message_id)
    {
        $this->last_send_message_id = trim((string) $message_id);

        return $this;
    }

    protected function normalize_message_id($message_id = '')
    {
        $message_id = trim((string) $message_id);
        if ($message_id !== '') {
            return $message_id;
        }

        if (isset($this->email) && method_exists($this->email, 'get_last_message_id')) {
            $candidate = trim((string) $this->email->get_last_message_id());
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'local:' . uniqid('mail_', true);
    }

    private function send_via_brevo_api(array $payload)
    {
        $apiKey = trim((string) ($payload['api_key'] ?? ''));
        $toEmail = trim((string) ($payload['to_email'] ?? ''));
        if ($apiKey === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid Brevo API payload.'];
        }

        $request = [
            'sender' => [
                'email' => (string) ($payload['from_email'] ?? ''),
                'name'  => (string) ($payload['from_name'] ?? ''),
            ],
            'to' => [['email' => $toEmail]],
            'subject' => (string) ($payload['subject'] ?? ''),
            'htmlContent' => (string) ($payload['html_content'] ?? ''),
            'textContent' => (string) ($payload['text_content'] ?? ''),
        ];

        $replyTo = trim((string) ($payload['reply_to'] ?? ''));
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $request['replyTo'] = ['email' => $replyTo];
        }
        foreach (['cc', 'bcc'] as $field) {
            $list = isset($payload[$field]) && is_array($payload[$field]) ? $payload[$field] : [];
            if (!empty($list)) {
                $request[$field] = array_values(array_filter(array_map(static function ($email) {
                    $email = trim((string) $email);
                    return filter_var($email, FILTER_VALIDATE_EMAIL) ? ['email' => $email] : null;
                }, $list)));
            }
        }

        $attachments = isset($payload['attachments']) && is_array($payload['attachments']) ? $payload['attachments'] : [];
        if (!empty($attachments)) {
            $apiAttachments = [];
            foreach ($attachments as $attachment) {
                $path = (string) ($attachment['attachment'] ?? '');
                if ($path === '' || !is_file($path) || !is_readable($path)) {
                    continue;
                }
                $name = (string) ($attachment['filename'] ?? basename($path));
                $apiAttachments[] = ['name' => $name, 'content' => base64_encode((string) file_get_contents($path))];
            }
            if (!empty($apiAttachments)) {
                $request['attachment'] = $apiAttachments;
            }
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => $error !== '' ? $error : 'cURL error', 'http_code' => $httpCode];
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message_id' => (string) ($decoded['messageId'] ?? '')];
        }

        return ['success' => false, 'message' => (string) ($decoded['message'] ?? ('Brevo API HTTP ' . $httpCode)), 'http_code' => $httpCode];
    }

    /**
     * Send email template
     * @deprecated 2.3.0
     * @param  string $template_slug email template slug
     * @param  string $email         email to send
     * @param  array $merge_fields  merge field
     * @param  string $ticketid      used only when sending email templates linked to ticket / used for piping
     * @param  mixed $cc
     * @return boolean
     */
    public function send_email_template($template_slug, $email, $merge_fields, $ticketid = '', $cc = '')
    {
        $email = hooks()->apply_filters('send_email_template_to', $email);

        $template                     = get_email_template_for_sending($template_slug, $email);
        $staff_email_templates_slugs  = get_staff_email_templates_slugs();
        $client_email_templates_slugs = get_client_email_templates_slugs();

        $inactive_user_table_check = '';

        /**
         * Dont send email templates for non active contacts/staff
         * Do checking here
         */
        if (in_array($template_slug, $staff_email_templates_slugs)) {
            $inactive_user_table_check = db_prefix() . 'staff';
        } elseif (in_array($template_slug, $client_email_templates_slugs)) {
            $inactive_user_table_check = db_prefix() . 'contacts';
        }

        /**
         * Is really inactive?
         */
        if ($inactive_user_table_check != '') {
            $this->db->select('active')->where('email', $email);
            $user = $this->db->get($inactive_user_table_check)->row();
            if ($user && $user->active == 0) {
                $this->clear_attachments();
                $this->set_staff_id(null);

                return false;
            }
        }

        /**
         * Template not found?
         */
        if (!$template) {
            log_activity('Failed to send email template [Template not found]');
            $this->clear_attachments();
            $this->set_staff_id(null);

            return false;
        }

        /**
         * Template is disabled or invalid email?
         * Log activity
         */
        if ($template->active == 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->clear_attachments();

            $this->db->where('language', 'english');
            $this->db->where('slug', $template->slug);
            $tmpTemplate = $this->db->get(db_prefix() . 'emailtemplates')->row();

            if (!$tmpTemplate) {
                log_activity('Failed to send email template [<a href="' . admin_url('emails/email_template/' . $tmpTemplate->emailtemplateid) . '">' . $template->name . '</a>] [Reason: Email template is disabled.]');
            }

            return false;
        }

        $template = hooks()->apply_filters('before_parse_email_template_message', $template);

        $template = parse_email_template($template, $merge_fields);

        $template = hooks()->apply_filters('after_parse_email_template_message', $template);

        $template->message = get_option('email_header') . $template->message . get_option('email_footer');

        // Parse merge fields again in case there is merge fields found in email_header and email_footer option.
        // We cant parse this in parse_email_template function because in case the template content is send via $_POST wont work
        $template = parse_email_template_merge_fields($template, $merge_fields);

        /**
         * Template is plain text?
         */
        if ($template->plaintext == 1) {
            $this->config->set_item('mailtype', 'text');
            $template->message = strip_html_tags($template->message, '<br/>, <br>, <br />');
        }

        $fromemail = $template->fromemail;
        $fromname  = $template->fromname;

        if ($fromemail == '') {
            $fromemail = get_option('smtp_email');
        }

        if ($fromname == '') {
            $fromname = get_option('companyname');
        }

        /**
         * Ticket variables
        */
        $reply_to               = false;
        $from_header_dept_email = false;
        /**
         * Tickets template
         * For tickets there is different config
         */
        if (is_numeric($ticketid) && $template->type == 'ticket') {
            if (!class_exists('tickets_model')) {
                $this->load->model('tickets_model');
            }

            $this->db->select(db_prefix() . 'departments.email as department_email, email_from_header as dept_email_from_header')
            ->where('ticketid', $ticketid)
            ->join(db_prefix() . 'departments', db_prefix() . 'departments.departmentid=' . db_prefix() . 'tickets.department', 'left');

            $ticket = $this->db->get(db_prefix() . 'tickets')->row();

            if (!empty($ticket->department_email) && filter_var($ticket->department_email, FILTER_VALIDATE_EMAIL)) {
                $reply_to               = $ticket->department_email;
                $from_header_dept_email = $ticket->dept_email_from_header == 1;
            }
            /**
             * IMPORTANT
             * Do not change/remove this line, this is used for email piping so the software can recognize the ticket id.
             */
            if (substr($template->subject, 0, 10) != '[Ticket ID') {
                $template->subject = '[Ticket ID: ' . $ticketid . '] ' . $template->subject;
            }
        }

        $hook_data['template']    = $template;
        $hook_data['email']       = $email;
        $hook_data['attachments'] = $this->attachment;

        $hook_data['template']->message = check_for_links($hook_data['template']->message);

        $hook_data = hooks()->apply_filters('before_email_template_send', $hook_data);

        $template    = $hook_data['template'];
        $email       = $hook_data['email'];
        $attachments = $hook_data['attachments'];

        if (isset($template->prevent_sending) && $template->prevent_sending == true) {
            $this->clear_attachments();
            $this->set_staff_id(null);

            return false;
        }

        $this->load->config('email');
        $this->email->clear(true);
        $this->email->set_newline(config_item('newline'));
        $this->email->from(($from_header_dept_email ? $ticket->department_email : $fromemail), $fromname);
        $this->email->subject($template->subject);

        $this->email->message($template->message);
        $this->email->to($email);

        $bcc = '';
        // Used for action hooks
        if (isset($template->bcc)) {
            $bcc = $template->bcc;
            if (is_array($bcc)) {
                $bcc = implode(', ', $bcc);
            }
        }

        $systemBCC = get_option('bcc_emails');
        if ($systemBCC != '') {
            if ($bcc != '') {
                $bcc .= ', ' . $systemBCC;
            } else {
                $bcc .= $systemBCC;
            }
        }

        if ($bcc != '') {
            $bcc = array_map('trim', explode(',', $bcc));
            $bcc = array_unique($bcc);
            $bcc = implode(', ', $bcc);
            $this->email->bcc($bcc);
        }

        if ($reply_to != false) {
            $this->email->reply_to($reply_to);
        } elseif (isset($template->reply_to)) {
            $this->email->reply_to($template->reply_to);
        }

        if ($template->plaintext == 0) {
            $alt_message = strip_html_tags($template->message, '<br/>, <br>, <br />');
            // Replace <br /> with \n
            $alt_message = clear_textarea_breaks($alt_message, "\r\n");
            $this->email->set_alt_message($alt_message);
        }

        if (is_array($cc) || !empty($cc)) {
            $this->email->cc($cc);
        }

        if (count($attachments) > 0) {
            foreach ($attachments as $attach) {
                if (!isset($attach['read'])) {
                    $this->email->attach($attach['attachment'], 'attachment', $attach['filename'], $attach['type']);
                } else {
                    $this->email->attach($attach['attachment'], '', $attach['filename']);
                }
            }
        }

        $this->clear_attachments();
        $this->set_staff_id(null);

        if ($this->email->send()) {
            $this->set_last_send_message_id($this->normalize_message_id());
            log_activity('Email Send To [Email: ' . $email . ', Template: ' . $template->name . ']');
            hooks()->do_action('email_template_sent', ['template' => $template, 'email' => $email, 'message_id' => $this->get_last_send_message_id()]);

            return true;
        }

            log_activity('Failed to send email template - ' . $this->email->print_debugger());

        return false;
    }

    /**
     * @param resource
     * @param string
     * @param string (mime type)
     * @return none
     * Add attachment to property to check before an email is send
     */
    public function add_attachment($attachment)
    {
        $this->attachment[] = $attachment;
    }

    /**
     * @return none
     * Clear all attachment properties
     */
    private function clear_attachments()
    {
        $this->attachment = [];
    }

    /**
     * @deprecated 2.3.0
     */
    public function set_rel_id($rel_id)
    {
        $this->rel_id = $rel_id;
    }

    /**
     * @deprecated 2.3.0
     */
    public function set_rel_type($rel_type)
    {
        $this->rel_type = $rel_type;
    }

    /**
     * @deprecated 2.3.0
     */
    public function get_rel_id()
    {
        return $this->rel_id;
    }

    /**
     * @deprecated 2.3.0
     */
    public function get_rel_type()
    {
        return $this->rel_type;
    }

    /**
     * @deprecated 2.3.0
     */
    public function set_staff_id($id)
    {
        $this->staff_id = $id;
    }

    /**
     * @deprecated 2.3.0
     */
    public function get_staff_id()
    {
        return $this->staff_id;
    }
}
