<?php

defined('BASEPATH') or exit('No direct script access allowed');

class InitModules
{
    /**
     * Early init modules features
     */
    public function handle()
    {
        include_once(LIBSPATH.'App_modules.php');
        // Load the directory helper so the directory_map function can be used
        include_once(BASEPATH . 'helpers/directory_helper.php');

        $activeModules = $this->getActiveModuleNames();

        foreach (\App_modules::get_valid_modules() as $module) {
            if (empty($module['name']) || !in_array($module['name'], $activeModules, true)) {
                continue;
            }

            $excludeUrisPath = $module['path'] . 'config' . DIRECTORY_SEPARATOR . 'csrf_exclude_uris.php';

            if (file_exists($excludeUrisPath)) {
                $uris = include_once($excludeUrisPath);

                if (is_array($uris)) {
                    hooks()->add_filter('csrf_exclude_uris', function ($current) use ($uris) {
                        return array_merge($current, $uris);
                    });
                }
            }
        }
    }

    protected function getActiveModuleNames()
    {
        if (!defined('APP_DB_HOSTNAME')) {
            include_once APPPATH . 'config/app-config.php';
        }

        if (!defined('APP_DB_HOSTNAME') || !defined('APP_DB_USERNAME') || !defined('APP_DB_NAME')) {
            return [];
        }

        try {
            $db = @new mysqli(
                APP_DB_HOSTNAME,
                APP_DB_USERNAME,
                defined('APP_DB_PASSWORD') ? APP_DB_PASSWORD : '',
                APP_DB_NAME,
                defined('APP_DB_PORT') ? APP_DB_PORT : 3306
            );
        } catch (\Throwable $e) {
            return [];
        }

        if (!$db || $db->connect_errno) {
            return [];
        }

        $activeModules = [];
        $result = $db->query("SELECT module_name FROM tblmodules WHERE active = 1");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['module_name'])) {
                    $activeModules[] = strtolower(trim((string) $row['module_name']));
                }
            }

            $result->free();
        }

        $db->close();

        return $activeModules;
    }
}
