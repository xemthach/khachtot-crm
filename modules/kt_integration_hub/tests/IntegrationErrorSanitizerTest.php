<?php

define('BASEPATH', __DIR__);
require_once dirname(__DIR__) . '/helpers/kt_integration_hub_helper.php';

$samples = [
    'curl.exe --header "Authorization: Bearer live-token-value" --header "X-Signature: abcdef123456"',
    'Request failed with Bearer raw-token and token=raw-query-token',
    'X-Signature: abcdef Secret: hidden Token: hidden',
];

foreach ($samples as $sample) {
    $safe = kt_integration_hub_sanitize_error_message($sample);
    if (preg_match('/\b(?:curl(?:\.exe)?|Authorization|Bearer|Signature|Secret|Token)\b/i', $safe)) {
        throw new RuntimeException('Sensitive transport material survived sanitization.');
    }
}

$ordinary = kt_integration_hub_sanitize_error_message('Operation timed out after 15000 milliseconds.');
if ($ordinary !== 'Operation timed out after 15000 milliseconds.') {
    throw new RuntimeException('Safe operational error was changed unexpectedly.');
}

echo "Integration error sanitizer test passed.\n";
