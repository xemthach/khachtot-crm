<?php

defined('BASEPATH') or exit('No direct script access allowed');

function kt_saas_run_upgrades()
{
    $CI = &get_instance();

    $planTable = db_prefix() . 'kt_saas_plans';
    if ($CI->db->table_exists($planTable)) {
        $planColumns = [
            'limit_roles'               => "ALTER TABLE `{$planTable}` ADD `limit_roles` INT NOT NULL DEFAULT 0 AFTER `limit_automations`",
            'limit_departments'         => "ALTER TABLE `{$planTable}` ADD `limit_departments` INT NOT NULL DEFAULT 0 AFTER `limit_roles`",
            'limit_governance_viewers'  => "ALTER TABLE `{$planTable}` ADD `limit_governance_viewers` INT NOT NULL DEFAULT 0 AFTER `limit_departments`",
            'limit_governance_managers' => "ALTER TABLE `{$planTable}` ADD `limit_governance_managers` INT NOT NULL DEFAULT 0 AFTER `limit_governance_viewers`",
        ];

        foreach ($planColumns as $column => $sql) {
            if (!$CI->db->field_exists($column, $planTable)) {
                $CI->db->query($sql);
            }
        }
    }

    // 1. Drop old usage table if it doesn't have module_name
    if ($CI->db->table_exists(db_prefix() . 'kt_saas_usage') && !$CI->db->field_exists('module_name', db_prefix() . 'kt_saas_usage')) {
        $CI->db->query("DROP TABLE `" . db_prefix() . "kt_saas_usage`;");
    }

    // 2. Load install.php to create the tables
    require_once __DIR__ . '/install.php';

    // 3. Sync modules catalog from physical modules
    if (!class_exists('App_modules')) {
        include_once(LIBSPATH . 'App_modules.php');
    }
    $modules = $CI->app_modules->get();
    foreach ($modules as $module) {
        $system_name = $module['system_name'];
        if ($system_name === KT_SAAS_MODULE) {
            continue; // Skip kt_saas itself
        }
        $display_name = $module['headers']['module_name'] ?? $system_name;
        $slug = str_replace('_', '-', $system_name);
        $version = $module['headers']['version'] ?? '1.0.0';
        $description = $module['headers']['description'] ?? '';

        $exists = $CI->db->where('module_name', $system_name)->get(db_prefix() . 'kt_saas_module_catalog')->row_array();
        if (!$exists) {
            $CI->db->insert(db_prefix() . 'kt_saas_module_catalog', [
                'module_name'      => $system_name,
                'display_name'     => $display_name,
                'slug'             => $slug,
                'description'      => $description,
                'version'          => $version,
                'is_global_active' => $module['activated'] ? 1 : 0,
                'synced_at'        => date('Y-m-d H:i:s'),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // 4. Sync Plan Features from tblkt_saas_plans.module_json
    $plans = $CI->db->get(db_prefix() . 'kt_saas_plans')->result_array();
    foreach ($plans as $plan) {
        $module_json = $plan['module_json'];
        if ($module_json) {
            $modulesArray = json_decode($module_json, true);
            if (is_array($modulesArray)) {
                foreach ($modulesArray as $modName) {
                    $feature_key = $modName . '.access';
                    $exists = $CI->db->where('plan_id', $plan['id'])
                        ->where('module_name', $modName)
                        ->where('feature_key', $feature_key)
                        ->get(db_prefix() . 'kt_saas_plan_features')
                        ->row_array();
                    if (!$exists) {
                        $CI->db->insert(db_prefix() . 'kt_saas_plan_features', [
                            'plan_id'     => $plan['id'],
                            'module_name' => $modName,
                            'feature_key' => $feature_key,
                            'is_enabled'  => 1,
                            'created_at'  => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }
        }
    }

    // 5. Sync Tenant Entitlements only from explicit overrides in tblkt_saas_modules
    if ($CI->db->table_exists(db_prefix() . 'kt_saas_modules')) {
        $overrides = $CI->db->get(db_prefix() . 'kt_saas_modules')->result_array();
        foreach ($overrides as $override) {
            if (($override['source'] ?? '') !== 'override') {
                continue;
            }

            $tenantId   = $override['tenant_id'];
            $moduleName = $override['module_name'];
            $status     = $override['status'];
            $enabled    = ($status === 'enabled') ? 1 : 0;
            $feature_key = $moduleName . '.access';

            $exists = $CI->db->where('tenant_id', $tenantId)
                ->where('module_name', $moduleName)
                ->where('feature_key', $feature_key)
                ->get(db_prefix() . 'kt_saas_tenant_entitlements')
                ->row_array();
            if (!$exists) {
                $CI->db->insert(db_prefix() . 'kt_saas_tenant_entitlements', [
                    'tenant_id'      => $tenantId,
                    'module_name'    => $moduleName,
                    'feature_key'    => $feature_key,
                    'is_enabled'     => $enabled,
                    'source_plan_id' => null,
                    'overridden'     => 1,
                    'created_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
