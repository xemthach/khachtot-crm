<?php
declare(strict_types=1);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$root = dirname(__DIR__, 2);
$script = $root . '/scripts/install-live.sh';
$lock = sys_get_temp_dir() . '/khachtot-live-installer.completed';
$token = (string) (getenv('KT_INSTALLER_TOKEN') ?: '');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
$auth = !empty($_SESSION['auth']);

function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function stop_now(string $message, int $code = 400): never { http_response_code($code); exit('<h1>Installer stopped</h1><p>' . esc($message) . '</p>'); }
function shell_value(string $v): string { return "'" . str_replace("'", "'\\''", $v) . "'"; }
function write_env(array $values): string {
    $path = tempnam(sys_get_temp_dir(), 'khachtot-install-');
    chmod($path, 0600);
    $body = '';
    foreach ($values as $key => $value) $body .= $key . '=' . shell_value($value) . PHP_EOL;
    file_put_contents($path, $body, LOCK_EX);
    return $path;
}

if ($token === '') stop_now('KT_INSTALLER_TOKEN is not configured.', 503);
if (function_exists('posix_geteuid') && posix_geteuid() !== 0) stop_now('Start the web installer as root.', 403);
if (is_file($lock)) stop_now('Installer is locked after a completed installation.', 410);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string) ($_POST['token'] ?? ''))) stop_now('Invalid token.', 403);
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) stop_now('Invalid CSRF token.', 403);
    if (($_POST['action'] ?? '') === 'login') {
        $_SESSION['auth'] = true;
        $auth = true;
    } elseif (!$auth) {
        stop_now('Authentication required.', 403);
    }
}

$fields = ['APP_DOMAIN','APP_SUBDOMAINS','PROJECT_PATH','DEPLOY_USER','DEPLOY_GROUP','SYNC_COMMIT','REPO_URL','DB_HOST','DB_NAME','DB_USER','DB_PASSWORD','APP_ENC_KEY','DB_ADMIN_USER','DB_ADMIN_PASSWORD','PHP_FPM_SOCKET'];
$values = $_SESSION['values'] ?? [];
$output = '';
$message = '';

if ($auth && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['plan','apply'], true)) {
    foreach ($fields as $field) {
        $value = trim((string) ($_POST[$field] ?? ''));
        if (str_contains($value, "\n") || str_contains($value, "\r")) stop_now('Newlines are not allowed.');
        $values[$field] = $value;
    }
    $values['CREATE_DATABASE'] = isset($_POST['CREATE_DATABASE']) ? 'true' : 'false';
    foreach (['APP_DOMAIN','PROJECT_PATH','SYNC_COMMIT','DB_PASSWORD','APP_ENC_KEY'] as $required) {
        if ($values[$required] === '') stop_now($required . ' is required.');
    }
    if (!preg_match('/^[A-Za-z0-9.-]+$/', $values['APP_DOMAIN'])) stop_now('APP_DOMAIN is invalid.');
    if (!preg_match('/^[0-9a-f]{7,64}$/i', $values['SYNC_COMMIT'])) stop_now('SYNC_COMMIT must be a commit hash.');
    if (strlen($values['APP_ENC_KEY']) < 32) stop_now('APP_ENC_KEY must be at least 32 characters.');
    if (is_dir($values['PROJECT_PATH'])) stop_now('PROJECT_PATH already exists.');
    $_SESSION['values'] = $values;
    $env = write_env($values);
    $mode = $_POST['action'];
    $command = 'bash ' . shell_value($script) . ' --config ' . shell_value($env) . ' --' . $mode . ' 2>&1';
    $output = (string) shell_exec($command);
    unlink($env);
    $message = $mode === 'plan' ? 'Plan completed. Review output before Apply.' : 'Installer finished. Verify the server before traffic.';
    if ($mode === 'apply') file_put_contents($lock, date(DATE_ATOM) . PHP_EOL, LOCK_EX);
}
?>
<!doctype html>
<meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Khach Tot Live Installer</title>
<style>body{font:16px system-ui;background:#f3f4f6;margin:0}.box{max-width:900px;margin:24px auto;background:white;padding:24px;border-radius:10px}label{display:block;margin:10px 0;font-weight:600}input{box-sizing:border-box;width:100%;padding:8px;margin-top:4px}button{padding:10px 16px;margin:12px 8px 0 0;color:white;background:#155eef;border:0;border-radius:5px}.apply{background:#b42318}.warn{padding:12px;background:#fff4d6}pre{background:#111827;color:#d1fae5;padding:14px;white-space:pre-wrap}</style>
<main class='box'><h1>Khach Tot Live Installer</h1>
<p class='warn'>Run only through an SSH tunnel or private protected host. Never expose this page publicly.</p>
<?php if ($message !== ''): ?><p><?= esc($message) ?></p><?php endif; ?>
<?php if (!$auth): ?><form method='post'><input type='hidden' name='csrf' value='<?= esc($csrf) ?>'><label>Installer token<input type='password' name='token' required></label><button name='action' value='login'>Unlock</button></form>
<?php else: ?><form method='post'><input type='hidden' name='csrf' value='<?= esc($csrf) ?>'><input type='hidden' name='token' value='<?= esc($token) ?>'>
<?php foreach ($fields as $field): ?><label><?= esc($field) ?><input name='<?= esc($field) ?>' type='<?= str_contains($field, 'PASSWORD') || $field === 'APP_ENC_KEY' ? 'password' : 'text' ?>' value='<?= esc((string) ($values[$field] ?? '')) ?>'></label><?php endforeach; ?>
<label><input type='checkbox' name='CREATE_DATABASE' value='1'> Create database and user</label><button name='action' value='plan'>Run plan</button><button class='apply' name='action' value='apply' onclick='return confirm("Apply installation now?")'>Apply installation</button></form>
<?php endif; ?>
<?php if ($output !== ''): ?><h2>Output</h2><pre><?= esc($output) ?></pre><?php endif; ?></main>
