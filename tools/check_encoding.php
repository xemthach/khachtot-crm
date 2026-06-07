<?php
declare(strict_types=1);

/**
 * Platform-wide UTF-8 / mojibake guardrail.
 *
 * Usage:
 *   php tools/check_encoding.php
 *
 * Exits with code 1 if any issue is found.
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "FAIL: cannot resolve project root\n");
    exit(1);
}

$includeDirs = [
    $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'views',
    $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'controllers',
    $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'helpers',
    $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'libraries',
    $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config',
    $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'vietnamese',
    $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_landing',
    $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_saas',
    $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_sepay',
    $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_matbao_invoice',
    $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_inventory',
    $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css',
    $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js',
];

$excludeParts = [
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'backup_mail_brevo' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'backup_recovery' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . '_backup' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'screenshots' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . '.codex' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'builds' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'foreign_chars.php',
    '.bridgebackup_',
    '.bak_',
    '.bak',
    '.backup',
];

$extensions = ['php', 'html', 'htm', 'js', 'css'];

// Specific mojibake byte sequences, not single characters.
// This avoids flagging valid Vietnamese like "ĐÃ" or "Ân".
$mojibakeTokens = [
    "\xC3\x83\xC2\xA0", // Ã 
    "\xC3\x83\xC2\xA1", // Ã¡
    "\xC3\x83\xC2\xA2", // Ã¢
    "\xC3\x83\xC2\xA3", // Ã£
    "\xC3\x83\xC2\xA4", // Ã¤
    "\xC3\x83\xC2\xA5", // Ã¥
    "\xC3\x83\xC2\xA6", // Ã¦
    "\xC3\x83\xC2\xA7", // Ã§
    "\xC3\x83\xC2\xA8", // Ã¨
    "\xC3\x83\xC2\xA9", // Ã©
    "\xC3\x83\xC2\xAA", // Ãª
    "\xC3\x83\xC2\xAB", // Ã«
    "\xC3\x83\xC2\xAC", // Ã¬
    "\xC3\x83\xC2\xAD", // Ã­
    "\xC3\x83\xC2\xAE", // Ã®
    "\xC3\x83\xC2\xAF", // Ã¯
    "\xC3\x83\xC2\xB1", // Ã±
    "\xC3\x83\xC2\xB2", // Ã²
    "\xC3\x83\xC2\xB3", // Ã³
    "\xC3\x83\xC2\xB4", // Ã´
    "\xC3\x83\xC2\xB5", // Ãµ
    "\xC3\x83\xC2\xB6", // Ã¶
    "\xC3\x83\xC2\xB7", // Ã·
    "\xC3\x83\xC2\xB8", // Ã¸
    "\xC3\x83\xC2\xB9", // Ã¹
    "\xC3\x83\xC2\xBA", // Ãº
    "\xC3\x83\xC2\xBB", // Ã»
    "\xC3\x83\xC2\xBC", // Ã¼
    "\xC3\x83\xC2\xBD", // Ã½
    "\xC3\x83\xC2\xBF", // Ã¿
    "\xC3\x84\xC2\x90", // Ä
    "\xC3\x84\xC2\x91", // Ä‘
    "\xC3\x85\xE2\x80\x99", // Å’
    "\xC3\x85\xE2\x80\x9C", // Å“
    "\xC3\x86\xC2\xA1", // Æ¡
    "\xC3\x86\xC2\xAF", // Æ¯
    "\xC3\xA1\xC2\xBB", // á»
    "\xC3\xA1\xC2\xBA", // áº
    "\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2", // â€™
    "\xC3\xA2\xE2\x82\xAC\xC5\x93", // â€œ
    "\xC3\xA2\xE2\x82\xAC\xC2\x9D", // â€
    "\xEF\xBB\xBF", // BOM
];

$issues = [];

$shouldScan = static function (string $path) use ($excludeParts): bool {
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    foreach ($excludeParts as $part) {
        if (strpos($normalized, $part) !== false) {
            return false;
        }
    }
    return true;
};

$scanFile = static function (string $path) use ($mojibakeTokens, &$issues): void {
    $bytes = @file_get_contents($path);
    if ($bytes === false) {
        $issues[] = [
            'path' => $path,
            'line' => 0,
            'issue' => 'Unreadable file',
            'severity' => 'FAIL',
        ];
        return;
    }

    if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
        $issues[] = [
            'path' => $path,
            'line' => 1,
            'issue' => 'UTF-8 BOM detected',
            'severity' => 'FAIL',
        ];
        $bytes = substr($bytes, 3);
    }

    if (!preg_match('//u', $bytes)) {
        $issues[] = [
            'path' => $path,
            'line' => 1,
            'issue' => 'File is not valid UTF-8',
            'severity' => 'FAIL',
        ];
        return;
    }

    $text = (string) $bytes;
    $lines = preg_split("/\r\n|\r|\n/", $text) ?: [];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $needsMetaCheck = (
        strpos($path, DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'head.php') !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_landing' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_saas' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_sepay' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'kt_matbao_invoice' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR) !== false
    );

    foreach ($lines as $lineNo => $line) {
        $currentLine = $lineNo + 1;

        foreach ($mojibakeTokens as $token) {
            if ($token !== '' && strpos($line, $token) !== false) {
                $issues[] = [
                    'path' => $path,
                    'line' => $currentLine,
                    'issue' => 'Mojibake token detected',
                    'severity' => 'FAIL',
                ];
                break;
            }
        }

        if (in_array($ext, ['php', 'html', 'htm'], true)) {
            if (preg_match('/\butf8_encode\s*\(/i', $line)) {
                $issues[] = [
                    'path' => $path,
                    'line' => $currentLine,
                    'issue' => 'utf8_encode() usage',
                    'severity' => 'FAIL',
                ];
            }

            if (preg_match('/\butf8_decode\s*\(/i', $line)) {
                $issues[] = [
                    'path' => $path,
                    'line' => $currentLine,
                    'issue' => 'utf8_decode() usage',
                    'severity' => 'FAIL',
                ];
            }

            if (preg_match('/\bhtmlentities\s*\(/i', $line) && !preg_match('/(UTF-8|utf-8|charset\s*=\s*[\'"]?UTF-8[\'"]?)/i', $line)) {
                $issues[] = [
                    'path' => $path,
                    'line' => $currentLine,
                    'issue' => 'htmlentities() without explicit UTF-8 charset',
                    'severity' => 'WARN',
                ];
            }
        }

        if ($needsMetaCheck && preg_match('/<head\b/i', $line)) {
            if (stripos($text, '403 Forbidden') !== false) {
                continue;
            }
            $headWindow = implode("\n", array_slice($lines, $lineNo, 40));
            if (stripos($headWindow, '<meta charset="UTF-8">') === false
                && stripos($headWindow, "<meta charset='UTF-8'>") === false
                && stripos($headWindow, '<meta charset=UTF-8>') === false
                && stripos($headWindow, '<meta charset="utf-8">') === false
                && stripos($headWindow, "<meta charset='utf-8'>") === false) {
                $issues[] = [
                    'path' => $path,
                    'line' => $currentLine,
                    'issue' => 'Missing <meta charset="UTF-8"> near <head>',
                    'severity' => 'WARN',
                ];
            }
        }
    }
};

foreach ($includeDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if (!$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        if (!$shouldScan($path)) {
            continue;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            continue;
        }

        $scanFile($path);
    }
}

if (!empty($issues)) {
    echo "FAIL\n";
    foreach ($issues as $issue) {
        echo sprintf(
            "%s\t%s\tline %d\t%s\n",
            $issue['severity'],
            str_replace($root . DIRECTORY_SEPARATOR, '', $issue['path']),
            $issue['line'],
            $issue['issue']
        );
    }
    exit(1);
}

echo "PASS\n";
exit(0);
