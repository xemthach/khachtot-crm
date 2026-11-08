<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaPayloadValidator.php';
require_once __DIR__ . '/MisaPayloadBuilders.php';

final class MisaDryRunService
{
    private $builders;
    private $validator;

    public function __construct(MisaPayloadBuilders $builders, MisaPayloadValidator $validator)
    {
        $this->builders = $builders; $this->validator = $validator;
    }

    public function run($objectType, array $source)
    {
        $payload = $this->builders->build($objectType, $source);
        $validation = $this->validator->validate($objectType, $payload);
        return ['object_type' => (string) $objectType, 'valid' => $validation['valid'], 'errors' => $validation['errors'], 'payload' => kt_misa_redact($payload), 'payload_hash' => $this->builders->fingerprint($payload), 'external_call' => false, 'remote_mapping_created' => false];
    }
}

