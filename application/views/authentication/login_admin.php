<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $this->load->view('authentication/includes/head.php'); ?>

<body class="tw-bg-neutral-100 login_admin">
    <?php $ktSaasAuthContext = function_exists('kt_saas_auth_context') ? kt_saas_auth_context() : null; ?>
    <?php
    $tenantHostHint = $_GET['tenant_host'] ?? ($ktSaasAuthContext['host'] ?? '');
    $tenantHostHint = strtolower(trim((string) $tenantHostHint));
    $authHost = $tenantHostHint !== '' ? $tenantHostHint : ($_SERVER['HTTP_HOST'] ?? parse_url(APP_BASE_URL, PHP_URL_HOST));
    $authScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $authBasePath = trim(get_admin_uri(), '/');
    $authActionUrl = $authScheme . '://' . $authHost . '/' . $authBasePath . '/authentication';
    $tenantHostQuery = $tenantHostHint !== '' ? '?tenant_host=' . rawurlencode($tenantHostHint) : '';
    $forgotPasswordUrl = $authActionUrl . '/forgot_password' . $tenantHostQuery;
    ?>

    <div class="tw-max-w-md tw-mx-auto tw-pt-24 authentication-form-wrapper tw-relative tw-z-20">
        <div class="company-logo text-center">
            <?php if (!empty($ktSaasAuthContext['is_tenant']) && !empty($ktSaasAuthContext['company_logo_dark_url'])) { ?>
                <a href="<?= site_url(); ?>" class="logo img-responsive">
                    <img src="<?= e($ktSaasAuthContext['company_logo_dark_url'] ?? ''); ?>" class="img-responsive" alt="<?= e($ktSaasAuthContext['company_name'] ?? 'Tenant'); ?>">
                </a>
            <?php } else { ?>
                <?php get_dark_company_logo(); ?>
            <?php } ?>
        </div>

        <div class=" text-center tw-mb-5">
            <h1 class="tw-text-neutral-800 tw-text-2xl tw-font-bold tw-mb-1">
                <?= _l('admin_auth_login_heading'); ?>
            </h1>
            <p class="tw-text-neutral-600">
                <?= _l('welcome_back_sign_in'); ?>
            </p>
            <?php if (!empty($ktSaasAuthContext['is_tenant'])) { ?>
            <p class="tw-text-sm tw-text-primary tw-mt-2">
                <?= html_escape($ktSaasAuthContext['company_name'] ?: $ktSaasAuthContext['tenant_code']); ?>
                <?php if (!empty($ktSaasAuthContext['host'])) { ?>
                    (<?= html_escape($ktSaasAuthContext['host']); ?>)
                <?php } ?>
            </p>
            <?php } ?>
        </div>

        <div
            class="tw-bg-white tw-mx-2 sm:tw-mx-6 tw-py-8 tw-px-6 sm:tw-px-8 tw-shadow-sm tw-rounded-lg tw-border tw-border-solid tw-border-neutral-600/20">

            <?php $this->load->view('authentication/includes/alerts'); ?>

            <form action="<?= html_escape($authActionUrl); ?>" method="post" accept-charset="utf-8">
            <input type="hidden" name="<?= html_escape($this->security->get_csrf_token_name()); ?>" value="<?= html_escape($this->security->get_csrf_hash()); ?>">
            <?php if ($tenantHostHint !== '') { ?>
            <input type="hidden" name="tenant_host" value="<?= html_escape($tenantHostHint); ?>">
            <?php } ?>

            <?= validation_errors('<div class="alert alert-danger text-center">', '</div>'); ?>

            <?php hooks()->do_action('after_admin_login_form_start'); ?>

            <div class="form-group">
                <label for="email" class="control-label !tw-mb-3">
                    <?= _l('admin_auth_login_email'); ?>
                </label>
                <input type="email" id="email" name="email" class="form-control" autofocus="1">
            </div>

            <div class="form-group tw-mt-8">
                <span class="tw-inline-flex tw-justify-between tw-items-end tw-w-full tw-mb-3">
                    <label for="password" class="control-label !tw-m-0">
                        <?= _l('admin_auth_login_password'); ?>
                    </label>
                    <a href="<?= $forgotPasswordUrl; ?>"
                        class="text-muted">
                        <?= _l('admin_auth_login_fp'); ?>
                    </a>
                </span>

                <input type="password" id="password" name="password" class="form-control">
            </div>

            <?php if (show_recaptcha()) { ?>
            <div class="g-recaptcha tw-mb-4"
                data-sitekey="<?= get_option('recaptcha_site_key'); ?>">
            </div>
            <?php } ?>

            <div class="form-group">
                <div class="checkbox checkbox-inline">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">
                        <?= _l('admin_auth_login_remember_me'); ?></label>
                </div>
            </div>

            <div class="tw-mt-6">
                <button type="submit" class="btn btn-primary btn-block tw-font-semibold tw-py-2">
                    <?= _l('admin_auth_login_button'); ?>
                </button>
            </div>

            <?php hooks()->do_action('before_admin_login_form_close'); ?>

            </form>
        </div>
    </div>

</body>

</html>

