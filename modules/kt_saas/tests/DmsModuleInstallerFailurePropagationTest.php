<?php defined('BASEPATH') or define('BASEPATH', __DIR__);

$runner = file_get_contents(dirname(__DIR__) . '/provisioning/ProvisioningJobRunner.php');

foreach ([
    '$CI = $this->CI;',
    "return ['success' => false",
    "if (empty(\$installResult['success']))",
    "if (empty(\$result['success']))",
] as $needle) {
    if (strpos($runner, $needle) === false) {
        throw new RuntimeException('Missing module installer failure contract: ' . $needle);
    }
}

echo "DmsModuleInstallerFailurePropagationTest passed.\n";
