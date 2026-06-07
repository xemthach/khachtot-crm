<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $this->load->view('authentication/includes/head.php'); ?>

<body class="tw-bg-neutral-100 authentication forgot-password">
    <?php
    $ktSaasAuthContext = function_exists('kt_saas_auth_context') ? kt_saas_auth_context() : null;
    $tenantHostHint = $_GET['tenant_host'] ?? ((function_exists('kt_saas_auth_context') ? (kt_saas_auth_context()['host'] ?? '') : ''));
    $tenantHostHint = strtolower(trim((string) $tenantHostHint));
    $authHost = $tenantHostHint !== '' ? $tenantHostHint : ($_SERVER['HTTP_HOST'] ?? parse_url(APP_BASE_URL, PHP_URL_HOST));
    $authScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $authBasePath = trim(get_admin_uri(), '/');
    $forgotPasswordActionUrl = $authScheme . '://' . $authHost . '/' . $authBasePath . '/authentication/forgot_password';
    ?>

    <div class="tw-max-w-md tw-mx-auto tw-pt-24 authentication-form-wrappe tw-relative tw-z-20">
        <div class="company-logo text-center">
            <?php if (!empty($ktSaasAuthContext['is_tenant']) && !empty($ktSaasAuthContext['company_logo_dark_url'])) { ?>
                <a href="<?= site_url(); ?>" class="logo img-responsive">
                    <img src="<?= e($ktSaasAuthContext['company_logo_dark_url'] ?? ''); ?>" class="img-responsive" alt="<?= e($ktSaasAuthContext['company_name'] ?? 'Tenant'); ?>">
                </a>
            <?php } else { ?>
                <?= get_dark_company_logo(); ?>
            <?php } ?>
        </div>

        <h1 class="tw-text-2xl tw-text-neutral-800 text-center tw-font-semibold tw-mb-5">
            <?= _l('admin_auth_forgot_password_heading'); ?>
        </h1>

        <div
            class="tw-bg-white tw-mx-2 sm:tw-mx-6 tw-py-8 tw-px-6 sm:tw-px-8 tw-shadow-sm tw-rounded-lg tw-border tw-border-solid tw-border-neutral-600/20">
            <form action="<?= html_escape($forgotPasswordActionUrl); ?>" method="post" accept-charset="utf-8">
            <input type="hidden" name="<?= html_escape($this->security->get_csrf_token_name()); ?>" value="<?= html_escape($this->security->get_csrf_hash()); ?>">
            <?php if ($tenantHostHint !== '') { ?>
            <input type="hidden" name="tenant_host" value="<?= html_escape($tenantHostHint); ?>">
            <?php } ?>

            <?= validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>

            <?php $this->load->view('authentication/includes/alerts'); ?>

            <?= render_input('email', 'admin_auth_forgot_password_email', set_value('email'), 'email'); ?>

            <button type="submit" class="btn btn-primary btn-block tw-font-semibold tw-py-2">
                <?= _l('admin_auth_forgot_password_button'); ?>
            </button>

            </form>
        </div>

    </div>
</body>

</html>

