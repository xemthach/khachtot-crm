<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaTransportInterface.php';
require_once __DIR__ . '/MisaCertification.php';

final class MisaApiClient
{
    private $transport;
    private $externalCallsEnabled;
    private $certificationLevel;

    public function __construct(MisaTransportInterface $transport, $externalCallsEnabled = false, $certificationLevel = null)
    {
        $this->transport = $transport;
        $this->externalCallsEnabled = (bool) $externalCallsEnabled;
        $this->certificationLevel = $certificationLevel;
    }

    public function connect(array $input)
    {
        $this->assertAllowed();
        return $this->transport->connect($input);
    }

    public function request($operation, array $input, array $context = [])
    {
        $this->assertAllowed();
        return $this->transport->request($operation, $input, $context);
    }

    private function assertAllowed()
    {
        if (!$this->externalCallsEnabled && (!(class_exists('MisaMockTransport') && $this->transport instanceof MisaMockTransport))) throw new RuntimeException('MISA external calls are disabled.');
        if ($this->externalCallsEnabled && !(class_exists('MisaMockTransport') && $this->transport instanceof MisaMockTransport) && $this->certificationLevel !== null && !MisaCertification::canUseExternal($this->certificationLevel)) {
            throw new RuntimeException('MISA external calls require an approved certification level.');
        }
    }
}
