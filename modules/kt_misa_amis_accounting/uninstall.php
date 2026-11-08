<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Accounting requests, mappings, callbacks and audit records are retained by policy.
if (function_exists('update_option')) {
    update_option('kt_misa_module_enabled', '0');
    update_option('kt_misa_external_calls_enabled', '0');
    update_option('kt_misa_callback_enabled', '0');
    update_option('kt_misa_master_data_sync_enabled', '0');
}

