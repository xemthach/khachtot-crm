<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaCallbackVerifier
{
    public function verify($rawData, $signature, $appId)
    {
        $rawData = (string) $rawData;
        $signature = strtolower(trim((string) $signature));
        $appId = (string) $appId;
        if ($rawData === '' || $signature === '' || $appId === '') return false;
        $expected = hash_hmac('sha256', $rawData, $appId);
        return hash_equals(strtolower($expected), $signature);
    }
}

