<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Verification extends ClientsController
{
    public function index()
    {
        if (is_contact_email_verified()) {
            redirect(site_url('clients'));
        }

        $data['title'] = _l('email_verification_required');

        $this->view('verification_required');
        $this->data($data);
        $this->layout();
    }

    public function verify($id, $key = '')
    {
        $contact = $this->clients_model->get_contact($id);

        if (!$contact) {
            show_404();
        }

        if (!is_null($contact->email_verified_at)) {
            set_alert('info', _l('email_already_verified'));
            redirect(site_url('clients'));
        }

        if ($key === '' || $contact->email_verification_key !== $key) {
            return $this->renderVerificationFailure(
                403,
                _l('invalid_verification_key'),
                'authentication/login',
                _l('clients_login_heading_no_register')
            );
        }

        $timestamp_now_minus_2_days = time() - (2 * 86400);
        $contact_registered         = strtotime($contact->email_verification_sent_at);

        if ($timestamp_now_minus_2_days > $contact_registered) {
            return $this->renderVerificationFailure(
                410,
                _l('verification_key_expired'),
                'authentication/login',
                _l('clients_login_heading_no_register')
            );
        }

        $this->clients_model->mark_email_as_verified($contact->id);

        // User not yet confirmed
        // from option customers_register_require_confirmation
        if (total_rows(db_prefix() . 'clients', ['userid' => $contact->userid, 'registration_confirmed' => 0]) > 0) {
            set_alert('info', _l('email_successfully_verified_but_required_admin_confirmation'));

            hooks()->do_action('contact_email_verified_but_requires_admin_confirmation', $contact);
        } else {
            set_alert('success', _l('email_successfully_verified'));

            hooks()->do_action('contact_email_verified', $contact);
        }

        $redUri = is_client_logged_in() ? 'clients' : 'authentication';
        redirect(site_url($redUri));
    }

    public function resend()
    {
        if (is_contact_email_verified() || !is_client_logged_in()) {
            redirect(site_url('clients'));
        }

        if ($this->clients_model->send_verification_email(get_contact_user_id())) {
            set_alert('success', _l('email_verification_mail_sent_successully'));
        } else {
            set_alert('danger', 'Failed to sent email verification mail, contact webmaster for more information.');
        }

        redirect(site_url('verification'));
    }

    private function renderVerificationFailure($statusCode, $message, $ctaUri, $ctaLabel)
    {
        $data['title'] = _l('email_verification_required');
        $data['verification_heading'] = $message;
        $data['verification_message'] = $statusCode === 410
            ? 'Liên kết xác minh đã hết hạn. Vui lòng đăng nhập để gửi lại email xác minh.'
            : 'Liên kết xác minh không hợp lệ. Vui lòng đăng nhập để yêu cầu email xác minh mới.';
        $data['verification_alert_class'] = 'danger';
        $data['verification_cta_url'] = site_url($ctaUri);
        $data['verification_cta_label'] = $ctaLabel;

        $this->output->set_status_header((int) $statusCode);
        $this->view('verification_required');
        $this->data($data);
        $this->layout();

        return;
    }
}
