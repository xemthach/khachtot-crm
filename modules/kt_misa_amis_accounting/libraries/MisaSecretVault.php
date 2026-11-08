<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaSecretVault
{
    private $encrypt;
    private $decrypt;

    public function __construct($encrypt = null, $decrypt = null)
    {
        $this->encrypt = $encrypt;
        $this->decrypt = $decrypt;
    }

    public function encrypt($value)
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (is_callable($this->encrypt)) return call_user_func($this->encrypt, $value);
        if (!function_exists('get_instance')) throw new RuntimeException('Encryption runtime is unavailable.');
        $CI = &get_instance();
        $CI->load->library('encryption');
        $encrypted = $CI->encryption->encrypt($value);
        if (!is_string($encrypted) || $encrypted === '') throw new RuntimeException('Encryption failed.');
        return $encrypted;
    }

    public function decrypt($value)
    {
        if ((string) $value === '') return '';
        if (is_callable($this->decrypt)) return (string) call_user_func($this->decrypt, $value);
        if (!function_exists('get_instance')) throw new RuntimeException('Decryption runtime is unavailable.');
        $CI = &get_instance();
        $CI->load->library('encryption');
        $decrypted = $CI->encryption->decrypt($value);
        if (!is_string($decrypted) || $decrypted === '') throw new RuntimeException('Decryption failed.');
        return $decrypted;
    }
}

