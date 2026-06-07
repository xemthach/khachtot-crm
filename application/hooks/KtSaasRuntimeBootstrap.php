<?php

defined('BASEPATH') or exit('No direct script access allowed');

class KtSaasRuntimeBootstrap
{
    public function bootstrap()
    {
        if (is_cli()) {
            return;
        }

        $host = $this->currentHost();
        if ($host === '') {
            return;
        }

        $namespace = $this->hostNamespace($host);
        $this->applySessionNamespace($namespace);

        $GLOBALS['kt_saas_request_host'] = $host;
        $GLOBALS['kt_saas_session_namespace'] = $namespace;
        $GLOBALS['kt_saas_cache_namespace'] = 'host:' . $namespace;
    }

    protected function applySessionNamespace($namespace)
    {
        global $config;

        $configRef = function_exists('get_config') ? get_config() : null;
        $baseCookieName = 'sp_session';

        if (is_array($configRef) && !empty($configRef['sess_cookie_name'])) {
            $baseCookieName = $configRef['sess_cookie_name'];
        } elseif (is_array($config) && !empty($config['sess_cookie_name'])) {
            $baseCookieName = $config['sess_cookie_name'];
        }

        $baseCookieName = preg_replace('/[^a-z0-9_\-]/i', '_', (string) $baseCookieName);
        $scopedCookieName = $baseCookieName . '_' . $namespace;
        $scopedCookieName = substr($scopedCookieName, 0, 120);

        if (is_array($config)) {
            $config['sess_cookie_name'] = $scopedCookieName;
        }

        if (is_array($configRef)) {
            $configRef['sess_cookie_name'] = $scopedCookieName;
        }

        ini_set('session.name', $scopedCookieName);
    }

    protected function currentHost()
    {
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(trim((string) $host));

        if (strpos($host, ':') !== false) {
            $parts = explode(':', $host);
            $host = $parts[0];
        }

        return $host;
    }

    protected function hostNamespace($host)
    {
        $host = preg_replace('/[^a-z0-9_\-\.]/', '_', strtolower((string) $host));
        $host = str_replace('.', '_', $host);

        return $host !== '' ? $host : 'default';
    }
}
