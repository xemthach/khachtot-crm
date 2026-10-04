<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Deployment policy, not a provider contract. No DB writes or credential reads. */
final class KtReleasePolicy
{
    public static function limited()
    {
        // Only an explicit server-side standard profile can lift this boundary.
        return !defined('KT_RELEASE_PROFILE') || KT_RELEASE_PROFILE !== 'standard';
    }

    public static function moduleBlocked($name)
    {
        return self::limited() && (bool) preg_match(
            '/^(?:kt_integration(?:_|$)|kt_misa(?:_|$)|misa(?:_|$)|kt_dms(?:_|$)|dms(?:_|$)|salesrep(?:_|$))/i',
            (string) $name
        );
    }

    public static function routeBlocked($route)
    {
        if (!self::limited()) {
            return false;
        }
        $path = strtolower(rawurldecode(explode('?', (string) $route, 2)[0]));
        foreach (preg_split('~[/\\\\]+~', $path) as $segment) {
            if (self::moduleBlocked($segment) || preg_match('/^(?:misa|dms|salesrep)(?:[-_]|$)/', $segment)) {
                return true;
            }
        }
        return false;
    }

    public static function deny()
    {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        // No fake callback acknowledgement; provider retry policy is NOT asserted.
        exit('{"success":false,"code":"INTEGRATION_DISABLED_BY_RELEASE_PROFILE"}');
    }

    public static function assertHubAllowed()
    {
        if (self::moduleBlocked('kt_integration_hub')) {
            throw new RuntimeException('INTEGRATION_DISABLED_BY_RELEASE_PROFILE');
        }
    }

    public static function denyTenant()
    {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        exit('{"success":false,"code":"TENANT_RUNTIME_UNAVAILABLE"}');
    }
}
