<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

function read_file_safe(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

function has(string $content, string $needle): bool
{
    return strpos($content, $needle) !== false;
}

function has_regex(string $content, string $pattern): bool
{
    return preg_match($pattern, $content) === 1;
}

function check(bool $ok, string $name, string $detail = ''): array
{
    return [
        'name' => $name,
        'ok' => $ok,
        'detail' => $detail,
    ];
}

$routes = read_file_safe($root . '/application/config/routes.php');
$landingController = read_file_safe($root . '/modules/kt_landing/controllers/Kt_landing.php');
$signupView = read_file_safe($root . '/modules/kt_landing/views/public/signup.php');
$sepayProcessor = read_file_safe($root . '/modules/kt_sepay/libraries/Kt_sepay_processor.php');
$saasModel = read_file_safe($root . '/modules/kt_saas/models/Kt_saas_model.php');

$results = [];
$results[] = check(
    has_regex($routes, "/\\\$route\\['default_controller'\\]\\s*=\\s*'kt_landing';/"),
    'Route: default_controller -> kt_landing'
);
$results[] = check(has($routes, "\$route['pricing'] = 'kt_landing/pricing';"), 'Route: /pricing');
$results[] = check(has($routes, "\$route['signup'] = 'kt_landing/signup';"), 'Route: /signup');
$results[] = check(has($routes, "\$route['signup/status'] = 'kt_landing/signup_status';"), 'Route: /signup/status');

$results[] = check(has($landingController, "if (\$this->isTenantRuntime())"), 'Tenant runtime guard present');
$results[] = check(has($landingController, "redirect(site_url('clients'));"), 'Tenant runtime redirect -> clients');

$results[] = check(has($signupView, '$this->security->get_csrf_token_name()'), 'Signup form has CSRF token');
$results[] = check(has($signupView, 'name="signup_ts"'), 'Signup form has timestamp field');
$results[] = check(has($signupView, 'name="website"'), 'Signup form has honeypot field');

$results[] = check(has($landingController, 'canSubmitSignupNow'), 'Backend rate-limit function exists');
$results[] = check(has($landingController, 'landing.signup_blocked_rate_limit'), 'Rate-limit event log exists');

$results[] = check(has($sepayProcessor, 'queueProvisioningAfterPublicSignupPayment'), 'SePay processor has post-payment provisioning hook');
$results[] = check(has($sepayProcessor, "create_provision_job(\$tenantId, 'provision_tenant'"), 'SePay hook queues provision job');
$results[] = check(has($sepayProcessor, "if ((string) (\$tenant['provisioning_status'] ?? '') === 'done')"), 'SePay hook idempotency guard exists');

$results[] = check(
    has($saasModel, "->where_in('status', ['queued', 'running'])") && has($saasModel, 'create_provision_job'),
    'Model create_provision_job dedupe queued/running'
);

$pass = 0;
foreach ($results as $row) {
    if ($row['ok']) {
        $pass++;
    }
}
$total = count($results);

$output = [
    'success' => $pass === $total,
    'timestamp' => date('c'),
    'pass' => $pass,
    'total' => $total,
    'checks' => $results,
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
