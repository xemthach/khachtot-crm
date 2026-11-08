<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Qa_auth extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Qa_auth_model');
    }

    public function redeem()
    {
        if (!$this->input->is_cli_request() && strtoupper((string) $this->input->method()) !== 'POST') {
            show_error('Phương thức không được hỗ trợ.', 405);
            return;
        }

        try {
            $row = $this->Qa_auth_model->redeem(
                (string) $this->input->post('token_id', true),
                (string) $this->input->post('token', false),
                (string) $this->input->ip_address(),
                (string) $this->input->user_agent()
            );

            $this->session->set_userdata([
                'staff_user_id' => (int) $row['staff_id'],
                'staff_logged_in' => true,
                'qa_auth_tenant_id' => !empty($row['tenant_id']) ? (int) $row['tenant_id'] : null,
                'qa_auth_role' => (string) $row['role'],
            ]);

            hooks()->do_action('after_staff_login');
            redirect(admin_url());
        } catch (RuntimeException $exception) {
            show_error(html_escape($exception->getMessage()), 403, 'Không thể đăng nhập QA');
        }
    }
}
