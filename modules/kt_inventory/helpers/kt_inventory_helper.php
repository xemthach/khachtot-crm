<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('kt_inventory_is_module_request')) {
    function kt_inventory_is_module_request()
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($requestUri, '/admin/kt_inventory') !== false;
    }
}

if (!function_exists('kt_inventory_staff_can')) {
    function kt_inventory_staff_can($capability)
    {
        return is_admin() || staff_can($capability, KT_INVENTORY_MODULE);
    }
}

if (!function_exists('kt_inventory_user_can_access_module')) {
    function kt_inventory_user_can_access_module()
    {
        if (is_admin()) {
            return true;
        }

        $caps = [
            'kt_inventory_view',
            'kt_inventory_manage_warehouses',
            'kt_inventory_manage_items',
            'kt_inventory_goods_receipt',
            'kt_inventory_goods_issue',
            'kt_inventory_adjustment',
            'kt_inventory_transfer',
            'kt_inventory_reports',
            'kt_inventory_settings',
        ];

        foreach ($caps as $capability) {
            if (staff_can($capability, KT_INVENTORY_MODULE)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('kt_inventory_get_option')) {
    function kt_inventory_get_option($name, $default = '')
    {
        $CI = &get_instance();
        $row = $CI->db->select('value')->where('name', $name)->get(db_prefix() . 'options')->row();
        if ($row) {
            return $row->value === '' ? $default : $row->value;
        }
        return $default;
    }
}

if (!function_exists('kt_inventory_document_statuses')) {
    function kt_inventory_document_statuses()
    {
        return [
            'draft'     => _l('kt_inventory_status_draft'),
            'posted'    => _l('kt_inventory_status_posted'),
            'cancelled' => _l('kt_inventory_status_cancelled'),
        ];
    }
}

if (!function_exists('kt_inventory_transaction_types')) {
    function kt_inventory_transaction_types()
    {
        return [
            'receipt'      => _l('kt_inventory_transaction_receipt'),
            'issue'        => _l('kt_inventory_transaction_issue'),
            'adjustment'   => _l('kt_inventory_transaction_adjustment'),
            'transfer_in'  => _l('kt_inventory_transaction_transfer_in'),
            'transfer_out' => _l('kt_inventory_transaction_transfer_out'),
        ];
    }
}

if (!function_exists('kt_inventory_barcode_types')) {
    function kt_inventory_barcode_types()
    {
        return [
            'ean13'        => 'EAN13',
            'upc'          => 'UPC',
            'code128'      => 'Code128',
            'qr'           => 'QR',
            'gs1'          => 'GS1',
            'internal'     => 'Internal',
            'manufacturer' => 'Manufacturer',
            'supplier'     => 'Supplier',
        ];
    }
}

if (!function_exists('kt_inventory_barcode_sources')) {
    function kt_inventory_barcode_sources()
    {
        return [
            'manual'       => 'Manual',
            'generated'    => 'Generated',
            'imported'     => 'Imported',
            'manufacturer' => 'Manufacturer',
            'supplier'     => 'Supplier',
        ];
    }
}

if (!function_exists('kt_inventory_reference_type_label')) {
    function kt_inventory_reference_type_label($referenceType)
    {
        $map = [
            'goods_receipt'     => 'Phiếu nhập kho',
            'goods_issue'       => 'Phiếu xuất kho',
            'stock_adjustment'  => 'Phiếu điều chỉnh',
            'stock_transfer'    => 'Phiếu chuyển kho',
            'invoice'           => 'Hóa đơn',
            'manual'            => 'Thủ công',
        ];

        $referenceType = (string) $referenceType;

        return $map[$referenceType] ?? ucwords(str_replace('_', ' ', $referenceType));
    }
}

if (!function_exists('kt_inventory_status_badge_class')) {
    function kt_inventory_status_badge_class($status)
    {
        $map = [
            'draft'     => 'warning',
            'posted'    => 'success',
            'cancelled' => 'danger',
        ];

        return $map[$status] ?? 'default';
    }
}

if (!function_exists('kt_inventory_generate_code')) {
    function kt_inventory_generate_code($type)
    {
        $map = [
            'receipt'    => ['table' => db_prefix() . 'kt_goods_receipts', 'column' => 'receipt_code', 'prefix_option' => 'kt_inventory_code_prefix_receipt'],
            'issue'      => ['table' => db_prefix() . 'kt_goods_issues', 'column' => 'issue_code', 'prefix_option' => 'kt_inventory_code_prefix_issue'],
            'adjustment' => ['table' => db_prefix() . 'kt_stock_adjustments', 'column' => 'adjustment_code', 'prefix_option' => 'kt_inventory_code_prefix_adjustment'],
            'transfer'   => ['table' => db_prefix() . 'kt_stock_transfers', 'column' => 'transfer_code', 'prefix_option' => 'kt_inventory_code_prefix_transfer'],
        ];

        if (!isset($map[$type])) {
            return strtoupper($type) . '-' . date('YmdHis');
        }

        $CI = &get_instance();
        $prefix = kt_inventory_get_option($map[$type]['prefix_option'], strtoupper(substr($type, 0, 3)));
        $count = (int) $CI->db->count_all($map[$type]['table']) + 1;

        return strtoupper(trim($prefix)) . '-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('kt_inventory_normalize_decimal')) {
    function kt_inventory_normalize_decimal($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) str_replace(',', '', (string) $value);
    }
}

if (!function_exists('kt_inventory_low_stock_label')) {
    function kt_inventory_low_stock_label($row)
    {
        return (float) $row['available_quantity'] <= (float) $row['min_stock'];
    }
}
