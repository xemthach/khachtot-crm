<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaPayloadValidator
{
    private $required = [
        'customer' => ['code', 'name'],
        'product' => ['code', 'name', 'unit'],
        'product_category' => ['code', 'name'],
        'unit' => ['code', 'name'],
        'warehouse' => ['code', 'name'],
    ];

    public function validate($objectType, array $payload)
    {
        $errors = [];
        foreach ($this->required[(string) $objectType] ?? [] as $field) {
            if (!isset($payload[$field]) || trim((string) $payload[$field]) === '') $errors[] = ['field' => $field, 'code' => 'required', 'message' => $field . ' is required.'];
            if (isset($payload[$field]) && mb_strlen(trim((string) $payload[$field])) > 191) $errors[] = ['field' => $field, 'code' => 'max_length', 'message' => $field . ' exceeds 191 characters.'];
        }
        return ['valid' => !$errors, 'errors' => $errors];
    }

    public function requiredFields($objectType) { return $this->required[(string) $objectType] ?? []; }
}
