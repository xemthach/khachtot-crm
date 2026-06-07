<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Prepares email template preview $data for the view
 * @param  string $template    template class name
 * @param  mixed $customer_id_or_email customer ID to fetch the primary contact email or email
 * @return array
 */
function prepare_mail_preview_data($template, $customer_id_or_email, $mailClassParams = [])
{
    $CI = &get_instance();

    if (is_numeric($customer_id_or_email)) {
        $contact = $CI->clients_model->get_contact(get_primary_contact_user_id($customer_id_or_email));
        $email   = $contact ? $contact->email : '';
    } else {
        $email = $customer_id_or_email;
    }

    $CI->load->model('emails_model');

    $data['template'] = $CI->app_mail_template->prepare($email, $template);
    $slug             = $CI->app_mail_template->get_default_property_value('slug', $template, $mailClassParams);

    $data['template_name'] = $slug;

    $template_result = $CI->emails_model->get(['slug' => $slug, 'language' => 'english'], 'row');

    $data['template_system_name'] = $template_result->name;
    $data['template_id']          = $template_result->emailtemplateid;

    $data['template_disabled'] = $template_result->active == 0;

    return $data;
}
/**
 * Parse email template with the merge fields
 * @param  mixed $template     template
 * @param  array  $merge_fields
 * @return object
 */
function parse_email_template($template, $merge_fields = [])
{
    $CI = & get_instance();
    if (!is_object($template) || $CI->input->post('template_name')) {
        $original_template = $template;

        if (!class_exists('emails_model', false)) {
            $CI->load->model('emails_model');
        }

        if ($CI->input->post('template_name')) {
            $template = $CI->input->post('template_name');
        }

        $template = $CI->emails_model->get(['slug' => $template], 'row');

        if ($CI->input->post('email_template_custom')) {
            $template->message = $CI->input->post('email_template_custom', false);
            // Replace the subject too
            $template->subject = $original_template->subject;
        }
    }

    $template = parse_email_template_merge_fields($template, $merge_fields);

    // Used in hooks eq for emails tracking
    $template->tmp_id = app_generate_hash();

    return hooks()->apply_filters('email_template_parsed', $template);
}

/**
 * This function will parse email template merge fields and replace with the corresponding merge fields passed before sending email
 * @param  object $template     template from database
 * @param  array $merge_fields available merge fields
 * @return object
 */
function parse_email_template_merge_fields($template, $merge_fields)
{
    $CI = &get_instance();

    if (!class_exists('other_merge_fields', false)) {
        $CI->load->library('merge_fields/other_merge_fields');
    }

    $merge_fields = array_merge($merge_fields, $CI->other_merge_fields->format());

    foreach ($merge_fields as $key => $val) {
        foreach (['message', 'fromname', 'subject'] as $section) {
            $template->{$section} = stripos($template->{$section}, $key) !== false
            ? str_replace($key, $val, $template->{$section})
            : str_replace($key, '', $template->{$section});
        }
    }

    return $template;
}

/**
 * Send mail template
 * @since  2.3.0
 * @return mixed
 */
function send_mail_template()
{
    $params = func_get_args();

    return mail_template(...$params)->send();
}

/**
 * Prepare mail template class
 * @param  string $class mail template class name
 * @return mixed
 */
function mail_template($class)
{
    $CI = &get_instance();

    $params = func_get_args();

    // First params is the $class param
    unset($params[0]);

    $params = array_values($params);

    $path = get_mail_template_path($class, $params);

    if (!file_exists($path)) {
        if (!defined('CRON')) {
            show_error('Mail Class Does Not Exists [' . $path . ']');
        } else {
            return false;
        }
    }

    // Include the mailable class
    if (!class_exists($class, false)) {
        include_once($path);
    }

    // Initialize the class and pass the params
    $instance = new $class(...$params);

    // Call the send method
    return $instance;
}

function get_mail_template_path($class, &$params)
{
    $CI  = &get_instance();
    $dir = APPPATH . 'libraries/mails/';

    // Check if second parameter is module and is activated so we can get the class from the module path
    // Also check if the first value is not equal to '/' e.q. when import is performed we set
    // for some values which are blank to "/"
    if (isset($params[0]) && is_string($params[0]) && $params[0] !== '/' && is_dir(module_dir_path($params[0]))) {
        $module = $CI->app_modules->get($params[0]);

        if ($module['activated'] === 1) {
            $dir = module_libs_path($params[0]) . 'mails/';
        }

        unset($params[0]);
        $params = array_values($params);
    }

    return $dir . ucfirst($class) . '.php';
}
/**
 * Create new email template
 * @param  string  $subject the predefined email template subject
 * @param  string  $message the predefined email template message
 * @param  string  $type    for what feature this email template is related e.q. invoice|ticket
 * @param  string  $name    the email template name which user see in Setup->Email Template, this is used for easier email template recognition
 * @param  string  $slug    unique email template slug
 * @param  integer $active  whether by default this email template is active
 * @return mixed
 */
function create_email_template($subject, $message, $type, $name, $slug, $active = 1)
{
    if (total_rows('emailtemplates', ['slug' => $slug]) > 0) {
        return false;
    }

    $data['subject']   = $subject;
    $data['message']   = $message;
    $data['type']      = $type;
    $data['name']      = $name;
    $data['slug']      = $slug;
    $data['language']  = 'english';
    $data['active']    = $active;
    $data['plaintext'] = 0;
    $data['fromname'] = '{companyname} | CRM';

    $CI                = &get_instance();
    $CI->load->model('emails_model');

    return $CI->emails_model->add_template($data);
}

/**
 * Localized defaults for customer/staff-facing email templates that are
 * initialized from the English source in Setup -> Email Templates.
 *
 * This prevents Vietnamese runtime rows from being created with English
 * subjects or blank messages.
 *
 * @param string $slug
 * @param string $language
 * @return array|null
 */
function kt_email_template_localized_defaults($slug, $language, $source = [])
{
    if ($language !== 'vietnamese') {
        return null;
    }

    $definitions = [
        'contact-forgot-password' => [
            'name' => 'Quên mật khẩu [vietnamese]',
            'subject' => 'Tạo mật khẩu mới',
            'message' => '<h2>Tạo mật khẩu mới</h2><p>Bạn quên mật khẩu?</p><p>Hãy dùng liên kết sau để tạo mật khẩu mới:</p><p><strong>{reset_password_url}</strong></p><p>Bạn nhận được email này vì có yêu cầu đặt lại mật khẩu cho tài khoản tại {companyname}. Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email và mật khẩu hiện tại sẽ không thay đổi.</p><p>{email_signature}</p>',
        ],
        'contact-password-reseted' => [
            'name' => 'Xác nhận đổi mật khẩu khách hàng [vietnamese]',
            'subject' => 'Mật khẩu của bạn đã được thay đổi',
            'message' => '<p><strong>Mật khẩu của bạn đã được thay đổi.</strong></p><p>Vui lòng lưu lại thông tin này để tránh quên mật khẩu.</p><p>Email đăng nhập của bạn: <strong>{contact_email}</strong></p><p>Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ ngay với bộ phận hỗ trợ.</p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'contact-set-password' => [
            'name' => 'Thiết lập mật khẩu mới [vietnamese]',
            'subject' => 'Thiết lập mật khẩu mới tại {companyname}',
            'message' => '<h2>Thiết lập mật khẩu mới tại {companyname}</h2><p>Vui lòng dùng liên kết bên dưới để tạo mật khẩu đăng nhập cho tài khoản của bạn.</p><p>Hãy lưu lại thông tin này để thuận tiện sử dụng về sau.</p><p>Liên kết có hiệu lực trong 48 giờ. Sau thời gian này, bạn sẽ cần yêu cầu tạo lại liên kết.</p><p>Trang đăng nhập: {crm_url}<br>Email đăng nhập: {contact_email}</p><p><strong>{set_password_url}</strong></p><p>{email_signature}</p>',
        ],
        'staff-forgot-password' => [
            'name' => 'Quên mật khẩu nhân sự [vietnamese]',
            'subject' => 'Tạo mật khẩu mới',
            'message' => '<h2>Tạo mật khẩu mới</h2><p>Bạn quên mật khẩu?</p><p>Hãy dùng liên kết sau để tạo mật khẩu mới:</p><p><strong>{reset_password_url}</strong></p><p>Bạn nhận được email này vì có yêu cầu đặt lại mật khẩu cho tài khoản tại {companyname}. Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email và mật khẩu hiện tại sẽ không thay đổi.</p><p>{email_signature}</p>',
        ],
        'staff-password-reseted' => [
            'name' => 'Xác nhận đổi mật khẩu nhân sự [vietnamese]',
            'subject' => 'Mật khẩu của bạn đã được thay đổi',
            'message' => '<p><strong>Mật khẩu của bạn đã được thay đổi.</strong></p><p>Vui lòng lưu lại thông tin này để tránh quên mật khẩu.</p><p>Email đăng nhập của bạn: <strong>{staff_email}</strong></p><p>Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ ngay với quản trị viên hệ thống.</p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'new-client-created' => [
            'name' => 'Chào mừng khách hàng mới [vietnamese]',
            'subject' => 'Chào mừng bạn đến với {companyname}',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Chào mừng bạn đến với {companyname}.</p><p>Tài khoản của bạn đã được tạo thành công. Bạn có thể đăng nhập và bắt đầu sử dụng hệ thống theo liên kết bên dưới:</p><p><a href="{crm_url}">{crm_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'invoice-send-to-client' => [
            'name' => 'Gửi hóa đơn cho khách hàng [vietnamese]',
            'subject' => 'Hóa đơn {invoice_number} đã được tạo',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Chúng tôi đã lập hóa đơn <strong>{invoice_number}</strong> cho bạn.</p><p><strong>Trạng thái hóa đơn:</strong> {invoice_status}</p><p>Bạn có thể xem hóa đơn tại đây: <a href="{invoice_link}">{invoice_number}</a></p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'invoice-due-notice' => [
            'name' => 'Nhắc hạn thanh toán hóa đơn [vietnamese]',
            'subject' => 'Hóa đơn {invoice_number} sắp đến hạn thanh toán',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Hóa đơn <strong>{invoice_number}</strong> sẽ đến hạn thanh toán vào <strong>{invoice_duedate}</strong>.</p><p>Bạn có thể xem hóa đơn tại đây: <a href="{invoice_link}">{invoice_link}</a></p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'invoice-overdue-notice' => [
            'name' => 'Nhắc hóa đơn quá hạn [vietnamese]',
            'subject' => 'Hóa đơn quá hạn thanh toán - {invoice_number}',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Hóa đơn <strong>{invoice_number}</strong> của bạn đã quá hạn từ ngày <strong>{invoice_duedate}</strong>.</p><p>Bạn có thể xem và thanh toán tại đây: <a href="{invoice_link}">{invoice_number}</a></p><p>Vui lòng hoàn tất thanh toán để tránh gián đoạn dịch vụ.</p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'invoice-payment-recorded' => [
            'name' => 'Xác nhận thanh toán hóa đơn [vietnamese]',
            'subject' => 'Đã ghi nhận thanh toán hóa đơn',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Cảm ơn bạn. Chúng tôi đã ghi nhận khoản thanh toán <strong>{payment_total}</strong> vào ngày <strong>{payment_date}</strong> cho hóa đơn <strong>{invoice_number}</strong>.</p><p>Bạn có thể xem lại hóa đơn tại đây: <a href="{invoice_link}">{invoice_number}</a></p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'invoice-payment-recorded-to-staff' => [
            'name' => 'Ghi nhận thanh toán hóa đơn cho nhân sự [vietnamese]',
            'subject' => 'Có thanh toán hóa đơn mới',
            'message' => '<p>Khách hàng đã thanh toán cho hóa đơn <strong>{invoice_number}</strong>.</p><p>Xem hóa đơn tại đây: <a href="{invoice_link}">{invoice_link}</a></p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'subscription-payment-failed' => [
            'name' => 'Thanh toán subscription thất bại [vietnamese]',
            'subject' => 'Thanh toán gần nhất cho gói đăng ký chưa thành công',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Thanh toán gần nhất cho gói đăng ký <strong>{subscription_name}</strong> chưa được xử lý thành công.</p><p>Vui lòng đăng nhập để cập nhật phương thức thanh toán tại: <a href="{crm_url}">{crm_url}</a></p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'subscription-payment-succeeded' => [
            'name' => 'Biên nhận thanh toán gói đăng ký [vietnamese]',
            'subject' => 'Biên nhận thanh toán gói đăng ký - {subscription_name}',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Chúng tôi đã nhận khoản thanh toán <strong>{payment_total}</strong> cho gói đăng ký <strong>{subscription_name}</strong>.</p><p>Hóa đơn liên quan hiện có trạng thái <strong>{invoice_status}</strong>.</p><p>Cảm ơn bạn đã tiếp tục sử dụng dịch vụ.</p><p>Trân trọng,<br>{email_signature}</p>',
        ],
        'subscription-canceled' => [
            'name' => 'Hủy gói đăng ký [vietnamese]',
            'subject' => 'Gói đăng ký của bạn đã được hủy',
            'message' => '<p>Xin chào {contact_firstname} {contact_lastname},</p><p>Gói đăng ký <strong>{subscription_name}</strong> của bạn đã được hủy.</p><p>Nếu bạn cần hỗ trợ kích hoạt lại, vui lòng liên hệ với chúng tôi.</p><p>Trân trọng,<br>{email_signature}</p>',
        ],
    ];

    if (isset($definitions[$slug])) {
        return $definitions[$slug];
    }

    return kt_email_template_generated_vietnamese_defaults($slug, $source);
}

/**
 * Build the Vietnamese default for core templates whose translated rows were
 * historically initialized with an English subject and an empty message.
 *
 * @param string $slug
 * @param array  $source English source template
 * @return array|null
 */
function kt_email_template_generated_vietnamese_defaults($slug, $source)
{
    $subjects = [
        'client-registration-confirmed' => 'Đăng ký tài khoản của bạn đã được xác nhận',
        'client-statement' => 'Sao kê tài khoản từ {statement_from} đến {statement_to}',
        'contact-verification-email' => 'Xác minh địa chỉ email của bạn',
        'new-client-registered-to-admin' => 'Có khách hàng mới đăng ký',
        'new-customer-profile-file-uploaded-to-staff' => 'Khách hàng vừa tải tệp mới lên hồ sơ',
        'contract-comment-to-admin' => 'Có bình luận mới về hợp đồng',
        'contract-comment-to-client' => 'Có bình luận mới về hợp đồng',
        'contract-expiration' => 'Nhắc nhở hợp đồng sắp hết hạn',
        'contract-expiration-to-staff' => 'Nhắc nhở hợp đồng sắp hết hạn',
        'contract-sign-reminder' => 'Nhắc ký hợp đồng',
        'contract-signed-to-staff' => 'Khách hàng đã ký hợp đồng',
        'send-contract' => 'Hợp đồng - {contract_subject}',
        'credit-note-send-to-client' => 'Phiếu ghi có số #{credit_note_number} đã được tạo',
        'estimate-accepted-to-staff' => 'Khách hàng đã chấp nhận báo giá',
        'estimate-already-send' => 'Báo giá số {estimate_number}',
        'estimate-declined-to-staff' => 'Khách hàng đã từ chối báo giá',
        'estimate-expiry-reminder' => 'Nhắc nhở báo giá sắp hết hạn',
        'estimate-send-to-client' => 'Báo giá số {estimate_number} đã được tạo',
        'estimate-thank-you-to-customer' => 'Cảm ơn bạn đã chấp nhận báo giá',
        'estimate-request-assigned' => 'Yêu cầu báo giá mới đã được giao',
        'estimate-request-received-to-user' => 'Đã tiếp nhận yêu cầu báo giá',
        'estimate-request-submitted-to-staff' => 'Có yêu cầu báo giá mới',
        'gdpr-removal-request' => 'Đã nhận yêu cầu xóa dữ liệu',
        'gdpr-removal-request-lead' => 'Đã nhận yêu cầu xóa dữ liệu',
        'inventory-warning-to-staff' => 'Cảnh báo tồn kho',
        'invoice-already-send' => 'Hóa đơn số {invoice_number}',
        'invoices-batch-payments' => 'Chúng tôi đã nhận được các khoản thanh toán của bạn',
        'new-lead-assigned' => 'Bạn được giao một khách hàng tiềm năng mới',
        'new-web-to-lead-form-submitted' => '{lead_name} - Chúng tôi đã nhận được yêu cầu của bạn',
        'non-billed-tasks-reminder' => 'Cần xử lý: Công việc đã hoàn tất nhưng chưa lập hóa đơn',
        'assigned-to-project' => 'Dự án mới đã được tạo',
        'new-project-discussion-comment-to-customer' => 'Có bình luận mới trong trao đổi dự án',
        'new-project-discussion-comment-to-staff' => 'Có bình luận mới trong trao đổi dự án',
        'new-project-discussion-created-to-customer' => 'Trao đổi dự án mới - {project_name}',
        'new-project-discussion-created-to-staff' => 'Trao đổi dự án mới - {project_name}',
        'new-project-file-uploaded-to-customer' => 'Có tệp mới trong dự án - {project_name}',
        'new-project-file-uploaded-to-staff' => 'Có tệp mới trong dự án - {project_name}',
        'project-finished-to-customer' => 'Dự án đã được đánh dấu hoàn tất',
        'staff-added-as-project-member' => 'Bạn được giao một dự án mới',
        'proposal-client-accepted' => 'Khách hàng đã chấp nhận đề xuất',
        'proposal-client-declined' => 'Khách hàng đã từ chối đề xuất',
        'proposal-client-thank-you' => 'Cảm ơn bạn đã chấp nhận đề xuất',
        'proposal-comment-to-admin' => 'Có bình luận mới về đề xuất',
        'proposal-comment-to-client' => 'Có bình luận mới về đề xuất',
        'proposal-expiry-reminder' => 'Nhắc nhở đề xuất sắp hết hạn',
        'proposal-send-to-customer' => 'Đề xuất số {proposal_number} đã được tạo',
        'event-notification-to-staff' => 'Sự kiện sắp diễn ra - {event_title}',
        'new-staff-created' => 'Tài khoản nhân sự của bạn đã được tạo',
        'reminder-email-staff' => 'Bạn có một lời nhắc mới',
        'two-factor-authentication' => 'Xác nhận đăng nhập',
        'customer-subscribed-to-staff' => 'Khách hàng đã đăng ký gói dịch vụ',
        'send-subscription' => 'Gói dịch vụ đã được tạo',
        'subscription-payment-requires-action' => 'Quan trọng: Xác nhận thanh toán gói {subscription_name}',
        'tenant-expiration-reminder' => 'Nhắc nhở không gian làm việc sắp hết hạn',
        'we-found-your-tenant-url' => 'Đã tìm thấy địa chỉ không gian làm việc của bạn',
        'task-added-as-follower' => 'Bạn được thêm làm người theo dõi công việc - {task_name}',
        'task-added-attachment' => 'Có tệp đính kèm mới trong công việc - {task_name}',
        'task-added-attachment-to-contacts' => 'Có tệp đính kèm mới trong công việc - {task_name}',
        'task-assigned' => 'Bạn được giao công việc mới - {task_name}',
        'task-commented' => 'Có bình luận mới trong công việc - {task_name}',
        'task-commented-to-contacts' => 'Có bình luận mới trong công việc - {task_name}',
        'task-deadline-notification' => 'Nhắc nhở hạn hoàn thành công việc',
        'task-status-change-to-contacts' => 'Trạng thái công việc đã thay đổi',
        'task-status-change-to-staff' => 'Trạng thái công việc đã thay đổi',
        'auto-close-ticket' => 'Phiếu hỗ trợ đã tự động đóng',
        'new-ticket-created-staff' => 'Có phiếu hỗ trợ mới',
        'new-ticket-opened-admin' => 'Phiếu hỗ trợ mới đã được mở',
        'ticket-assigned-to-admin' => 'Bạn được giao một phiếu hỗ trợ mới',
        'ticket-autoresponse' => 'Phiếu hỗ trợ mới đã được mở',
        'ticket-reply' => 'Có phản hồi mới cho phiếu hỗ trợ',
        'ticket-reply-to-admin' => 'Có phản hồi mới cho phiếu hỗ trợ',
    ];

    if (!isset($subjects[$slug])) {
        return null;
    }

    $canonical = (string) ($source['subject'] ?? '') . ' ' . (string) ($source['message'] ?? '');
    preg_match_all('/\{[A-Za-z0-9_]+\}/', $canonical, $matches);
    $fields = array_values(array_unique($matches[0]));

    $message = kt_email_template_build_vietnamese_message($subjects[$slug], $fields);
    $name = trim((string) preg_replace('/\s*[-–]?\s*\{[^}]+\}/', '', $subjects[$slug]));

    return [
        'name' => $name . ' [vietnamese]',
        'subject' => $subjects[$slug],
        'message' => $message,
    ];
}

/**
 * Generate a readable Vietnamese message while preserving the canonical
 * merge-field set from the English source template.
 *
 * @param string $summary
 * @param array  $fields
 * @return string
 */
function kt_email_template_build_vietnamese_message($summary, $fields)
{
    $labels = [
        'admin_url' => 'Trang quản trị',
        'batch_payments_list' => 'Chi tiết thanh toán',
        'client_company' => 'Doanh nghiệp',
        'comment_creator' => 'Người bình luận',
        'companyname' => 'Tên doanh nghiệp',
        'contact_email' => 'Email',
        'contact_firstname' => 'Tên',
        'contact_lastname' => 'Họ',
        'contract_dateend' => 'Ngày kết thúc',
        'contract_datestart' => 'Ngày bắt đầu',
        'contract_description' => 'Nội dung hợp đồng',
        'contract_link' => 'Xem hợp đồng',
        'contract_subject' => 'Tên hợp đồng',
        'credit_note_date' => 'Ngày lập',
        'credit_note_number' => 'Số phiếu ghi có',
        'credit_note_total' => 'Tổng giá trị',
        'crm_url' => 'Trang đăng nhập',
        'customer_profile_files_admin_link' => 'Xem tệp khách hàng',
        'discussion_comment' => 'Nội dung bình luận',
        'discussion_creator' => 'Người tạo trao đổi',
        'discussion_description' => 'Nội dung trao đổi',
        'discussion_link' => 'Xem trao đổi',
        'discussion_subject' => 'Chủ đề trao đổi',
        'email_verification_url' => 'Xác minh email',
        'estimate_expirydate' => 'Ngày hết hạn',
        'estimate_link' => 'Xem báo giá',
        'estimate_number' => 'Số báo giá',
        'estimate_request_assigned' => 'Người phụ trách',
        'estimate_request_email' => 'Email người gửi',
        'estimate_request_form_name' => 'Biểu mẫu',
        'estimate_request_id' => 'Mã yêu cầu',
        'estimate_request_link' => 'Xem yêu cầu báo giá',
        'estimate_request_submitted_data' => 'Nội dung yêu cầu',
        'estimate_status' => 'Trạng thái báo giá',
        'event_link' => 'Xem sự kiện',
        'event_start_date' => 'Thời gian bắt đầu',
        'event_title' => 'Tên sự kiện',
        'file_creator' => 'Người tải tệp',
        'invoice_link' => 'Xem hóa đơn',
        'invoice_number' => 'Số hóa đơn',
        'lead_assigned' => 'Người phụ trách',
        'lead_email' => 'Email',
        'lead_link' => 'Xem khách hàng tiềm năng',
        'lead_name' => 'Khách hàng tiềm năng',
        'password' => 'Mật khẩu tạm thời',
        'project_link' => 'Xem dự án',
        'project_name' => 'Tên dự án',
        'project_start_date' => 'Ngày bắt đầu',
        'proposal_link' => 'Xem đề xuất',
        'proposal_number' => 'Số đề xuất',
        'proposal_open_till' => 'Có hiệu lực đến',
        'proposal_proposal_to' => 'Người nhận',
        'proposal_subject' => 'Tên đề xuất',
        'proposal_total' => 'Tổng giá trị',
        'staff_email' => 'Email đăng nhập',
        'staff_firstname' => 'Tên nhân sự',
        'staff_lastname' => 'Họ nhân sự',
        'staff_name' => 'Nhân sự',
        'staff_reminder_description' => 'Nội dung nhắc nhở',
        'staff_reminder_relation_link' => 'Xem nội dung liên quan',
        'staff_reminder_relation_name' => 'Nội dung liên quan',
        'statement_balance_due' => 'Số dư cần thanh toán',
        'statement_from' => 'Từ ngày',
        'statement_to' => 'Đến ngày',
        'subscription_authorize_payment_link' => 'Xác nhận thanh toán',
        'subscription_description' => 'Mô tả gói dịch vụ',
        'subscription_id' => 'Mã đăng ký',
        'subscription_link' => 'Xem gói dịch vụ',
        'subscription_name' => 'Tên gói dịch vụ',
        'task_comment' => 'Nội dung bình luận',
        'task_duedate' => 'Hạn hoàn thành',
        'task_link' => 'Xem công việc',
        'task_name' => 'Tên công việc',
        'task_priority' => 'Mức ưu tiên',
        'task_startdate' => 'Ngày bắt đầu',
        'task_status' => 'Trạng thái',
        'task_user_take_action' => 'Người thực hiện',
        'TENANT_EMAIL' => 'Email đăng nhập',
        'TENANTS_LOGIN_URL' => 'Địa chỉ đăng nhập',
        'ticket_department' => 'Bộ phận hỗ trợ',
        'ticket_id' => 'Mã phiếu',
        'ticket_message' => 'Nội dung',
        'ticket_priority' => 'Mức ưu tiên',
        'ticket_public_url' => 'Xem phiếu hỗ trợ',
        'ticket_subject' => 'Chủ đề',
        'ticket_url' => 'Xem phiếu hỗ trợ',
        'two_factor_auth_code' => 'Mã xác nhận',
        'unbilled_tasks_list' => 'Danh sách công việc',
        'notification_content' => 'Nội dung cảnh báo',
    ];

    $linkFields = [
        'admin_url',
        'crm_url',
        'customer_profile_files_admin_link',
        'contract_link',
        'discussion_link',
        'email_verification_url',
        'estimate_link',
        'estimate_request_link',
        'event_link',
        'invoice_link',
        'lead_link',
        'project_link',
        'proposal_link',
        'staff_reminder_relation_link',
        'subscription_authorize_payment_link',
        'subscription_link',
        'task_link',
        'TENANTS_LOGIN_URL',
        'ticket_public_url',
        'ticket_url',
    ];

    $rows = [];
    $signature = '';
    foreach ($fields as $field) {
        $key = trim($field, '{}');
        if ($key === 'email_signature') {
            $signature = $field;
            continue;
        }

        $value = in_array($key, $linkFields, true)
            ? '<a href="' . $field . '">' . $field . '</a>'
            : $field;
        $rows[] = '<tr><td style="padding:6px 12px 6px 0"><strong>'
            . ($labels[$key] ?? 'Thông tin')
            . ':</strong></td><td style="padding:6px 0">' . $value . '</td></tr>';
    }

    $message = '<p>Xin chào,</p><p>' . $summary . '.</p>';
    if ($rows !== []) {
        $message .= '<table cellpadding="0" cellspacing="0" border="0">' . implode('', $rows) . '</table>';
    }
    $message .= '<p>Vui lòng liên hệ với chúng tôi nếu bạn cần hỗ trợ thêm.</p>';
    if ($signature !== '') {
        $message .= '<p>Trân trọng,<br>' . $signature . '</p>';
    }

    return $message;
}

/**
 * Check whether an email template is active based on given slug
 *
 * @since 2.7.0
 *
 * @param  string  $slug
 *
 * @return boolean
 */
function is_email_template_active($slug)
{
    return total_rows(db_prefix() . 'emailtemplates', ['slug' => $slug, 'active' => 1]) > 0;
}
