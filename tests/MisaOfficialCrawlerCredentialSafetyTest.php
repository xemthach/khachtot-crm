<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$files = array_merge(glob($root . '/docs/evidence/misa/**/*') ?: [], glob($root . '/docs/reports/MISA_ACT_AMIS_*.md') ?: []);
$pattern = '/(?i)(?:x-misa-access-token|access[_-]?code|clientsecret|password)\s*["\']?\s*[:=]\s*["\']?[A-Za-z0-9+\/=._-]{16,}/';
foreach ($files as $file) if (is_file($file) && preg_match($pattern, (string) file_get_contents($file))) throw new RuntimeException('Potential credential value in ' . $file);
echo "MisaOfficialCrawlerCredentialSafetyTest passed.\n";
