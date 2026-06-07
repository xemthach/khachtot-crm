<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_landing_is_landlord_context()
{
    if (function_exists('kt_saas_is_landlord_context')) {
        return kt_saas_is_landlord_context();
    }

    if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
        return false;
    }

    return true;
}

function kt_landing_staff_can($capability)
{
    if (!is_staff_logged_in()) {
        return false;
    }

    if (is_admin()) {
        return true;
    }

    if (!function_exists('staff_can')) {
        return false;
    }

    return staff_can($capability, KT_LANDING_MODULE);
}

function kt_landing_pricing_sync_service()
{
    static $service = null;
    if ($service !== null) {
        return $service;
    }

    require_once module_dir_path(KT_LANDING_MODULE, 'services/LandingPricingSyncService.php');
    $service = new LandingPricingSyncService();
    return $service;
}

function kt_landing_media_service()
{
    static $service = null;
    if ($service !== null) {
        return $service;
    }

    require_once module_dir_path(KT_LANDING_MODULE, 'services/LandingMediaService.php');
    $service = new LandingMediaService();
    return $service;
}

function kt_landing_publish_service()
{
    static $service = null;
    if ($service !== null) {
        return $service;
    }

    require_once module_dir_path(KT_LANDING_MODULE, 'services/LandingPublishService.php');
    $service = new LandingPublishService();
    return $service;
}

function kt_landing_clone_service()
{
    static $service = null;
    if ($service !== null) {
        return $service;
    }

    require_once module_dir_path(KT_LANDING_MODULE, 'services/LandingCloneService.php');
    $service = new LandingCloneService();
    return $service;
}
