<?php

defined('BASEPATH') or exit('No direct script access allowed');

class DomainVerificationService
{
    protected $CI;
    protected $defaultTimeout = 5;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function verify(array $domain)
    {
        $host = strtolower(trim((string) ($domain['domain'] ?? '')));
        if ($host === '') {
            return [
                'success' => false,
                'message' => 'Domain is empty.',
            ];
        }

        $landlordHost = strtolower(trim((string) kt_saas_get_option('kt_saas_landlord_host', parse_url(APP_BASE_URL, PHP_URL_HOST))));
        $domainType = strtolower(trim((string) ($domain['domain_type'] ?? 'subdomain')));
        $expectedTarget = $this->determineExpectedTarget($domainType, $landlordHost);
        $dnsRecords = $this->resolveDnsRecords($host);
        $expectedIps = $this->resolveIps($landlordHost);
        $dnsCheck = $this->evaluateDns($host, $domainType, $expectedTarget, $dnsRecords, $expectedIps);
        $sslCheck = $this->checkSslHandshake($host);
        $readinessStatus = $this->determineReadinessStatus($dnsCheck['status'], $sslCheck['status']);
        $verifiedAt = ($dnsCheck['status'] === 'verified' && $sslCheck['status'] === 'active') ? date('Y-m-d H:i:s') : null;

        return [
            'success'      => true,
            'domain'       => $host,
            'domain_type'  => $domainType,
            'readiness_status' => $readinessStatus,
            'expected_target'  => $expectedTarget,
            'dns_status'   => $dnsCheck['status'],
            'ssl_status'   => $sslCheck['status'],
            'verified_at'  => $verifiedAt,
            'checked_at'   => date('Y-m-d H:i:s'),
            'resolved_ips' => $dnsCheck['resolved_ips'],
            'expected_ips' => $expectedIps,
            'dns_records'  => $dnsRecords,
            'dns_message'  => $dnsCheck['message'],
            'ssl_message'  => $sslCheck['message'],
            'ssl_details'  => $sslCheck['details'],
            'message'      => trim($dnsCheck['message'] . ' ' . $sslCheck['message']),
        ];
    }

    public function verifyMany(array $domains, $limit = 100)
    {
        $results = [];
        $count = 0;

        foreach ($domains as $domain) {
            if ($count >= $limit) {
                break;
            }

            $results[] = $this->verify($domain);
            $count++;
        }

        return $results;
    }

    protected function determineExpectedTarget($domainType, $landlordHost)
    {
        $domainType = strtolower(trim((string) $domainType));
        $baseDomain = strtolower(trim((string) kt_saas_get_option('kt_saas_base_domain', '')));

        if ($domainType === 'subdomain' && $baseDomain !== '') {
            return $baseDomain;
        }

        return $landlordHost;
    }

    protected function evaluateDns($host, $domainType, $expectedTarget, array $dnsRecords, array $expectedIps)
    {
        $resolvedIps = array_values(array_unique(array_merge($dnsRecords['a'], $dnsRecords['aaaa'])));
        $cnames = $dnsRecords['cname'];
        $host = strtolower(trim((string) $host));
        $expectedTarget = strtolower(trim((string) $expectedTarget));

        if (empty($resolvedIps) && empty($cnames)) {
            return [
                'status' => 'pending',
                'message' => 'No DNS records resolved yet.',
                'resolved_ips' => $resolvedIps,
            ];
        }

        if (!empty($expectedIps) && count(array_intersect($resolvedIps, $expectedIps)) > 0) {
            return [
                'status' => 'verified',
                'message' => 'A/AAAA records resolve to the landlord target.',
                'resolved_ips' => $resolvedIps,
            ];
        }

        if ($expectedTarget !== '' && in_array($expectedTarget, $cnames, true)) {
            return [
                'status' => 'verified',
                'message' => 'CNAME points to the expected landlord target.',
                'resolved_ips' => $resolvedIps,
            ];
        }

        if ($domainType === 'subdomain' && $expectedTarget !== '' && $this->hostEndsWith($host, $expectedTarget) && !empty($resolvedIps)) {
            return [
                'status' => 'verified',
                'message' => 'Subdomain resolves inside the configured base domain.',
                'resolved_ips' => $resolvedIps,
            ];
        }

        return [
            'status' => 'mismatch',
            'message' => 'DNS resolves, but it does not match the expected landlord target.',
            'resolved_ips' => $resolvedIps,
        ];
    }

    protected function resolveDnsRecords($host)
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return ['a' => [], 'aaaa' => [], 'cname' => []];
        }

        $records = ['a' => [], 'aaaa' => [], 'cname' => []];

        if (function_exists('dns_get_record')) {
            $resolved = @dns_get_record($host, DNS_A + DNS_AAAA + DNS_CNAME);
            if (is_array($resolved)) {
                foreach ($resolved as $record) {
                    if (!empty($record['ip'])) {
                        $records['a'][] = strtolower((string) $record['ip']);
                    }
                    if (!empty($record['ipv6'])) {
                        $records['aaaa'][] = strtolower((string) $record['ipv6']);
                    }
                    if (!empty($record['target'])) {
                        $records['cname'][] = rtrim(strtolower((string) $record['target']), '.');
                    }
                }
            }
        }

        $records['a'] = array_values(array_unique($records['a']));
        $records['aaaa'] = array_values(array_unique($records['aaaa']));
        $records['cname'] = array_values(array_unique($records['cname']));

        return $records;
    }

    protected function resolveIps($host)
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return [];
        }

        $ips = [];
        $dnsRecords = $this->resolveDnsRecords($host);
        $ips = array_merge($ips, $dnsRecords['a'], $dnsRecords['aaaa']);

        if (function_exists('gethostbynamel')) {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = array_merge($ips, $resolved);
            }
        }

        $fallback = @gethostbyname($host);
        if (is_string($fallback) && $fallback !== '' && $fallback !== $host) {
            $ips[] = $fallback;
        }

        $ips = array_filter(array_unique(array_map('strtolower', $ips)));

        return array_values($ips);
    }

    protected function checkSslHandshake($host)
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'SNI_enabled'       => true,
                'peer_name'         => $host,
            ],
        ]);

        $client = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            $this->defaultTimeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            return [
                'success' => false,
                'status'  => 'pending',
                'message' => trim((string) $errstr) !== '' ? trim((string) $errstr) : 'SSL handshake failed.',
                'details' => [],
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $details = $this->extractSslCertificateDetails($params['options']['ssl']['peer_certificate'] ?? null, $host);

        return [
            'success' => true,
            'status'  => !empty($details['hostname_match']) ? 'active' : 'mismatch',
            'message' => !empty($details['hostname_match']) ? 'SSL certificate is active and matches the domain.' : 'SSL handshake succeeded, but certificate hostname does not match the domain.',
            'details' => $details,
        ];
    }

    protected function extractSslCertificateDetails($certificate, $host)
    {
        if (!$certificate) {
            return [];
        }

        $parsed = @openssl_x509_parse($certificate);
        if (!is_array($parsed)) {
            return [];
        }

        $subjectCn = $parsed['subject']['CN'] ?? '';
        $san = $parsed['extensions']['subjectAltName'] ?? '';
        $sanHosts = [];

        if (is_string($san) && $san !== '') {
            foreach (explode(',', $san) as $part) {
                $part = trim((string) $part);
                if (stripos($part, 'DNS:') === 0) {
                    $sanHosts[] = strtolower(trim(substr($part, 4)));
                }
            }
        }

        $hostnameMatch = $this->hostMatchesCertificate($host, strtolower((string) $subjectCn), $sanHosts);

        return [
            'subject_cn'      => $subjectCn,
            'issuer_cn'       => $parsed['issuer']['CN'] ?? '',
            'valid_from'      => !empty($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', (int) $parsed['validFrom_time_t']) : null,
            'valid_to'        => !empty($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', (int) $parsed['validTo_time_t']) : null,
            'san_hosts'       => $sanHosts,
            'hostname_match'  => $hostnameMatch,
        ];
    }

    protected function hostMatchesCertificate($host, $subjectCn, array $sanHosts)
    {
        $host = strtolower(trim((string) $host));
        $candidates = array_filter(array_unique(array_merge($subjectCn !== '' ? [$subjectCn] : [], $sanHosts)));

        foreach ($candidates as $candidate) {
            if ($candidate === $host) {
                return true;
            }

            if (strpos($candidate, '*.') === 0) {
                $suffix = substr($candidate, 1);
                if ($this->hostEndsWith($host, ltrim($suffix, '.')) && substr_count($host, '.') >= substr_count(ltrim($suffix, '.'), '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function determineReadinessStatus($dnsStatus, $sslStatus)
    {
        if ($dnsStatus === 'verified' && $sslStatus === 'active') {
            return 'ready';
        }

        if ($dnsStatus !== 'verified' && in_array($dnsStatus, ['pending', 'failed'], true)) {
            return 'dns_pending';
        }

        if ($dnsStatus === 'verified' && $sslStatus !== 'active') {
            return 'ssl_pending';
        }

        return 'attention';
    }

    protected function hostEndsWith($host, $suffix)
    {
        $host = strtolower(trim((string) $host));
        $suffix = strtolower(trim((string) $suffix));

        if ($host === '' || $suffix === '') {
            return false;
        }

        return $host === $suffix || substr($host, -strlen('.' . $suffix)) === '.' . $suffix;
    }
}
