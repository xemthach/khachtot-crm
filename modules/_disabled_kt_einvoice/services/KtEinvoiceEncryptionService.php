<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Encryption Service
 * Mã hóa/giải mã AES-256-CBC cho API credentials
 */
class KtEinvoiceEncryptionService
{
    /** @var string */
    private $key;

    /** @var string */
    private $cipher = 'AES-256-CBC';

    public function __construct()
    {
        $this->key = $this->resolveKey();
    }

    /**
     * Mã hóa chuỗi plaintext
     * @return array{ciphertext: string, iv: string}
     */
    public function encrypt(string $plainText): array
    {
        if (empty($plainText)) {
            return ['ciphertext' => '', 'iv' => ''];
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv       = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $plainText,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('[kt_einvoice] Encryption failed.');
        }

        return [
            'ciphertext' => base64_encode($encrypted),
            'iv'         => base64_encode($iv),
        ];
    }

    /**
     * Giải mã ciphertext
     */
    public function decrypt(string $ciphertext, string $iv): string
    {
        if (empty($ciphertext) || empty($iv)) {
            return '';
        }

        $decrypted = openssl_decrypt(
            base64_decode($ciphertext),
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            base64_decode($iv)
        );

        if ($decrypted === false) {
            log_message('error', '[kt_einvoice] Decryption failed — ciphertext may be corrupted or key changed.');
            return '';
        }

        return $decrypted;
    }

    /**
     * Kiểm tra xem value có vẻ là encrypted không
     */
    public function isEncrypted(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }
        // Heuristic: base64 string có độ dài > 20
        return preg_match('/^[A-Za-z0-9+\/=]{20,}$/', $value) === 1;
    }

    /**
     * Lấy encryption key
     * Ưu tiên: env var → Perfex config encryption_key
     */
    private function resolveKey(): string
    {
        // Từ environment variable (production best practice)
        $envKey = getenv('KT_EINVOICE_ENCRYPT_KEY');
        if (!empty($envKey)) {
            return substr(hash('sha256', $envKey, true), 0, 32);
        }

        // Từ Perfex config
        $CI = &get_instance();
        $perfexKey = $CI->config->item('encryption_key');
        if (!empty($perfexKey)) {
            return substr(hash('sha256', $perfexKey . '_kt_einvoice', true), 0, 32);
        }

        throw new RuntimeException('[kt_einvoice] No encryption key configured. Set encryption_key in Perfex config or KT_EINVOICE_ENCRYPT_KEY env var.');
    }
}
