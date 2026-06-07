<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read Announcements_model  $announcements_model
 * @property-read Authentication_model $Authentication_model
 * @property-read App_Form_validation  $form_validation
 */
class Authentication extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        if ($this->app->is_db_upgrade_required()) {
            redirect($this->currentAdminUrl());
        }

        load_admin_language();
        $this->load->model('Authentication_model');
        $this->load->library('form_validation');

        $this->maybeRedirectToTenantHost();

        $this->form_validation->set_message('required', _l('form_validation_required'));
        $this->form_validation->set_message('valid_email', _l('form_validation_valid_email'));
        $this->form_validation->set_message('matches', _l('form_validation_matches'));

        hooks()->do_action('admin_auth_init');
    }

    public function index()
    {
        $this->admin();
    }

    public function admin()
    {
        if (is_staff_logged_in()) {
            redirect($this->currentAdminUrl());
        }

        $this->form_validation->set_rules('password', _l('admin_auth_login_password'), 'required');
        $this->form_validation->set_rules('email', _l('admin_auth_login_email'), 'trim|required|valid_email');
        if (show_recaptcha()) {
            $this->form_validation->set_rules('g-recaptcha-response', 'Captcha', 'callback_recaptcha');
        }
        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $email    = $this->input->post('email');
                $password = $this->input->post('password', false);
                $remember = $this->input->post('remember');

                $data = $this->Authentication_model->login($email, $password, $remember, true);

                if (is_array($data) && isset($data['memberinactive'])) {
                    set_alert('danger', _l('admin_auth_inactive_account'));
                    redirect($this->currentAdminUrl('authentication'));
                } elseif (is_array($data) && isset($data['two_factor_auth'])) {
                    $this->session->set_userdata('_two_factor_auth_established', true);
                    if ($data['user']->two_factor_auth_enabled == 1) {
                        $this->Authentication_model->set_two_factor_auth_code($data['user']->staffid);
                        $sent = send_mail_template('staff_two_factor_auth_key', $data['user']);

                        if (! $sent) {
                            set_alert('danger', _l('two_factor_auth_failed_to_send_code'));
                            redirect($this->currentAdminUrl('authentication'));
                        } else {
                            $this->session->set_userdata('_two_factor_auth_staff_email', $email);
                            set_alert('success', _l('two_factor_auth_code_sent_successfully', $email));
                            redirect($this->currentAdminUrl('authentication/two_factor'));
                        }
                    } else {
                        set_alert('success', _l('enter_two_factor_auth_code_from_mobile'));
                        redirect($this->currentAdminUrl('authentication/two_factor/app'));
                    }
                } elseif ($data == false) {
                    set_alert('danger', _l('admin_auth_invalid_email_or_password'));
                    redirect($this->currentAdminUrl('authentication'));
                }

                $this->load->model('announcements_model');
                $this->announcements_model->set_announcements_as_read_except_last_one(get_staff_user_id(), true);

                // is logged in
                maybe_redirect_to_previous_url();

                hooks()->do_action('after_staff_login');
                redirect($this->currentAdminUrl());
            }
        }

        $data['title'] = _l('admin_auth_login_heading');
        $this->load->view('authentication/login_admin', $data);
    }

    public function two_factor($type = 'email')
    {
        if (! $this->session->has_userdata('_two_factor_auth_established')) {
            show_404();
        }

        $this->form_validation->set_rules('code', _l('two_factor_authentication_code'), 'required');

        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $code  = $this->input->post('code');
                $code  = trim($code);
                $email = $this->session->userdata('_two_factor_auth_staff_email');
                if ($this->Authentication_model->is_two_factor_code_valid($code, $email) && $type = 'email') {
                    $this->session->unset_userdata('_two_factor_auth_staff_email');

                    $user = $this->Authentication_model->get_user_by_two_factor_auth_code($code);
                    $this->Authentication_model->clear_two_factor_auth_code($user->staffid);
                    $this->Authentication_model->two_factor_auth_login($user);
                    $this->session->unset_userdata('_two_factor_auth_established');
                    $this->load->model('announcements_model');
                    $this->announcements_model->set_announcements_as_read_except_last_one(get_staff_user_id(), true);

                    maybe_redirect_to_previous_url();

                    hooks()->do_action('after_staff_login');
                    redirect($this->currentAdminUrl());
                } elseif ($this->Authentication_model->is_google_two_factor_code_valid($code) && $type = 'app') {
                    $user = get_staff($this->session->userdata('tfa_staffid'));
                    $this->Authentication_model->two_factor_auth_login($user);
                    $this->session->unset_userdata('_two_factor_auth_established');
                    $this->load->model('announcements_model');
                    $this->announcements_model->set_announcements_as_read_except_last_one(get_staff_user_id(), true);

                    maybe_redirect_to_previous_url();

                    hooks()->do_action('after_staff_login');
                    redirect($this->currentAdminUrl());
                } else {
                    log_activity('Failed Two factor authentication attempt [Staff Name: ' . get_staff_full_name() . ', IP: ' . $this->input->ip_address() . ']');

                    set_alert('danger', _l('two_factor_code_not_valid'));
                    redirect($this->currentAdminUrl('authentication/two_factor/' . $type));
                }
            }
        }

        $this->load->view('authentication/set_two_factor_auth_code');
    }

    public function forgot_password()
    {
        if (is_staff_logged_in()) {
            redirect($this->currentAdminUrl());
        }
        $this->form_validation->set_rules('email', _l('admin_auth_login_email'), 'trim|required|valid_email|callback_email_exists');
        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $success = $this->Authentication_model->forgot_password($this->input->post('email'), true);
                if (is_array($success) && isset($success['memberinactive'])) {
                    set_alert('danger', _l('inactive_account'));
                    redirect($this->currentAdminUrl('authentication/forgot_password'));
                } elseif ($success == true) {
                    set_alert('success', _l('check_email_for_resetting_password'));
                    redirect($this->currentAdminUrl('authentication'));
                } else {
                    set_alert('danger', _l('error_setting_new_password_key'));
                    redirect($this->currentAdminUrl('authentication/forgot_password'));
                }
            }
        }
        $this->load->view('authentication/forgot_password');
    }

    public function reset_password($staff, $userid, $new_pass_key)
    {
        if (! $this->Authentication_model->can_reset_password($staff, $userid, $new_pass_key)) {
            set_alert('danger', _l('password_reset_key_expired'));
            redirect($this->currentAdminUrl('authentication'));
        }
        $this->form_validation->set_rules('password', _l('admin_auth_reset_password'), 'required');
        $this->form_validation->set_rules('passwordr', _l('admin_auth_reset_password_repeat'), 'required|matches[password]');
        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                hooks()->do_action('before_user_reset_password', [
                    'staff'  => $staff,
                    'userid' => $userid,
                ]);
                $success = $this->Authentication_model->reset_password($staff, $userid, $new_pass_key, $this->input->post('passwordr', false));
                if (is_array($success) && $success['expired'] == true) {
                    set_alert('danger', _l('password_reset_key_expired'));
                } elseif ($success == true) {
                    hooks()->do_action('after_user_reset_password', [
                        'staff'  => $staff,
                        'userid' => $userid,
                    ]);
                    set_alert('success', _l('password_reset_message'));
                } else {
                    set_alert('danger', _l('password_reset_message_fail'));
                }
                redirect($this->currentAdminUrl('authentication'));
            }
        }
        $this->load->view('authentication/reset_password');
    }

    public function set_password($staff, $userid, $new_pass_key)
    {
        if (! $this->Authentication_model->can_set_password($staff, $userid, $new_pass_key)) {
            set_alert('danger', _l('password_reset_key_expired'));
            if ($staff == 1) {
                redirect($this->currentAdminUrl('authentication'));
            } else {
                redirect(site_url('authentication'));
            }
        }
        $this->form_validation->set_rules('password', _l('admin_auth_set_password'), 'required');
        $this->form_validation->set_rules('passwordr', _l('admin_auth_set_password_repeat'), 'required|matches[password]');
        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $success = $this->Authentication_model->set_password($staff, $userid, $new_pass_key, $this->input->post('passwordr', false));
                if (is_array($success) && $success['expired'] == true) {
                    set_alert('danger', _l('password_reset_key_expired'));
                } elseif ($success == true) {
                    set_alert('success', _l('password_reset_message'));
                } else {
                    set_alert('danger', _l('password_reset_message_fail'));
                }
                if ($staff == 1) {
                    redirect($this->currentAdminUrl('authentication'));
                } else {
                    redirect(site_url());
                }
            }
        }
        $this->load->view('authentication/set_password');
    }

    public function logout()
    {
        $this->Authentication_model->logout();
        hooks()->do_action('after_user_logout');
        redirect($this->currentAdminUrl('authentication'));
    }

    public function clear_session()
    {
        $cookiePath = config_item('cookie_path') ?: '/';
        $cookieDomain = config_item('cookie_domain') ?: '';
        $secure = (bool) config_item('cookie_secure');
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(trim((string) $host));
        if (strpos($host, ':') !== false) {
            $parts = explode(':', $host);
            $host = $parts[0];
        }

        $legacySessionCookie = 'sp_session';
        $scopedSessionCookie = $legacySessionCookie;
        if ($host !== '') {
            $namespace = preg_replace('/[^a-z0-9_\\-\\.]/', '_', $host);
            $namespace = str_replace('.', '_', $namespace);
            $namespace = trim((string) $namespace, '_');
            if ($namespace !== '') {
                $scopedSessionCookie = substr($legacySessionCookie . '_' . $namespace, 0, 120);
            }
        }

        $cookies = array_unique(array_filter([
            $legacySessionCookie,
            $scopedSessionCookie,
            config_item('csrf_cookie_name'),
            'autologin',
        ]));

        foreach ($cookies as $cookieName) {
            delete_cookie($cookieName, $cookieDomain, $cookiePath);
            setcookie($cookieName, '', time() - 3600, $cookiePath, $cookieDomain, $secure, true);
            if ($host !== '' && $cookieDomain === '') {
                setcookie($cookieName, '', time() - 3600, $cookiePath, $host, $secure, true);
            }
        }

        $this->session->sess_destroy();
        session_write_close();

        redirect($this->currentAdminUrl('authentication'));
    }

    private function currentAdminUrl($path = '')
    {
        $baseUrl = $this->resolvedAdminBaseUrl();
        $baseUrl = rtrim((string) $baseUrl, '/') . '/';
        $adminUri = trim(get_admin_uri(), '/');
        $path = trim((string) $path, '/');

        $url = $baseUrl . $adminUri;
        if ($path !== '') {
            $url .= '/' . $path;
        }

        return $url;
    }

    private function resolvedAdminBaseUrl()
    {
        $tenantHost = $this->currentRequestHost();
        if ($tenantHost !== '' && !$this->isKnownTenantHost($tenantHost)) {
            $tenantHost = '';
        }

        if ($tenantHost === '') {
            $tenantHost = $this->requestedTenantHost();
        }

        if ($tenantHost === '' && function_exists('kt_saas_auth_context')) {
            $authContext = kt_saas_auth_context();
            $tenantHost = strtolower(trim((string) ($authContext['host'] ?? '')));
        }

        if ($tenantHost !== '' && $this->isKnownTenantHost($tenantHost)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ? 'https'
                : (parse_url(APP_BASE_URL, PHP_URL_SCHEME) ?: 'https');

            return $scheme . '://' . $tenantHost;
        }

        return (string) $this->config->item('base_url');
    }

    private function maybeRedirectToTenantHost()
    {
        $tenantHost = $this->requestedTenantHost();
        if ($tenantHost === '' || $tenantHost === $this->currentRequestHost()) {
            return;
        }

        if (! $this->isKnownTenantHost($tenantHost)) {
            return;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : (parse_url(APP_BASE_URL, PHP_URL_SCHEME) ?: 'https');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = $path ?: '/' . ltrim((string) uri_string(), '/');

        $query = $_GET;
        unset($query['tenant_host']);

        $target = $scheme . '://' . $tenantHost . $path;
        if (! empty($query)) {
            $target .= '?' . http_build_query($query);
        }

        redirect($target, 'location', $this->input->method(true) === 'POST' ? 307 : 302);
    }

    private function requestedTenantHost()
    {
        $tenantHost = $this->input->get_post('tenant_host', true);
        $tenantHost = strtolower(trim((string) $tenantHost));

        if (strpos($tenantHost, ':') !== false) {
            $parts = explode(':', $tenantHost);
            $tenantHost = $parts[0];
        }

        return preg_replace('/[^a-z0-9\.\-]/', '', $tenantHost) ?: '';
    }

    private function currentRequestHost()
    {
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(trim((string) $host));

        if (strpos($host, ':') !== false) {
            $parts = explode(':', $host);
            $host = $parts[0];
        }

        return $host;
    }

    private function isKnownTenantHost($tenantHost)
    {
        $moduleRoot = APP_MODULES_PATH . 'kt_saas' . DIRECTORY_SEPARATOR;
        if (!is_dir($moduleRoot)) {
            return false;
        }

        $helperPath = $moduleRoot . 'helpers' . DIRECTORY_SEPARATOR . 'kt_saas_helper.php';
        if (file_exists($helperPath) && ! function_exists('kt_saas_get_option')) {
            require_once $helperPath;
        }

        $runtimeEnabled = function_exists('kt_saas_get_option')
            ? kt_saas_get_option('kt_saas_runtime_enabled', '0')
            : '0';

        if ($runtimeEnabled !== '1') {
            return false;
        }

        require_once $moduleRoot . 'tenant_bootstrap' . DIRECTORY_SEPARATOR . 'TenantResolver.php';
        $resolver = new TenantResolver();
        $tenant = $resolver->resolveByHost($tenantHost);

        return !empty($tenant['resolved']);
    }

    public function email_exists($email)
    {
        $total_rows = total_rows(db_prefix() . 'staff', [
            'email' => $email,
        ]);
        if ($total_rows == 0) {
            $this->form_validation->set_message('email_exists', _l('auth_reset_pass_email_not_found'));

            return false;
        }

        return true;
    }

    public function recaptcha($str = '')
    {
        return do_recaptcha_validation($str);
    }

    public function get_qr()
    {
        if (! is_staff_logged_in()) {
            ajax_access_denied();
        }

        // Check if imagick extension is available
        if (! extension_loaded('imagick')) {
            echo '<div class="alert alert-danger">';
            echo '<i class="fa fa-exclamation-triangle"></i> ';
            echo '<strong>Error:</strong> The PHP imagick extension is required for Google Two-Factor Authentication but is not installed on this server. Please contact your system administrator to install the imagick extension.';
            echo '</div>';

            return;
        }

        $company_name = preg_replace('/:/', '-', get_option('companyname'));

        if ($company_name == '') {
            // Colons is not allowed in the issuer name
            $company_name = rtrim(preg_replace('/^https?:\/\//', '', site_url()), '/') . ' - CRM';
        }

        $data = $this->Authentication_model->get_qr($company_name);
        $this->load->view('admin/includes/google_two_factor', $data);
    }
}
