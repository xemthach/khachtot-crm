<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaErrorClassifier
{
    public function classify($code, $httpStatus = 0)
    {
        $code = strtolower(trim((string) $code));
        if ((int) $httpStatus >= 500 || in_array($code, ['timeout', 'network', 'temporarily_unavailable'], true)) return 'retryable';
        if (in_array($code, ['tokenexpired', 'invalid_token', 'auth_failed'], true)) return 'auth';
        if ($code === '' || in_array($code, ['invalidparam', 'invalid_app', 'invalid_access_code', 'invalid_company'], true)) return 'permanent';
        return 'permanent';
    }
}

