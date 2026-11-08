<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__DIR__) . '/libraries/MisaWorkerEngine.php';
require_once dirname(__DIR__) . '/libraries/MisaWorkerTenantContext.php';

class Kt_misa_worker extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) throw new RuntimeException('MISA worker is CLI-only.');
        $this->load->helper('kt_saas/kt_saas');
    }

    public function work()
    {
        $this->runCommand('dispatch');
    }

    public function work_tenant()
    {
        $this->runCommand('dispatch', true);
    }

    public function reconcile()
    {
        $this->runCommand('reconcile');
    }

    /** Explicit CLI cron entrypoint; web requests cannot invoke tenant workers. */
    public function cron()
    {
        $this->runCommand('dispatch');
    }

    public function refresh_token()
    {
        $tenantId = (int) $this->option('tenant', 0);
        $connectionId = (int) $this->option('connection', 0);
        if ($tenantId <= 0 || $connectionId <= 0) throw new RuntimeException('Trusted --tenant and --connection are required.');
        $this->withTenant($tenantId, function (MisaScope $scope, MisaWorkerEngine $engine) use ($connectionId) {
            $this->json($engine->refreshConnection($scope, $connectionId, $this->workerId()));
        });
    }

    public function health()
    {
        $tenantId = (int) $this->option('tenant', 0);
        if ($tenantId > 0) {
            $this->withTenant($tenantId, function (MisaScope $scope, MisaWorkerEngine $engine) { $this->json($engine->health($scope)); });
            return;
        }
        $rows = [];
        foreach ($this->tenants() as $tenant) {
            $this->withTenant((int) $tenant['id'], function (MisaScope $scope, MisaWorkerEngine $engine) use (&$rows, $tenant) { $rows[] = array_merge(['tenant_code' => $tenant['tenant_code'] ?? null], $engine->health($scope)); });
        }
        $this->json(['success' => true, 'tenants' => $rows, 'secrets' => 'redacted']);
    }

    public function lease()
    {
        $tenantId = (int) $this->option('tenant', 0);
        $requestId = (int) $this->option('request', 0);
        $action = (string) $this->option('action', 'claim');
        $thisId = $this->workerId();
        if ($tenantId <= 0 || $requestId <= 0 || !in_array($action, ['claim', 'heartbeat', 'release'], true)) throw new RuntimeException('Trusted lease options are required.');
        $this->withTenant($tenantId, function (MisaScope $scope, MisaWorkerEngine $engine) use ($requestId, $action, $thisId) {
            if ($action === 'claim') $this->json($engine->claimForTest($scope, $requestId, $thisId, 'dispatch', 60));
            else {
                $token = (string) $this->option('lease-token', '');
                $this->json(['success' => $action === 'heartbeat' ? $engine->heartbeatForTest($scope, $requestId, $thisId, $token, 60) : $engine->releaseForTest($scope, $requestId, $thisId, $token)]);
            }
        });
    }

    private function runCommand($kind, $singleTenant = false)
    {
        $limit = max(1, min(100, (int) $this->option('limit', 25)));
        $tenantId = (int) $this->option('tenant', 0);
        $requestId = (int) $this->option('request', 0);
        $all = [];
        $tenants = $singleTenant || $tenantId > 0 ? [$this->trustedTenant($tenantId)] : $this->tenants();
        foreach ($tenants as $tenant) {
            $this->withTenant((int) $tenant['id'], function (MisaScope $scope, MisaWorkerEngine $engine) use (&$all, $kind, $limit, $requestId, $tenant) {
                $run = $requestId > 0 ? $engine->runRequest($scope, $requestId, $this->workerId(), $kind) : $engine->runOnce($scope, $this->workerId(), $limit, $kind);
                $all[] = array_merge(['tenant_code' => $tenant['tenant_code'] ?? null], $run);
            });
        }
        $this->json(['success' => true, 'command' => $kind === 'reconcile' ? 'misa:reconcile' : 'misa:work', 'results' => $all, 'network_calls' => 0, 'misa_calls' => 0, 'accounting_writes' => 0]);
    }

    private function withTenant($tenantId, callable $callback)
    {
        $tenant = $this->trustedTenant($tenantId);
        $context = new MisaWorkerTenantContext($this);
        try {
            $switch = $context->enter($tenant);
            if (empty($switch['switched'])) throw new RuntimeException((string) ($switch['reason'] ?? 'Tenant switch failed.'));
            $scope = new MisaScope((int) $tenant['id'], (int) ($tenant['crm_company_id'] ?? $tenant['landlord_client_id'] ?? 0));
            return $callback($scope, new MisaWorkerEngine($this->db));
        } finally {
            $context->restore();
        }
    }

    private function tenants()
    {
        return $this->db->where('deleted_at IS NULL', null, false)->where_in('status', ['active', 'trial'])->get(db_prefix() . 'kt_saas_tenants')->result_array();
    }

    private function trustedTenant($tenantId)
    {
        $tenant = $this->db->where('id', (int) $tenantId)->where('deleted_at IS NULL', null, false)->get(db_prefix() . 'kt_saas_tenants')->row_array();
        if (!$tenant) throw new RuntimeException('Trusted tenant not found.');
        return $tenant;
    }

    private function workerId()
    {
        $value = trim((string) $this->option('worker-id', 'misa-worker-' . getmypid()));
        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $value) ? $value : 'misa-worker-' . getmypid();
    }

    private function option($name, $default = null)
    {
        if (isset($GLOBALS['KT_CLI_NAMED_OPTIONS']) && array_key_exists($name, $GLOBALS['KT_CLI_NAMED_OPTIONS'])) return $GLOBALS['KT_CLI_NAMED_OPTIONS'][$name];
        $prefix = '--' . $name . '=';
        foreach ((array) ($_SERVER['argv'] ?? []) as $arg) if (strpos((string) $arg, $prefix) === 0) return substr((string) $arg, strlen($prefix));
        return $default;
    }

    private function json(array $value)
    {
        echo json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
