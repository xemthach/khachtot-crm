<?php
declare(strict_types=1);
$report = file_get_contents(dirname(__DIR__) . '/docs/reports/MISA_ACT_AMIS_DOCUMENT_CONFLICTS.md');
foreach (['base URL','token expiry','Reconciliation','Limits','Required confirmation'] as $needle) if (stripos($report, $needle) === false) throw new RuntimeException('Conflict topic missing: ' . $needle);
echo "MisaOfficialCrawlerConflictTest passed.\n";
