<?php

define('BASEPATH', true);
require_once dirname(__DIR__) . '/tenant_bootstrap/TenantResolver.php';

function kt_saas_http_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
kt_saas_http_assert(TenantResolver::normalizeHost('TENANT-A.KHACHTOT-MISA.TEST:8097') === 'tenant-a.khachtot-misa.test', 'Host normalization failed.');
kt_saas_http_assert(TenantResolver::normalizeHost('tenant-a.khachtot-misa.test.') === 'tenant-a.khachtot-misa.test', 'Trailing-dot normalization failed.');
kt_saas_http_assert(TenantResolver::normalizeHost('tenant..example.test') === null, 'Double-dot host was accepted.');
kt_saas_http_assert(TenantResolver::normalizeHost('bad host.test') === null, 'Invalid host was accepted.');
echo "KtSaasTenantHttpResolverTest passed.\n";
