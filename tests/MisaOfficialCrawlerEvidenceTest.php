<?php
declare(strict_types=1);
$path = dirname(__DIR__) . '/docs/evidence/misa/inventory/MISA_ACT_AMIS_DOCUMENT_INVENTORY.json';
if (!is_file($path)) throw new RuntimeException('ACT/AMIS inventory missing.');
$data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
if (($data['product_family'] ?? '') !== 'ACT_AMIS' || empty($data['documents']) || empty($data['endpoints'])) throw new RuntimeException('ACT/AMIS evidence incomplete.');
foreach ($data['documents'] as $doc) foreach (['document_id','official_url','source_domain','product_family','contract_family','retrieved_at','status','source_hash','snapshot_hash'] as $field) if (!array_key_exists($field, $doc)) throw new RuntimeException('Missing evidence field: ' . $field);
echo "MisaOfficialCrawlerEvidenceTest passed.\n";
