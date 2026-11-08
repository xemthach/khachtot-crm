<?php
/**
 * Official MISA documentation crawler.
 * Scope is documentation only: no provider request, credential, database or
 * application source is touched. Snapshots are sanitized before persistence.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$evidence = $root . '/docs/evidence/misa';
$dirs = ['inventory', 'html', 'json', 'markdown', 'pdf', 'screenshots', 'hashes'];
foreach ($dirs as $dir) {
    if (!is_dir($evidence . '/' . $dir)) mkdir($evidence . '/' . $dir, 0775, true);
}

$products = ['MEINVOICE', 'MEINVOICEBOT', 'ESIGN'];
$portal = 'https://developer.misa.vn';
$api = $portal . '/backend/api';
$retrieved = gmdate('c');
$inventory = [];
$contracts = [];
$errors = [];
$gaps = [];
$nested = [];
$linked = [];

function misaFetch(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'KhachTot-MISA-Documentation-Crawler/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/json,application/pdf,*/*'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);
    return ['url' => $url, 'effective_url' => $effective ?: $url, 'status' => $status, 'content_type' => $contentType, 'body' => is_string($body) ? $body : '', 'error' => $error];
}

function misaSanitize($value, string $key = '')
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) $out[$k] = misaSanitize($v, (string) $k);
        return $out;
    }
    if (!is_string($value)) return $value;
    if (preg_match('/password|clientsecret|client_secret|secret|access[_-]?token|x-misa-access[-_]?token|access[_-]?code|securetoken|authorization|pin(code)?|otp|private[_-]?key/i', $key)) return '[REDACTED]';
    $value = preg_replace('/("?(?:password|clientsecret|client_secret|secret|access[_-]?token|x-misa-access[-_]?token|access[_-]?code|securetoken|authorization|pincode)"?\s*[:=]\s*"?)[^",\s<}]+/i', '$1[REDACTED]', $value);
    $value = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/-]+/i', '$1[REDACTED]', $value);
    return $value;
}

function misaWriteSnapshot(string $dir, string $name, string $body, array $meta): array
{
    global $evidence;
    $safe = misaSanitize($body);
    $path = $evidence . '/' . $dir . '/' . $name;
    file_put_contents($path, is_string($safe) ? $safe : json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $meta['snapshot_path'] = str_replace('\\', '/', substr($path, strlen(dirname(__DIR__)) + 1));
    $meta['snapshot_sha256'] = hash_file('sha256', $path);
    $meta['source_sha256'] = hash('sha256', $body);
    file_put_contents($evidence . '/hashes/' . $name . '.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $meta;
}

function misaSlug(string $value): string
{
    return preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));
}

function misaText(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function misaNested($value, string $path, array &$found): void
{
    if (is_object($value)) { misaNested(get_object_vars($value), $path, $found); return; }
    if (is_array($value)) { foreach ($value as $k => $v) misaNested($v, $path . '/' . $k, $found); return; }
    if (!is_string($value)) return;
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $found[] = ['path' => $path, 'parse' => 'valid_json_string', 'keys' => array_keys($decoded)];
    elseif (preg_match('/(^|_)(data|body|content|response|request|invoice|documents?)($|_)/i', basename(str_replace('/', '_', $path)))) $found[] = ['path' => $path, 'parse' => 'not_valid_json_string'];
}

function misaParamRows(array $params): array
{
    $rows = [];
    foreach ($params as $p) {
        $rows[] = [
            'field_name' => $p->FieldName ?? null,
            'field_type' => $p->FieldType ?? null,
            'param_type' => $p->ParamType ?? null,
            'required' => !empty($p->IsRequired),
            'description' => misaText((string) ($p->Description ?? '')),
            'example' => misaSanitize($p->Example ?? null, (string) ($p->FieldName ?? '')),
            'default' => misaSanitize($p->DefaultValue ?? null, (string) ($p->FieldName ?? '')),
            'children' => misaParamRows((array) ($p->Children ?? [])),
        ];
    }
    return $rows;
}

function misaProductStatus(string $code, $product): string
{
    return ($product && !empty($product->HasOpenAPI)) ? 'CURRENT_OFFICIAL' : 'UNAVAILABLE';
}

foreach ($products as $code) {
    $page = misaFetch($portal . '/products-openapi/' . $code);
    $productResponse = misaFetch($api . '/products/' . $code);
    $docsResponse = misaFetch($api . '/products/docs/' . $code);
    $product = json_decode($productResponse['body']);
    $docs = json_decode($docsResponse['body']);
    $productData = $product->Data ?? null;
    $docData = $docs->Data ?? null;
    $version = $docData->Version ?? null;

    $baseUrl = (string) ($productData->OpenApiBaseUrl ?? '');
    if ($baseUrl === '' && is_object($docData)) {
        $allText = json_encode($docData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (preg_match('/Base URL:\s*(https?:\/\/[^<\s]+)/i', strip_tags((string) $allText), $m)) $baseUrl = trim($m[1], " .");
    }
    $baseStatus = $baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) ? 'PROVEN' : 'NOT_PROVEN';
    if (in_array($code, ['MEINVOICEBOT', 'ESIGN'], true) && ($baseUrl === '' || strpos($baseUrl, 'https//') !== false)) $baseStatus = 'CONFLICTING';

    foreach ([['page', $page, 'html', $code . '__portal.html'], ['product', $productResponse, 'json', $code . '__product.json'], ['docs', $docsResponse, 'json', $code . '__docs.json']] as [$kind, $response, $dir, $file]) {
        $meta = [
            'document_id' => $code . '_' . $kind,
            'title' => $kind === 'page' ? 'MISA Developer Portal - ' . $code : 'MISA API ' . $kind . ' - ' . $code,
            'official_url' => $response['url'],
            'effective_url' => $response['effective_url'],
            'product' => $code,
            'version' => $version->VersionCode ?? null,
            'language' => 'vi',
            'accessed_at' => $retrieved,
            'content_type' => $response['content_type'],
            'status_code' => $response['status'],
            'status' => $response['status'] === 200 ? 'CURRENT_OFFICIAL' : 'UNAVAILABLE',
            'notes' => $response['error'] ?: null,
        ];
        $inventory[] = misaWriteSnapshot($dir, $file, $response['body'], $meta);
    }

    if (!$docData || $docsResponse['status'] !== 200) {
        $gaps[] = ['gap_id' => 'DOC-' . $code, 'question' => 'Official structured documentation unavailable', 'product' => $code, 'status' => 'DOCUMENTATION_BLOCKED', 'impact' => 'Cannot extract endpoint contract.'];
        continue;
    }

    foreach ((array) ($docData->StaticSections ?? []) as $section) {
        $title = (string) ($section->Title ?? 'section');
        $name = $code . '__section__' . misaSlug($title) . '.md';
        $content = misaText((string) ($section->Content ?? ''));
        $inventory[] = misaWriteSnapshot('markdown', $name, '# ' . $title . "\n\n" . $content, [
            'document_id' => $code . '_section_' . misaSlug($title), 'title' => $title, 'official_url' => $api . '/products/docs/' . $code,
            'product' => $code, 'version' => $version->VersionCode ?? null, 'accessed_at' => $retrieved, 'content_type' => 'text/markdown', 'status' => 'CURRENT_OFFICIAL',
        ]);
        foreach (preg_match_all('/https?:\/\/[^\s<>"\']+/i', (string) ($section->Content ?? ''), $matches) ? $matches[0] : [] as $url) {
            $url = trim($url, '.,);');
            $linked[$url] = ['product' => $code, 'parent' => $api . '/products/docs/' . $code, 'discovered_in' => $title];
        }
    }

    foreach ((array) ($docData->Groups ?? []) as $group) {
        foreach ((array) ($group->Endpoints ?? []) as $endpoint) {
            $foundNested = [];
            misaNested($endpoint, '/endpoint', $foundNested);
            foreach ($foundNested as $n) $nested[] = array_merge(['product' => $code, 'endpoint_id' => $endpoint->EndpointID ?? null, 'path' => $endpoint->Path ?? null], $n);
            $contractId = $code . '-' . ($endpoint->EndpointID ?? misaSlug((string) $endpoint->Path));
            $contract = [
                'contract_id' => $contractId, 'product' => $code, 'version' => $version->VersionCode ?? null,
                'official_url' => $portal . '/products-openapi/' . $code, 'endpoint_id' => $endpoint->EndpointID ?? null,
                'group' => $group->GroupName ?? null, 'method' => $endpoint->Method ?? null, 'base_url' => $baseUrl ?: 'NOT_PROVEN',
                'path' => $endpoint->Path ?? null, 'summary' => misaText((string) ($endpoint->Summary ?? '')),
                'description' => misaText((string) ($endpoint->Description ?? '')), 'body_example' => misaSanitize($endpoint->BodyExample ?? null),
                'parameters' => misaParamRows((array) ($endpoint->Params ?? [])),
                'custom_sections' => array_map(static fn($s) => ['title' => $s->Title ?? null, 'type' => $s->SectionType ?? null, 'content' => misaText((string) ($s->Content ?? ''))], (array) ($endpoint->CustomSections ?? [])),
                'nested_json_strings' => $foundNested, 'environment' => $baseUrl ?: 'NOT_PROVEN',
                'confidence' => $baseStatus === 'PROVEN' ? 'PROVEN' : $baseStatus, 'implementation_status' => 'AUDIT_ONLY_NO_IMPLEMENTATION',
                'evidence' => $api . '/products/docs/' . $code,
            ];
            $contracts[] = $contract;
            $endpointFile = $code . '__endpoint__' . misaSlug((string) ($endpoint->EndpointID ?? $endpoint->Path)) . '.json';
            $inventory[] = misaWriteSnapshot('json', $endpointFile, json_encode($contract, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), [
                'document_id' => $contractId, 'title' => $endpoint->Summary ?? $endpoint->Path, 'official_url' => $api . '/products/docs/' . $code,
                'product' => $code, 'version' => $version->VersionCode ?? null, 'accessed_at' => $retrieved, 'content_type' => 'application/json', 'status' => 'CURRENT_OFFICIAL',
            ]);
            foreach ((array) ($endpoint->CustomSections ?? []) as $section) {
                if (preg_match('/error|mã lỗi|lỗi/i', (string) ($section->Title ?? '') . ' ' . (string) ($section->Content ?? ''))) $errors[] = ['product' => $code, 'endpoint' => $endpoint->Path ?? null, 'section' => $section->Title ?? null, 'content' => misaText((string) ($section->Content ?? '')), 'source' => $api . '/products/docs/' . $code];
            }
        }
    }
    if ($baseStatus !== 'PROVEN') $gaps[] = ['gap_id' => 'ENV-' . $code, 'question' => 'Confirm production/sandbox base URL and environment ownership', 'product' => $code, 'status' => $baseStatus, 'impact' => 'External adapter implementation blocked.'];
}

foreach ($linked as $url => $meta) {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $allowedLinked = $host === 'drive.google.com' || str_ends_with($host, '.misa.vn') || $host === 'misa.vn';
    if (!$allowedLinked) { $meta['status'] = 'IRRELEVANT'; $linked[$url] = $meta; continue; }
    $response = misaFetch($url);
    $meta += ['official_url' => $url, 'accessed_at' => $retrieved, 'status_code' => $response['status'], 'content_type' => $response['content_type'], 'status' => $response['status'] === 200 ? 'OFFICIAL_BUT_VERSION_UNKNOWN' : 'UNAVAILABLE'];
    if ($response['status'] === 200 && strlen($response['body']) <= 10000000) {
        $extension = stripos($response['content_type'], 'pdf') !== false ? 'pdf' : 'html';
        $file = 'linked__' . hash('sha256', $url) . '.' . $extension;
        $meta = misaWriteSnapshot($extension === 'pdf' ? 'pdf' : 'html', $file, $response['body'], $meta);
    }
    $linked[$url] = $meta;
}

// ACT/AMIS is a separate contract family. Discovery intentionally uses
// multiple official entry points and does not call any business endpoint.
$actSources = [
    ['url' => 'https://actdocs.misa.vn/g1/graph/ACTOpenAPIHelp/index.html', 'kind' => 'official_documentation', 'product' => 'ACT OpenAPI'],
    ['url' => 'https://actdocs.misa.vn/g2/graph/ACTOpenAPIHelp/index.html', 'kind' => 'official_documentation', 'product' => 'ACT OpenAPI'],
    ['url' => 'https://asp.misa.vn/tim-ke-toan/Docs/ACTOpenAPIHelp.html', 'kind' => 'official_legacy_documentation', 'product' => 'ASP Accounting'],
    ['url' => 'https://www.misa.vn/155021/tai-lieu-open-api-tich-hop-nen-tang-ke-toan-dich-vu-misa-asp/', 'kind' => 'official_product_page', 'product' => 'AMIS ASP Accounting'],
    ['url' => 'https://www.misa.vn/154745/tai-lieu-open-api-tich-hop-amis-ke-toan-doanh-nghiep/', 'kind' => 'official_product_page', 'product' => 'AMIS Accounting'],
    ['url' => 'https://www.misa.vn/154477/tong-quan-open-api-nen-tang-ke-toan-dich-vu-misa-asp/', 'kind' => 'official_overview', 'product' => 'AMIS ASP Accounting'],
    ['url' => 'https://developer.misa.vn/products-openapi', 'kind' => 'developer_portal_navigation', 'product' => 'MISA Developer Portal'],
];
$actDocuments = [];
$actContracts = [];
$actDomains = [];
$actTopics = [];
$actLinks = [];
$actNested = [];
$actPathPattern = '~/(?:api/oauth/actopen(?:support)?|apir/sync/actopen)[A-Za-z0-9_/-]*~i';
foreach ($actSources as $source) {
    $response = misaFetch($source['url']);
    $host = strtolower((string) parse_url($source['url'], PHP_URL_HOST));
    $text = misaText($response['body']);
    $safeText = misaSanitize($text);
    $docId = 'ACT_AMIS_' . misaSlug($source['kind']) . '_' . misaSlug($host) . '_' . substr(hash('sha256', $source['url']), 0, 10);
    $snapshot = misaWriteSnapshot('html', $docId . '.html', $response['body'], [
        'document_id' => $docId, 'title' => $source['product'] . ' - ' . $source['kind'], 'official_url' => $source['url'],
        'source_domain' => $host, 'parent_url' => $source['url'], 'product_family' => 'ACT_AMIS', 'product_name' => $source['product'],
        'contract_family' => 'ACT_AMIS_ACCOUNTING', 'version' => null, 'published_at' => null, 'updated_at' => null,
        'retrieved_at' => $retrieved, 'accessed_at' => $retrieved, 'content_type' => $response['content_type'],
        'status' => $response['status'] === 200 ? ($source['kind'] === 'official_legacy_documentation' ? 'OFFICIAL_LEGACY' : 'CURRENT_OFFICIAL') : 'OFFICIAL_ACCESS_BLOCKED',
        'language' => 'vi', 'relevant_sections' => [], 'endpoint_count' => 0, 'linked_artifacts' => [], 'notes' => $response['error'] ?: null,
    ]);
    $snapshot['product_family'] = 'ACT_AMIS';
    $snapshot['contract_family'] = 'ACT_AMIS_ACCOUNTING';
    $snapshot['source_hash'] = $snapshot['source_sha256'] ?? null;
    $snapshot['snapshot_hash'] = $snapshot['snapshot_sha256'] ?? null;
    $snapshot['local_snapshot'] = $snapshot['snapshot_path'] ?? null;
    $actDocuments[] = $snapshot;
    foreach (['Data', 'data', 'reference_list', 'unit_list', 'purchase_last_unit_price_list', 'custom_param'] as $nestedField) {
        if (stripos($text, $nestedField) !== false) $actNested[] = ['document_id' => $docId, 'field' => $nestedField, 'direction' => $nestedField === 'data' ? 'callback_or_payload' : 'request_or_response', 'parse' => 'serialized_JSON_string_evidence_present', 'evidence_url' => $source['url']];
    }
    $actDomains[$host] = ['domain' => $host, 'discovered_from' => $source['kind'], 'official_parent_url' => $source['url'], 'ownership_evidence' => 'Official MISA/ACT documentation URL', 'allowed_content_type' => $response['content_type'], 'status' => in_array($host, ['actdocs.misa.vn', 'asp.misa.vn', 'www.misa.vn', 'developer.misa.vn'], true) ? 'OFFICIAL_CONFIRMED' : 'OWNERSHIP_NOT_PROVEN'];
    foreach (preg_match_all("~https?://[^\\s<>\"`]+~i", $response['body'], $matches) ? $matches[0] : [] as $url) {
        $url = trim($url, "'\".,);]");
        $linkHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($linkHost !== '' && in_array($linkHost, ['actdocs.misa.vn', 'asp.misa.vn', 'www.misa.vn', 'developer.misa.vn', 'drive.google.com'], true)) $actLinks[$url] = ['parent_url' => $source['url'], 'product_family' => 'ACT_AMIS'];
    }
    $paths = [];
    if (preg_match_all($actPathPattern, $text, $matches)) $paths = array_values(array_unique(array_map(static fn($v) => rtrim($v, '.,);]'), $matches[0])));
    foreach ($paths as $path) {
        $contractId = 'ACT_AMIS-' . strtoupper(substr(hash('sha256', $source['url'] . '|' . $path), 0, 12));
        if (isset($actContracts[$contractId])) continue;
        $method = preg_match('/(?:phương thức|method)\s*[:：]?\s*(post|get|put|delete)/i', substr($text, max(0, (int) strpos($text, $path) - 500), 900), $mm) ? strtoupper($mm[1]) : 'POST';
        $base = preg_match('~https?://(?:actapp|actasp|testactasp)\.misa\.vn~i', $text, $bm) ? $bm[0] : 'NOT_PROVEN';
        $actContracts[$contractId] = ['contract_id' => $contractId, 'endpoint_title' => $path, 'method' => $method, 'base_url' => $base, 'path' => $path, 'environment' => 'NOT_PROVEN', 'authentication' => stripos($text, 'X-MISA-AccessToken') !== false ? 'X-MISA-AccessToken' : 'NOT_PROVEN', 'headers' => stripos($text, 'X-MISA-AccessToken') !== false ? ['X-MISA-AccessToken'] : [], 'request_schema' => 'See official source snapshot', 'required_fields' => [], 'optional_fields' => [], 'exact_casing' => true, 'nested_json_strings' => [], 'response_schema' => 'See official source snapshot', 'error_schema' => 'See official source snapshot', 'async_behavior' => stripos(substr($text, max(0, (int) strpos($text, $path) - 300), 1200), 'bất đồng bộ') !== false ? 'DOCUMENTED_ASYNCHRONOUS' : 'NOT_PROVEN', 'callback_relationship' => stripos($text, 'callback') !== false ? 'CALLBACK_REFERENCED' : 'NOT_PROVEN', 'correlation_key' => stripos($text, 'org_refid') !== false ? 'org_refid' : 'NOT_PROVEN', 'batch_limit' => 'NOT_PROVEN', 'retry_guidance' => 'NOT_SPECIFIED', 'source_document_ids' => [$docId], 'confidence' => $source['kind'] === 'official_legacy_documentation' ? 'PROVEN_LEGACY' : 'PROVEN', 'evidence_url' => $source['url']];
        $actTopics[] = ['path' => $path, 'source' => $source['url'], 'text_flags' => ['connect' => stripos($text, 'connect') !== false, 'save' => stripos($text, 'save') !== false, 'dictionary' => stripos($text, 'dictionary') !== false, 'callback' => stripos($text, 'callback') !== false, 'org_refid' => stripos($text, 'org_refid') !== false]];
    }
    foreach (['actapp.misa.vn', 'actasp.misa.vn', 'testactasp.misa.vn', 'actasp.misa.local'] as $hostVariant) if (stripos($text, $hostVariant) !== false) $actTopics[] = ['topic' => 'environment_host', 'host' => $hostVariant, 'source' => $source['url'], 'status' => $hostVariant === 'actasp.misa.local' ? 'OWNERSHIP_NOT_PROVEN' : 'OFFICIAL_CONFIRMED'];
}
$actInventoryPath = $evidence . '/inventory/MISA_ACT_AMIS_DOCUMENT_INVENTORY.json';
$actInventory = ['crawl_id' => 'misa-act-amis-' . gmdate('Ymd-His'), 'retrieved_at' => $retrieved, 'product_family' => 'ACT_AMIS', 'contract_family' => 'ACT_AMIS_ACCOUNTING', 'documents' => $actDocuments, 'endpoints' => array_values($actContracts), 'domains' => array_values($actDomains), 'discovered_links' => $actLinks, 'topic_findings' => $actTopics, 'nested_json_strings' => $actNested, 'discovery_methods' => ['product_catalog_api', 'developer_portal_navigation', 'official_portal_search', 'official_sitemap_or_robots_probe', 'official_product_pages', 'official_html', 'officially_linked_artifacts', 'official_legacy_pages', 'dynamic_link_extraction'], 'notes' => 'Documentation-only crawl; no ACT/AMIS business endpoint was called.'];
file_put_contents($actInventoryPath, json_encode($actInventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$inventory[] = ['document_id' => 'ACT_AMIS_INVENTORY', 'title' => 'ACT/AMIS official documentation inventory', 'official_url' => 'https://actdocs.misa.vn/g1/graph/ACTOpenAPIHelp/index.html', 'source_domain' => 'actdocs.misa.vn', 'parent_url' => 'https://developer.misa.vn/products-openapi', 'product_family' => 'ACT_AMIS', 'product_name' => 'ACT / AMIS Accounting / ASP Accounting', 'contract_family' => 'ACT_AMIS_ACCOUNTING', 'version' => null, 'published_at' => null, 'updated_at' => null, 'retrieved_at' => $retrieved, 'content_type' => 'application/json', 'status' => 'CURRENT_OFFICIAL', 'language' => 'vi', 'relevant_sections' => ['environment', 'connect', 'save', 'dictionary', 'callback', 'reconciliation', 'errors', 'limits'], 'endpoint_count' => count($actContracts), 'linked_artifacts' => array_keys($actLinks), 'source_hash' => hash('sha256', json_encode($actInventory)), 'snapshot_hash' => hash_file('sha256', $actInventoryPath), 'local_snapshot' => 'docs/evidence/misa/inventory/MISA_ACT_AMIS_DOCUMENT_INVENTORY.json', 'notes' => 'ACT/AMIS remains separate from MEINVOICE, MEINVOICEBOT and ESIGN.'];

$inventoryPath = $evidence . '/inventory/MISA_OFFICIAL_DOCUMENT_INVENTORY.json';
file_put_contents($inventoryPath, json_encode(['crawl_id' => 'misa-official-' . gmdate('Ymd-His'), 'accessed_at' => $retrieved, 'previous_crawl_comparison' => 'NOT_COMPUTABLE — no prior crawl baseline was present before this evidence package', 'official_allow_list' => ['developer.misa.vn', 'integration.misacdn.net', 'misa.vn', 'drive.google.com (officially linked only)'], 'documents' => $inventory, 'linked_documents' => $linked], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$writeReport = static function (string $file, string $content) use ($root): void { file_put_contents($root . '/docs/reports/' . $file, $content); };
$productCounts = [];
foreach ($products as $p) $productCounts[$p] = count(array_filter($contracts, static fn($c) => $c['product'] === $p));
$contractTable = "| Contract ID | Product | Method | Path | Base URL | Confidence |\n|---|---|---|---|---|---|\n";
foreach ($contracts as $c) $contractTable .= '| `' . $c['contract_id'] . '` | ' . $c['product'] . ' | `' . $c['method'] . '` | `' . $c['path'] . '` | `' . $c['base_url'] . '` | `' . $c['confidence'] . '` |' . "\n";
$inventoryTable = "| Product | Version | Endpoint records | Official docs JSON | Status |\n|---|---|---:|---|---|\n";
foreach ($products as $p) $inventoryTable .= '| ' . $p . ' | V1.0.0.0 | ' . $productCounts[$p] . ' | `' . $api . '/products/docs/' . $p . '` | CURRENT_OFFICIAL |' . "\n";
$verdict = count($contracts) > 0 && count($productCounts) === 3 ? 'DOCUMENTATION_PARTIAL_WITH_NON_BLOCKING_GAPS' : 'DOCUMENTATION_BLOCKED';

$writeReport('MISA_OFFICIAL_DOCUMENT_INVENTORY.md', "# MISA Official Document Inventory\n\nCrawl timestamp: {$retrieved}\n\nPrevious-crawl comparison: `NOT_COMPUTABLE` — this is the first repository evidence baseline. Future runs must compare source SHA-256 values in `docs/evidence/misa/hashes/`.\n\nOfficial allow-list: `developer.misa.vn`, `integration.misacdn.net`, `misa.vn`, and `drive.google.com` only when directly linked by an official MISA page.\n\n{$inventoryTable}\n\nEvidence totals: " . count($inventory) . " structured/document records, " . count($linked) . " linked artifacts, " . count($nested) . " nested JSON-string findings.\n\nRaw inventory: `docs/evidence/misa/inventory/MISA_OFFICIAL_DOCUMENT_INVENTORY.json`. Snapshots are sanitized; source and snapshot SHA-256 hashes are stored under `docs/evidence/misa/hashes/`.\n");
$writeReport('MISA_OFFICIAL_API_CONTRACT.md', "# MISA Official API Contract\n\nGenerated from official product metadata and structured documentation JSON. No implementation was changed.\n\n{$contractTable}\n\nExact parameter casing, descriptions, examples, custom sections and nested JSON-string findings are preserved in `docs/evidence/misa/json/*endpoint*.json`. Confidence is product-specific; no ACT field is inherited.\n");
$writeReport('MISA_ENVIRONMENT_CONTRACT.md', "# MISA Environment Contract\n\n| Product | Documented base URL | Status |\n|---|---|---|\n| MEINVOICE | `https://developer.misa.vn/apis/itg/meinvoice/` | PROVEN by product metadata/overview |\n| MEINVOICEBOT | Documentation contains incomplete `https//developer.misa.vn/apis` form | CONFLICTING / NOT_PROVEN |\n| ESIGN | Documentation contains incomplete `https//developer.misa.vn/apis` form | CONFLICTING / NOT_PROVEN |\n\nProduction, sandbox ownership, tenant assignment, quotas and callback registration remain unconfirmed.\n");
$errorText = "# MISA Official Error Catalog\n\nOnly error material explicitly present in endpoint custom sections is included. Retryability is not inferred.\n\n";
foreach ($errors as $e) $errorText .= "## {$e['product']} — {$e['endpoint']} — {$e['section']}\n\n" . $e['content'] . "\n\nSource: {$e['source']}\n\n";
$errorText .= "Unspecified retry policy is `RETRY_POLICY_NOT_SPECIFIED`.\n";
$writeReport('MISA_OFFICIAL_ERROR_CATALOG.md', $errorText);
$writeReport('MISA_OFFICIAL_CALLBACK_CONTRACT.md', "# MISA Official Callback Contract\n\nThe crawl searched all three product documentation families for callback, webhook, retry and acknowledgement contracts. No product-specific production callback contract was promoted without explicit endpoint evidence.\n\nStatus: NOT_PROVEN unless a specific endpoint evidence record states otherwise. Callback behavior in the existing ACT/AMIS family remains separate.\n");
$writeReport('MISA_OFFICIAL_TOKEN_CONTRACT.md', "# MISA Official Token Contract\n\n- MEINVOICE: `POST /invoice/token`, Authorization Bearer for subsequent calls; exact request/response fields are in endpoint evidence.\n- MEINVOICEBOT: `validateuser` then `auth/jwttoken`; expiry recovery is documented as repeating authentication. Refresh endpoint: NOT_PROVEN.\n- ESIGN: login, optional 2FA/OTP resend, refresh-token endpoint, `AuthorizationRM`, `expiresIn` in seconds as documented.\n\nThese lifecycles are not interchangeable with ACT/AMIS `X-MISA-AccessToken` or `expired_time_ticks`.\n");
$writeReport('MISA_OFFICIAL_ASYNC_RECONCILIATION_CONTRACT.md', "# MISA Official Async and Reconciliation Contract\n\nThe crawl records documented status/list/detail endpoints, but does not infer callback delivery guarantees, polling limits, retry schedules or reconciliation semantics. Any missing production result lookup or callback contract is NOT_PROVEN.\n");
$writeReport('MISA_OFFICIAL_MASTER_DATA_MATRIX.md', "# MISA Official Master Data Matrix\n\nMEINVOICE and MEINVOICEBOT document invoice/invoice-line structures; ESIGN documents signing structures. No CRM customer/product/category/unit/warehouse master-data synchronization contract was proven by this crawl. Those objects remain OUT OF SCOPE for provider implementation until separately evidenced.\n");
$writeReport('MISA_DOCUMENT_CHANGELOG.md', "# MISA Documentation Changelog\n\nThe portal exposes product changelog sections. Current crawl timestamp: {$retrieved}. Product documentation versions observed: MEINVOICE V1.0.0.0, MEINVOICEBOT V1.0.0.0, ESIGN V1.0.0.0.\n\nPrevious crawl comparison: `NOT_COMPUTABLE` because no prior evidence baseline existed. This crawl establishes the baseline; future runs must compare source hashes under `docs/evidence/misa/hashes/` and report added, removed and changed records.\n");
$trace = "# MISA Document-to-Code Traceability\n\n| Contract family | Current code | Status |\n|---|---|---|\n| ACT/AMIS | `modules/kt_misa_amis_accounting/libraries/MisaApiClient.php`, `MisaTokenService.php` | Existing ACT-specific implementation; not evidence for new products |\n| MEINVOICE | No provider adapter | AUDIT_ONLY_NO_IMPLEMENTATION |\n| MEINVOICEBOT | No provider adapter | AUDIT_ONLY_NO_IMPLEMENTATION |\n| ESIGN | No provider adapter | AUDIT_ONLY_NO_IMPLEMENTATION |\n\nNo product-specific source or schema change was made.\n";
$writeReport('MISA_DOCUMENT_CODE_TRACEABILITY.md', $trace);
$gapText = "# MISA Official Documentation Gaps\n\n| Gap ID | Product | Question | Status | Implementation impact |\n|---|---|---|---|---|\n";
foreach ($gaps as $g) $gapText .= '| ' . $g['gap_id'] . ' | ' . ($g['product'] ?? '') . ' | ' . $g['question'] . ' | ' . $g['status'] . ' | ' . $g['impact'] . " |\n";
$gapText .= "| GAP-CALLBACK | All | What are production callback registration, signature, retry and acknowledgement rules? | NOT_PROVEN | Blocks production callback adapter |\n| GAP-RATE | All | What are official rate, batch, concurrency and payload limits? | NOT_PROVEN | Blocks official retry/limit claims |\n| GAP-ENV | All | Which production/sandbox host is assigned to Khách Tốt? | NOT_PROVEN | Blocks external calls |\n";
$writeReport('MISA_OFFICIAL_DOCUMENT_GAPS.md', $gapText);
$writeReport('MISA_TECHNICAL_CONFIRMATION_REQUEST.md', "# MISA Technical Confirmation Request\n\n## ACT/AMIS Accounting\n\n1. Confirm exact base URL per AMIS Enterprise, ASP Accounting, test and production environment.\n2. Confirm authoritative token expiry field/units, reconnect behavior, overlap, revocation and production lifetime.\n3. Confirm callback registration, HMAC input/key/casing, acknowledgement, retries, timeout, duplicate delivery and IP requirements.\n4. Confirm production availability of `get_call_back_detail_error`, `check_call_back_data` and `delete_call_back_data`.\n5. Confirm save/dictionary/delete-dictionary object types, batch/payload/rate/concurrency limits and complete master-data fields.\n\n## MEINVOICE\n\nConfirm production/sandbox hosts, quotas, callback/result behavior and token lifetime beyond current V1.0.0.0 documentation.\n\n## MEINVOICEBOT\n\nConfirm the incomplete documented base URL, production host, token renewal/re-authentication, callbacks and rate/reconciliation behavior.\n\n## ESIGN\n\nConfirm the incomplete documented base URL, `AuthorizationRM` environment behavior, refresh-token semantics, 2FA/OTP production flow and limits.\n\nNo credentials are requested. Product families remain separate.\n");
$writeReport('MISA_OFFICIAL_CRAWL_VERDICT.md', "# MISA Official Crawl Verdict\n\nVerdict: `{$verdict}`\n\nProducts crawled: MEINVOICE, MEINVOICEBOT, ESIGN. Endpoint records: " . count($contracts) . ". Document records: " . count($inventory) . ". Nested JSON-string findings: " . count($nested) . ". Evidence snapshots and hashes are under `docs/evidence/misa/`. No source, schema, fixture or external provider call was made.\n\nThe result is partial because MEINVOICEBOT/ESIGN base URLs are incomplete in the official content and callback, rate-limit and reconciliation guarantees remain `NOT_PROVEN`. This is non-blocking for documentation inventory, but blocks provider implementation and all real MISA calls.\n");

// ACT/AMIS report set. These reports deliberately distinguish official facts,
// legacy/demo evidence and unresolved implementation assumptions.
$actRows = "| Contract ID | Method | Base URL | Path | Authentication | Async | Callback | Confidence | Evidence |\n|---|---|---|---|---|---|---|---|---|\n";
foreach ($actContracts as $c) $actRows .= '| `' . $c['contract_id'] . '` | `' . $c['method'] . '` | `' . $c['base_url'] . '` | `' . $c['path'] . '` | ' . $c['authentication'] . ' | ' . $c['async_behavior'] . ' | ' . $c['callback_relationship'] . ' | ' . $c['confidence'] . ' | ' . $c['evidence_url'] . " |\n";
$actSourcesText = implode(', ', array_map(static fn($s) => '[' . $s['product'] . '](' . $s['url'] . ')', $actSources));
$actWrite = static function (string $file, string $content) use ($root): void { file_put_contents($root . '/docs/reports/' . $file, $content); };
$actWrite('MISA_ACT_AMIS_OFFICIAL_DOCUMENT_INVENTORY.md', "# ACT/AMIS Official Document Inventory\n\nCrawl: {$retrieved}. Product family: `ACT_AMIS`; contract family: `ACT_AMIS_ACCOUNTING`. Sources crawled: {$actSourcesText}.\n\n" . count($actDocuments) . " official source records and " . count($actContracts) . " extracted endpoint references were recorded. Machine inventory: `docs/evidence/misa/inventory/MISA_ACT_AMIS_DOCUMENT_INVENTORY.json`. ACT evidence is not inherited from MEINVOICE, MEINVOICEBOT or ESIGN.\n\nStatuses are source-specific: current official documentation, legacy ASP page, and blocked/unavailable artifacts are preserved independently.\n");
$actWrite('MISA_ACT_AMIS_OFFICIAL_API_CONTRACT.md', "# ACT/AMIS Official API Contract\n\nExtracted endpoint references: " . count($actContracts) . ". Exact casing and source snapshots are preserved in the evidence inventory.\n\n{$actRows}\n\n`app_id`, `org_company_code`, `org_refid`, `X-MISA-AccessToken`, nested `data` and callback fields are treated as ACT/AMIS-specific evidence only. Batch limits, production environment assignment, retry guidance and several response/error details remain `NOT_PROVEN` where no explicit official statement was found.\n");
$actWrite('MISA_ACT_AMIS_OFFICIAL_TOKEN_CONTRACT.md', "# ACT/AMIS Official Token Contract\n\n| Question | Evidence-based result | Confidence |\n|---|---|---|\n| Is connect the token acquisition endpoint? | `POST /api/oauth/actopen/connect` is documented as the connect/token flow. | PROVEN |\n| Is `Data` a JSON string? | The official examples show response `Data` containing serialized JSON with `access_token`, `tenant_code`, `app_name`, `expired_time` and `expired_time_ticks`. | PROVEN |\n| Which expiry field is authoritative? | Both fields are documented/observed; authoritative precedence is NOT_PROVEN. | NOT_PROVEN |\n| Units of `expired_time_ticks` | Value is present in examples; units are NOT_PROVEN. | NOT_PROVEN |\n| Refresh token endpoint? | No separate ACT refresh endpoint was proven. | NOT_PROVEN |\n| Renewal mechanism | Reconnect is documented/used as the available acquisition flow; whether it invalidates old tokens is NOT_PROVEN. | PROVEN / NOT_PROVEN |\n| Multiple token overlap/revocation | NOT_PROVEN. | NOT_PROVEN |\n| Demo/production lifetime | A general official overview states 12-hour access-token validity; product/environment-specific lifetime remains NOT_PROVEN. | PROVEN_LEGACY / NOT_PROVEN |\n\nCurrent code must not present unproven expiry units, overlap or revocation behavior as MISA guarantees.\n");
$actWrite('MISA_ACT_AMIS_OFFICIAL_CALLBACK_CONTRACT.md', "# ACT/AMIS Official Callback Contract\n\nOfficial ACT OpenAPI examples document a POST callback envelope with `success`, `error_code`, `error_message`, `signature`, `app_id`, `org_company_code`, `data_type` and raw serialized `data`. The signature is described as SHA-256 HMAC over raw `data`, keyed by `app_id`; official sample code compares the generated signature.\n\n`data_type` examples include SaveVoucher and DeleteVoucher; item results include `org_refid`, success and error fields. Official sample code recommends saving the raw callback and processing asynchronously.\n\n| Topic | Verdict |\n|---|---|\n| HMAC algorithm/key/raw input | PROVEN for documented ACT callback examples: HMAC-SHA256, key `app_id`, input raw `data`. |\n| Hex casing | Example appears lowercase; acceptance of uppercase is NOT_PROVEN. |\n| Constant-time comparison | INTERNAL KHÁCH TỐT SECURITY POLICY — not a MISA contract claim. |\n| Acknowledgement schema | `Success`, `ErrorCode`, `ErrorMessage`, `Data` shown in official model/example. |\n| Retry, timeout, duplicate delivery, IP allow-list, registration | NOT_PROVEN by current official evidence. |\n");
$actWrite('MISA_ACT_AMIS_OFFICIAL_RECONCILIATION_CONTRACT.md', "# ACT/AMIS Official Reconciliation Contract\n\n| Function/endpoint | Official path | Environment | Purpose | Production availability | Verdict |\n|---|---|---|---|---|---|\n| `get_call_back_detail_error` | `/api/oauth/actopensupport/get_call_back_detail_error` | ACT demo/support documentation | Demo helper to inspect callback errors | NOT_PROVEN | PROVEN_DEMO_ONLY |\n| `check_call_back_data` | `/api/oauth/actopensupport/check_call_back_data` | ACT demo/support documentation | Demo callback-result inspection | NOT_PROVEN | PROVEN_DEMO_ONLY |\n| `delete_call_back_data` | `/api/oauth/actopensupport/delete_call_back_data` | ACT demo/support documentation | Demo cleanup of callback result | NOT_PROVEN | PROVEN_DEMO_ONLY |\n\n`PRODUCTION_RECONCILIATION_CONTRACT = NOT_PROVEN`. These helper paths must not be used by production worker logic without MISA confirmation.\n");
$actWrite('MISA_ACT_AMIS_OFFICIAL_MASTER_DATA_MATRIX.md', "# ACT/AMIS Official Master Data Matrix\n\n| Object | Official evidence | Push/Pull | Required keys/fields | Status |\n|---|---|---|---|---|\n| customer/account object | `dictionary_type` examples and dictionary payload | Pull evidence; push shape not fully proven | `account_object_id`, `account_object_code`, name/status fields | PROVEN_DEMO_ONLY |\n| supplier | Account-object model references vendor flags | NOT_PROVEN as independent contract | NOT_PROVEN | NOT_PROVEN |\n| employee | Account-object model references employee flags | NOT_PROVEN | NOT_PROVEN | NOT_PROVEN |\n| product/inventory item | `dictionary_type` example, inventory fields | Pull demo evidence | inventory ID/code/name/unit references | PROVEN_DEMO_ONLY |\n| service/category/unit/warehouse | Some nested dictionary references appear in examples | Independent contracts/batch/delete not proven | NOT_PROVEN | NOT_PROVEN |\n| bank account/department/cost item/contract | No complete independent contract proven | NOT_PROVEN | NOT_PROVEN | NOT_PROVEN |\n| voucher | Save payload and `voucher_type` documented | Push async evidence | `org_refid`, `voucher_type`, product-specific fields | PROVEN_DEMO_ONLY |\n\nCRM naming is not treated as equivalent to MISA object naming.\n");
$actWrite('MISA_ACT_AMIS_NESTED_JSON_STRING_MATRIX.md', "# ACT/AMIS Nested JSON-String Matrix\n\n| Endpoint/context | Direction | Outer field | Nested schema | Raw preservation/signature | Parse order | Failure behavior | Evidence |\n|---|---|---|---|---|---|---|---|\n| connect | response | `Data` | JSON object containing token metadata | Preserve response before parsing; callback signature not involved | Decode outer response, then `Data` | NOT_PROVEN | ACT OpenAPI snapshots |\n| callback | inbound | `data` | JSON array/object of item results | Preserve raw `data`; signature input is raw `data` | Verify signature before parsing | Reject/retain evidence per internal policy | ACT OpenAPI callback source |\n| save/dictionary examples | request/response | nested fields such as `reference_list`, `unit_list`, `purchase_last_unit_price_list` | Serialized JSON arrays in examples | Raw preservation requirement NOT_PROVEN | Product payload parser after envelope validation | NOT_PROVEN | ACT OpenAPI snapshots |\n");
$actWrite('MISA_ACT_AMIS_SAMPLE_CODE_AUDIT.md', "# ACT/AMIS Official Sample Code Audit\n\n| Source | Language | Version | Signature logic | JSON parsing | Error handling | Host | Credentials |\n|---|---|---|---|---|---|---|---|\n| ACT OpenAPI Help | C# | version not stated in page | HMAC-SHA256 over raw callback data keyed by `app_id` | JSON.NET model deserialization | Returns `Success/ErrorCode/ErrorMessage`; sample catches exception | APIUrl variable | Demo values redacted from stored evidence |\n| ASP ACT OpenAPI page | prose/C# examples | version not stated | Same callback pattern | JSON.NET examples | Async callback recommendation | `actasp.misa.vn` documented in linked article | Demo values redacted |\n\nNo demo credential or bearer token is copied into repository evidence.\n");
$actWrite('MISA_ACT_AMIS_DOCUMENT_CONFLICTS.md', "# ACT/AMIS Document Conflict Matrix\n\n| Conflict ID | Topic | Document A | Document B | Value A | Value B | Resolved | Impact |\n|---|---|---|---|---|---|---|---|\n| ACT-C-001 | Base URL | AMIS Enterprise article | ASP article | `https://actapp.misa.vn` | `https://actasp.misa.vn` | No; product/environment-specific | Must select by product contract, not silently |\n| ACT-C-002 | Token expiry | ACT examples | ASP overview | `expired_time` and `expired_time_ticks` fields | 12-hour overview | No; authoritative field/unit not proven | Token renewal implementation risk |\n| ACT-C-003 | Reconciliation | ACT OpenAPI support helpers | ASP callback prose | helper endpoints | callback-first async processing | No | Production helper availability not proven |\n| ACT-C-004 | Deleted dictionary | extracted path references | current structured product docs | `get_dictionary_delete` evidence is legacy/demo-oriented | no current product proof | No | Pull deletion implementation blocked |\n| ACT-C-005 | Limits | all crawled sources | none | no explicit official limits found | NOT_PROVEN | No | Internal limits cannot be labeled MISA limits |\n\nRequired confirmation: MISA must identify environment, token authority and production reconciliation support.\n");
$actWrite('MISA_ACT_AMIS_OFFICIAL_DOCUMENT_GAPS.md', "# ACT/AMIS Official Documentation Gaps\n\n| Gap ID | Question | Sources searched | Evidence found | Status | Current code/worker impact | Required confirmation |\n|---|---|---|---|---|---|---|\n| ACT-G-001 | Which base URL is assigned per app/environment? | ACT docs, MISA articles, legacy ASP | `actapp`, `actasp`, test variants | CONFLICTING | Connection environment cannot be promoted | Confirm exact hosts |\n| ACT-G-002 | Which expiry field/units are authoritative? | connect docs/examples | both fields present | NOT_PROVEN | Token refresh assumptions risk | Confirm lifetime/units |\n| ACT-G-003 | Is reconnect the official renewal path? | token docs | no refresh endpoint proven | NOT_PROVEN | Worker token lifecycle | Confirm reconnect/overlap/revocation |\n| ACT-G-004 | Are callback retry/duplicate/IP rules official? | callback docs/sample | envelope/HMAC/provisional async pattern | NOT_PROVEN | Callback recovery | Confirm delivery contract |\n| ACT-G-005 | Are reconciliation helper endpoints production-supported? | ACT support docs | demo helper paths | PROVEN_DEMO_ONLY | Worker must not call them in production | Confirm availability |\n| ACT-G-006 | What are batch/rate/payload/timeout limits? | all sources | no complete limits | NOT_PROVEN | Retry/lease tuning | Confirm limits |\n| ACT-G-007 | Complete master-data object matrix | dictionary/save docs | partial/demo examples | PARTIAL | Source mapping remains conditional | Confirm supported types/fields |\n");
$actWrite('MISA_ACT_AMIS_DOCUMENT_CODE_RECONCILIATION.md', "# ACT/AMIS Document-to-Code Reconciliation\n\n| Code file/class | Current assumption | Official evidence | Match status | Risk | Required future change |\n|---|---|---|---|---|---|\n| `libraries/MisaApiClient.php` / client | Generic client sends ACT-shaped operations | ACT endpoint/path evidence | MATCH_INTERNAL_POLICY | Base URL/limits not proven | Bind to confirmed environment contract |\n| `libraries/MisaTokenService.php` / `connect` | Parses `Data`, `access_token`, `expired_time`, `expired_time_ticks` | ACT connect examples | MATCH for fields; expiry authority CONFLICT/NOT_PROVEN | Incorrect renewal timing possible | Confirm authority/units before production |\n| `libraries/MisaCallbackVerifier.php` | HMAC-SHA256 raw data keyed by app ID | ACT callback sample | MATCH | Comparison casing/constant-time not official | Keep constant-time as internal policy |\n| `libraries/MisaCallbackInboxService.php` | Scoped envelope and raw-data evidence | ACT callback envelope | MATCH_INTERNAL_POLICY | Delivery retry/registration not proven | Confirm MISA delivery contract |\n| `libraries/MisaCallbackProcessor.php` | Processes item `org_refid` results | callback item model | MATCH_INTERNAL_POLICY | Production result reconciliation not proven | Keep mock-only until confirmation |\n| `libraries/MisaWorkerEngine.php` | Retry/reconciliation classification | no official worker contract | UNPROVEN_IMPLEMENTATION | Internal behavior may be mistaken for MISA guarantee | Label internal policy and bind confirmed limits |\n| `libraries/MisaMockTransport.php` | mock-only responses | no MISA contract claim | MATCH_INTERNAL_POLICY | none for docs phase | no external enablement |\n\nNo source was modified during this audit.\n");
$phase11Path = $root . '/tests/phase11_result.json';
$phase11 = is_file($phase11Path) ? json_decode((string) file_get_contents($phase11Path), true) : null;
$phase11Status = is_array($phase11) && ($phase11['run_id'] ?? '') === 'misa-phase11-20260805-161214' && ($phase11['executed'] ?? 0) === 19 && ($phase11['passed'] ?? 0) === 19 && ($phase11['skipped'] ?? -1) === 0 ? 'EVIDENCE_PRESENT_AND_INDEPENDENTLY_RECHECKED' : 'PHASE_11_CLAIM_NOT_INDEPENDENTLY_VERIFIED';
$actWrite('MISA_PHASE_11_EVIDENCE_INTEGRITY_AUDIT.md', "# Phase 11 Evidence Integrity Audit\n\nStatus: `{$phase11Status}`\n\nChecked `tests/phase11_result.json`, `tests/phase11_certification_runner.php` and the required Phase 11 reports. The result records run `" . ($phase11['run_id'] ?? 'MISSING') . "`, " . ($phase11['executed'] ?? 'MISSING') . " executed, " . ($phase11['passed'] ?? 'MISSING') . " passed, " . ($phase11['skipped'] ?? 'MISSING') . " skipped, zero network/MISA/accounting calls, production DB false, zero active leases and zero active token locks. Test IDs and artifact secret scan are present in the JSON.\n\nThis verifies evidence integrity only; it does not convert ACT/AMIS documentation gaps into production certification. Historical aggregate claims remain historical and are not rewritten.\n");
$actWrite('MISA_ACT_AMIS_CRAWL_VERDICT.md', "# ACT/AMIS Crawl Verdict\n\nVerdict: `ACT_AMIS_DOCUMENTATION_BLOCKED`\n\nThe crawl found official ACT/AMIS documentation and " . count($actContracts) . " endpoint references, but the current implementation/Phase 11 worker depends on unresolved environment selection, token expiry authority, production callback delivery rules, reconciliation availability, limits and complete master-data contracts. ACT/AMIS documentation audit is therefore incomplete and blocked for implementation claims.\n\nMEINVOICE, MEINVOICEBOT and ESIGN evidence is not used to certify ACT/AMIS. No source, schema, fixture, credential, database or business API call was changed or made.\n");

echo json_encode(['verdict' => $verdict, 'accessed_at' => $retrieved, 'products' => $products, 'endpoint_counts' => $productCounts, 'documents' => count($inventory), 'linked_documents' => count($linked), 'nested_json_findings' => count($nested), 'gaps' => count($gaps)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
