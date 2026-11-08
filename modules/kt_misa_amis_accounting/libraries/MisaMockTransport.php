<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/MisaTransportInterface.php';

final class MisaMockTransport implements MisaTransportInterface
{
    private $fixturesPath;
    private $calls = [];

    public function __construct($fixturesPath = null)
    {
        $this->fixturesPath = $fixturesPath ?: dirname(__DIR__) . '/fixtures';
    }

    public function connect(array $input)
    {
        $this->calls[] = ['operation' => 'connect', 'input' => kt_misa_redact($input), 'context' => ['transport' => 'mock']];
        $appId = trim((string) ($input['app_id'] ?? ''));
        if ($appId === 'mock-network-unreachable') throw new RuntimeException('Mock network unreachable.');
        $fixture = 'connect_success.json';
        $fixtures = [
            'invalid-app' => 'connect_invalid_app.json',
            'mock-malformed-outer' => 'connect_malformed_outer.json',
            'mock-malformed-data' => 'connect_malformed_data.json',
            'mock-missing-token' => 'connect_missing_token.json',
            'mock-missing-expiry' => 'connect_missing_expiry.json',
            'mock-expired-token' => 'connect_expired_token.json',
            'mock-timeout' => 'connect_timeout.json',
            'mock-http-401' => 'connect_http_401.json',
            'mock-http-403' => 'connect_http_403.json',
            'mock-http-429' => 'connect_http_429.json',
            'mock-http-500' => 'connect_http_500.json',
        ];
        if (isset($fixtures[$appId])) $fixture = $fixtures[$appId];
        if (trim((string) ($input['access_code'] ?? '')) === 'invalid-access') $fixture = 'connect_invalid_access_code.json';
        if (trim((string) ($input['org_company_code'] ?? '')) === 'invalid-company') $fixture = 'connect_invalid_company.json';
        return $this->readFixture($fixture);
    }

    public function request($operation, array $input, array $context = [])
    {
        $this->calls[] = ['operation' => (string) $operation, 'input' => kt_misa_redact($input), 'context' => kt_misa_redact($context)];
        $fixture = $operation === 'connect' ? 'connect_success.json' : 'request_accepted.json';
        return $this->readFixture($fixture);
    }

    public function calls() { return $this->calls; }

    private function readFixture($name)
    {
        $path = $this->fixturesPath . '/' . $name;
        if (!is_file($path)) throw new RuntimeException('Missing MISA mock fixture: ' . $name);
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) throw new RuntimeException('Malformed MISA mock fixture.');
        return $decoded;
    }
}
