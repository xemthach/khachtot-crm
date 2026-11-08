<?php

defined('BASEPATH') or exit('No direct script access allowed');

final class MisaPayloadBuilders
{
    public function build($objectType, array $source)
    {
        $objectType = (string) $objectType;
        $base = ['schema_version' => 'misa-master-v1', 'object_type' => $objectType, 'code' => trim((string) ($source['code'] ?? $source['sku'] ?? '')), 'name' => trim((string) ($source['name'] ?? $source['description'] ?? ''))];
        if ($objectType === 'product') $base['unit'] = trim((string) ($source['unit'] ?? ''));
        if ($objectType === 'customer') { $base['address'] = trim((string) ($source['address'] ?? '')); $base['tax_code'] = trim((string) ($source['tax_code'] ?? $source['vat'] ?? '')); }
        if ($objectType === 'warehouse') $base['address'] = trim((string) ($source['address'] ?? ''));
        if ($objectType === 'product_category' || $objectType === 'unit') $base['active'] = !isset($source['active']) || (bool) $source['active'];
        ksort($base);
        return $base;
    }

    public function fingerprint(array $payload) { return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
}

