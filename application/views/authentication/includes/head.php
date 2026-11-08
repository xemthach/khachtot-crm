<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Cpath d='M8 9h6v6h4V9h6v14h-6v-5h-4v5H8z' fill='white'/%3E%3C/svg%3E">
    <title>
        <?php echo e((function_exists('kt_saas_auth_context') && ($ctx = kt_saas_auth_context()) && !empty($ctx['company_name'])) ? $ctx['company_name'] : get_option('companyname')); ?> - <?php echo _l('admin_auth_login_heading'); ?>
    </title>
    <?php echo app_compile_css('admin-auth'); ?>
    <style>
    body,
    html {
        font-size: 16px;
    }

    body>* {
        font-size: 14px;
    }

    body {
        font-family: "Inter", sans-serif;
        color: #475569;
        margin: 0;
        padding: 0;
    }

    .company-logo {
        padding: 25px 10px;
        display: block;
    }

    .company-logo img {
        margin: 0 auto;
        display: block;
    }

    @media screen and (max-height: 575px),
    screen and (min-width: 992px) and (max-width:1199px) {

        #rc-imageselect,
        .g-recaptcha {
            transform: scale(0.83);
            -webkit-transform: scale(0.83);
            transform-origin: 0 0;
            -webkit-transform-origin: 0 0;
        }
    }
    </style>
    <?php if (show_recaptcha()) { ?>
    <script src='https://www.google.com/recaptcha/api.js'></script>
    <?php } ?>
    <?php if (file_exists(FCPATH . 'assets/css/custom.css')) { ?>
    <link href="<?php echo base_url('assets/css/custom.css'); ?>" rel="stylesheet" id="custom-css">
    <?php } ?>
    <?php hooks()->do_action('app_admin_authentication_head'); ?>
</head>
