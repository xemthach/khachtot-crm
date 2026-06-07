<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_inventory_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_dashboard_data()
    {
        return [
            'warehouse_count'   => $this->db->count_all_results(db_prefix() . 'kt_warehouses'),
            'item_count'        => $this->db->count_all_results(db_prefix() . 'kt_inventory_items'),
            'draft_receipts'    => $this->count_by_status('kt_goods_receipts', 'draft'),
            'draft_issues'      => $this->count_by_status('kt_goods_issues', 'draft'),
            'draft_adjustments' => $this->count_by_status('kt_stock_adjustments', 'draft'),
            'draft_transfers'   => $this->count_by_status('kt_stock_transfers', 'draft'),
            'low_stock_count'   => count($this->get_low_stock_balances()),
            'active_reservations' => $this->count_by_status('kt_stock_reservations', 'active'),
            'recent_transactions' => $this->get_transactions([
                'limit' => 10,
            ]),
        ];
    }

    public function get_warehouses($filters = [])
    {
        $this->db->from(db_prefix() . 'kt_warehouses w');
        $this->db->select('w.*, CONCAT(tblstaff.firstname, " ", tblstaff.lastname) as manager_name', false);
        $this->db->join(db_prefix() . 'staff', 'tblstaff.staffid = w.manager_staff_id', 'left');

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $this->db->where('w.is_active', (int) $filters['is_active']);
        }

        $this->db->order_by('w.warehouse_name', 'asc');
        return $this->db->get()->result_array();
    }

    public function get_warehouse($id)
    {
        return $this->db->where('id', $id)->get(db_prefix() . 'kt_warehouses')->row_array();
    }

    public function save_warehouse($data, $id = null)
    {
        if (trim((string) ($data['warehouse_code'] ?? '')) === '' || trim((string) ($data['warehouse_name'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập mã kho và tên kho.'];
        }

        if (!$id && function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
            $entitlementServicePath = module_dir_path('kt_saas', 'services/TenantEntitlementService.php');
            if (file_exists($entitlementServicePath)) {
                require_once $entitlementServicePath;
                $entitlements = new TenantEntitlementService();
                $entitlements->assertCanCreate('warehouses');
            }
        }

        $payload = [
            'warehouse_code'    => trim($data['warehouse_code']),
            'warehouse_name'    => trim($data['warehouse_name']),
            'address'           => trim((string) ($data['address'] ?? '')),
            'manager_staff_id'  => $data['manager_staff_id'] !== '' ? (int) $data['manager_staff_id'] : null,
            'is_active'         => isset($data['is_active']) ? 1 : 0,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        $existing = $this->db->where('warehouse_code', $payload['warehouse_code']);
        if ($id) {
            $existing->where('id !=', $id);
        }

        if ($existing->get(db_prefix() . 'kt_warehouses')->row()) {
            return ['success' => false, 'message' => 'Mã kho đã tồn tại.'];
        }

        if ($id) {
            $this->db->where('id', $id)->update(db_prefix() . 'kt_warehouses', $payload);
            return ['success' => true, 'id' => $id];
        }

        $payload['created_at'] = $payload['updated_at'];
        $this->db->insert(db_prefix() . 'kt_warehouses', $payload);

        if (function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
            $entitlementServicePath = module_dir_path('kt_saas', 'services/TenantEntitlementService.php');
            if (file_exists($entitlementServicePath)) {
                require_once $entitlementServicePath;
                $entitlements = new TenantEntitlementService();
                $entitlements->persistUsageSnapshot();
            }
        }

        return ['success' => true, 'id' => $this->db->insert_id()];
    }

    public function delete_warehouse($id)
    {
        if ($this->warehouse_has_transactions($id)) {
            return false;
        }

        $this->db->where('id', $id)->delete(db_prefix() . 'kt_warehouses');
        $deleted = $this->db->affected_rows() > 0;

        if ($deleted && function_exists('kt_saas_is_tenant_runtime') && kt_saas_is_tenant_runtime()) {
            $entitlementServicePath = module_dir_path('kt_saas', 'services/TenantEntitlementService.php');
            if (file_exists($entitlementServicePath)) {
                require_once $entitlementServicePath;
                $entitlements = new TenantEntitlementService();
                $entitlements->persistUsageSnapshot();
            }
        }

        return $deleted;
    }

    public function warehouse_has_transactions($warehouseId)
    {
        return $this->db->where('warehouse_id', $warehouseId)->count_all_results(db_prefix() . 'kt_stock_transactions') > 0;
    }

    public function get_inventory_items($filters = [])
    {
        $this->db->from(db_prefix() . 'kt_inventory_items i');
        $this->db->select('i.*, tblitems.description as name, tblitems.unit as unit, tblitems.description as core_item_name, pb.barcode as primary_barcode, pb.barcode_type as primary_barcode_type, pb.unit_type as primary_barcode_unit_type, pb.package_quantity as primary_package_quantity');
        $this->db->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner');
        $this->db->join(
            '(SELECT inventory_item_id, barcode, barcode_type, unit_type, package_quantity FROM ' . db_prefix() . 'kt_item_barcodes WHERE is_primary = 1 AND is_active = 1) pb',
            'pb.inventory_item_id = i.id',
            'left'
        );

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $this->db->where('i.is_active', (int) $filters['is_active']);
        }

        $this->db->order_by('tblitems.description', 'asc');
        return $this->db->get()->result_array();
    }

    public function get_inventory_item($id)
    {
        return $this->db
            ->select('i.*, tblitems.description as name, tblitems.unit as unit, pb.barcode as primary_barcode, pb.barcode_type as primary_barcode_type, pb.unit_type as primary_barcode_unit_type, pb.package_quantity as primary_package_quantity')
            ->from(db_prefix() . 'kt_inventory_items i')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner')
            ->join(
                '(SELECT inventory_item_id, barcode, barcode_type, unit_type, package_quantity FROM ' . db_prefix() . 'kt_item_barcodes WHERE is_primary = 1 AND is_active = 1) pb',
                'pb.inventory_item_id = i.id',
                'left'
            )
            ->where('i.id', $id)
            ->get()
            ->row_array();
    }

    public function get_item_barcodes($inventoryItemId)
    {
        return $this->db
            ->where('inventory_item_id', (int) $inventoryItemId)
            ->order_by('is_primary', 'desc')
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_item_barcodes')
            ->result_array();
    }

    public function get_barcode($barcodeId)
    {
        return $this->db->where('id', (int) $barcodeId)->get(db_prefix() . 'kt_item_barcodes')->row_array();
    }

    public function find_item_by_barcode($barcode)
    {
        $barcode = trim((string) $barcode);
        if ($barcode === '') {
            return null;
        }

        $parsed = $this->parse_gs1_barcode($barcode);
        $searchBarcodes = [$barcode];
        if (!empty($parsed['parsed']) && !empty($parsed['gtin'])) {
            $searchBarcodes[] = $parsed['gtin'];
            $searchBarcodes[] = ltrim($parsed['gtin'], '0');
        }

        $row = $this->db
            ->select('b.*, i.id as inventory_item_id, tblitems.description as item_name, i.sku, tblitems.unit as primary_unit, i.track_lot, i.track_serial')
            ->from(db_prefix() . 'kt_item_barcodes b')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = b.inventory_item_id')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner')
            ->where_in('b.barcode', $searchBarcodes)
            ->where('b.is_active', 1)
            ->where('i.is_active', 1)
            ->get()
            ->row_array();

        if (!$row) {
            return null;
        }

        $row['batch_required'] = !empty($row['track_lot']) || !empty($row['track_serial']);
        
        if (!empty($parsed['parsed'])) {
            $row['gs1_data'] = $parsed;
            $row['lot_number'] = $parsed['lot'] ?? null;
            $row['expiry_date'] = $parsed['expiry'] ?? null;
            $row['serial_number'] = $parsed['serial'] ?? null;
            
            if ($row['lot_number']) {
                $batch = $this->db->where('inventory_item_id', $row['inventory_item_id'])
                                  ->where('lot_number', $row['lot_number'])
                                  ->get(db_prefix() . 'kt_inventory_batches')
                                  ->row_array();
                if ($batch) {
                    $row['batch_id'] = (int)$batch['id'];
                }
            }
        } else {
            $row['gs1_data'] = null;
        }

        return $row;
    }

    public function add_item_barcode($data)
    {
        $inventoryItemId = (int) ($data['inventory_item_id'] ?? 0);
        $barcode = trim((string) ($data['barcode'] ?? ''));
        $barcodeType = trim((string) ($data['barcode_type'] ?? 'internal'));
        $unitType = trim((string) ($data['unit_type'] ?? ''));
        $packageQuantity = kt_inventory_normalize_decimal($data['package_quantity'] ?? 1);
        $source = trim((string) ($data['source'] ?? 'manual'));
        $isPrimary = isset($data['is_primary']) ? (int) ((string) $data['is_primary'] === '1' || $data['is_primary'] === 1 || $data['is_primary'] === true) : 0;
        $isActive = isset($data['is_active']) ? (int) ((string) $data['is_active'] === '1' || $data['is_active'] === 1 || $data['is_active'] === true) : 1;

        if (!$this->inventory_item_exists($inventoryItemId)) {
            return ['success' => false, 'message' => 'Hàng hóa được chọn không tồn tại.'];
        }
        if ($barcode === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập mã vạch.'];
        }
        if (strlen($barcode) > 191 || preg_match('/[\x00-\x1F\x7F]/', $barcode)) {
            return ['success' => false, 'message' => 'Mã vạch chứa ký tự không hợp lệ hoặc vượt quá độ dài cho phép.'];
        }
        if ($packageQuantity <= 0) {
            return ['success' => false, 'message' => 'Số lượng mỗi gói phải lớn hơn 0.'];
        }
        if ($this->barcode_exists($barcode)) {
            return ['success' => false, 'message' => 'Mã vạch đã tồn tại.'];
        }
        $formatValidation = $this->validate_barcode_format($barcode, $barcodeType);
        if (!$formatValidation['success']) {
            return $formatValidation;
        }

        $activeCount = $this->db
            ->where('inventory_item_id', $inventoryItemId)
            ->where('is_active', 1)
            ->count_all_results(db_prefix() . 'kt_item_barcodes');

        if ($activeCount === 0 && $isActive) {
            $isPrimary = 1;
        }
        if (!$isActive) {
            $isPrimary = 0;
        }

        $payload = [
            'inventory_item_id' => $inventoryItemId,
            'barcode'           => $barcode,
            'barcode_type'      => $barcodeType,
            'unit_type'         => $unitType,
            'package_quantity'  => $packageQuantity,
            'is_primary'        => $isPrimary,
            'source'            => $source,
            'is_active'         => $isActive,
            'created_by'        => get_staff_user_id() ?: null,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        $this->db->trans_begin();
        if ($isPrimary) {
            $this->db->where('inventory_item_id', $inventoryItemId)->update(db_prefix() . 'kt_item_barcodes', ['is_primary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->insert(db_prefix() . 'kt_item_barcodes', $payload);
        $barcodeId = $this->db->insert_id();
        $this->sync_legacy_primary_barcode($inventoryItemId);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['success' => false, 'message' => 'Không thể lưu mã vạch.'];
        }

        $this->db->trans_commit();
        return ['success' => true, 'id' => $barcodeId];
    }

    public function update_item_barcode($barcodeId, $data)
    {
        $current = $this->get_barcode($barcodeId);
        if (!$current) {
            return ['success' => false, 'message' => 'Không tìm thấy mã vạch.'];
        }

        $barcode = trim((string) ($data['barcode'] ?? $current['barcode']));
        $barcodeType = trim((string) ($data['barcode_type'] ?? $current['barcode_type']));
        $packageQuantity = kt_inventory_normalize_decimal($data['package_quantity'] ?? $current['package_quantity']);
        $isPrimary = isset($data['is_primary']) ? (int) ((string) $data['is_primary'] === '1' || $data['is_primary'] === 1 || $data['is_primary'] === true) : (int) $current['is_primary'];
        $isActive = isset($data['is_active']) ? (int) ((string) $data['is_active'] === '1' || $data['is_active'] === 1 || $data['is_active'] === true) : (int) $current['is_active'];

        if ($barcode === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập mã vạch.'];
        }
        if (strlen($barcode) > 191 || preg_match('/[\x00-\x1F\x7F]/', $barcode)) {
            return ['success' => false, 'message' => 'Mã vạch chứa ký tự không hợp lệ hoặc vượt quá độ dài cho phép.'];
        }
        if ($packageQuantity <= 0) {
            return ['success' => false, 'message' => 'Số lượng mỗi gói phải lớn hơn 0.'];
        }
        if ($this->barcode_exists($barcode, $barcodeId)) {
            return ['success' => false, 'message' => 'Mã vạch đã tồn tại.'];
        }
        $formatValidation = $this->validate_barcode_format($barcode, $barcodeType);
        if (!$formatValidation['success']) {
            return $formatValidation;
        }

        $payload = [
            'barcode'          => $barcode,
            'barcode_type'     => $barcodeType,
            'unit_type'        => trim((string) ($data['unit_type'] ?? $current['unit_type'])),
            'package_quantity' => $packageQuantity,
            'is_primary'       => $isPrimary,
            'source'           => trim((string) ($data['source'] ?? $current['source'])),
            'is_active'        => $isActive,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        $this->db->trans_begin();
        if ($isPrimary) {
            $this->db->where('inventory_item_id', (int) $current['inventory_item_id'])->update(db_prefix() . 'kt_item_barcodes', ['is_primary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->where('id', (int) $barcodeId)->update(db_prefix() . 'kt_item_barcodes', $payload);

        $activePrimary = $this->db
            ->where('inventory_item_id', (int) $current['inventory_item_id'])
            ->where('is_active', 1)
            ->where('is_primary', 1)
            ->count_all_results(db_prefix() . 'kt_item_barcodes');
        if ($activePrimary === 0) {
            $fallback = $this->db
                ->where('inventory_item_id', (int) $current['inventory_item_id'])
                ->where('is_active', 1)
                ->order_by('id', 'asc')
                ->get(db_prefix() . 'kt_item_barcodes')
                ->row_array();
            if ($fallback) {
                $this->db->where('id', (int) $fallback['id'])->update(db_prefix() . 'kt_item_barcodes', ['is_primary' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $this->sync_legacy_primary_barcode((int) $current['inventory_item_id']);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['success' => false, 'message' => 'Không thể cập nhật mã vạch.'];
        }

        $this->db->trans_commit();
        return ['success' => true, 'id' => $barcodeId];
    }

    public function delete_item_barcode($barcodeId)
    {
        $current = $this->get_barcode($barcodeId);
        if (!$current) {
            return false;
        }

        $this->db->trans_begin();
        $this->db->where('id', (int) $barcodeId)->update(db_prefix() . 'kt_item_barcodes', [
            'is_active'  => 0,
            'is_primary' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $fallback = $this->db
            ->where('inventory_item_id', (int) $current['inventory_item_id'])
            ->where('is_active', 1)
            ->order_by('id', 'asc')
            ->get(db_prefix() . 'kt_item_barcodes')
            ->row_array();
        if ($fallback) {
            $this->db->where('id', (int) $fallback['id'])->update(db_prefix() . 'kt_item_barcodes', ['is_primary' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->sync_legacy_primary_barcode((int) $current['inventory_item_id']);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return true;
    }

    public function set_primary_barcode($inventoryItemId, $barcodeId)
    {
        $barcode = $this->db
            ->where('id', (int) $barcodeId)
            ->where('inventory_item_id', (int) $inventoryItemId)
            ->where('is_active', 1)
            ->get(db_prefix() . 'kt_item_barcodes')
            ->row_array();
        if (!$barcode) {
            return false;
        }

        $this->db->trans_begin();
        $this->db->where('inventory_item_id', (int) $inventoryItemId)->update(db_prefix() . 'kt_item_barcodes', ['is_primary' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->db->where('id', (int) $barcodeId)->update(db_prefix() . 'kt_item_barcodes', ['is_primary' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->sync_legacy_primary_barcode((int) $inventoryItemId);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return true;
    }

    public function barcode_exists($barcode, $excludeId = null)
    {
        $query = $this->db->where('barcode', trim((string) $barcode));
        if ($excludeId) {
            $query->where('id !=', (int) $excludeId);
        }

        return $query->count_all_results(db_prefix() . 'kt_item_barcodes') > 0;
    }

    public function generate_internal_barcode($inventoryItemId, $type = 'code128')
    {
        $inventoryItemId = (int) $inventoryItemId;
        if (!$this->inventory_item_exists($inventoryItemId)) {
            return ['success' => false, 'message' => 'Hàng hóa được chọn không tồn tại.'];
        }

        $prefix = strtoupper(trim(kt_inventory_get_option('kt_inventory_internal_barcode_prefix', 'KTINV')));
        $barcodeType = trim((string) ($type ?: kt_inventory_get_option('kt_inventory_internal_barcode_type', 'code128')));
        $running = (int) kt_inventory_get_option('kt_inventory_next_barcode_number', '1');

        do {
            $barcode = $prefix . '-' . str_pad((string) $inventoryItemId, 6, '0', STR_PAD_LEFT) . '-' . str_pad((string) $running, 6, '0', STR_PAD_LEFT);
            $running++;
        } while ($this->barcode_exists($barcode));

        update_option('kt_inventory_next_barcode_number', (string) $running);

        return $this->add_item_barcode([
            'inventory_item_id' => $inventoryItemId,
            'barcode'           => $barcode,
            'barcode_type'      => $barcodeType,
            'unit_type'         => $this->get_inventory_item($inventoryItemId)['unit'] ?? '',
            'package_quantity'  => 1,
            'is_primary'        => 0,
            'source'            => 'generated',
            'is_active'         => 1,
        ]);
    }

    public function validate_barcode_format($barcode, $barcodeType)
    {
        $barcode = trim((string) $barcode);
        $barcodeType = trim((string) $barcodeType);

        if ($barcode === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập mã vạch.'];
        }

        if ($barcodeType === 'ean13') {
            if (!preg_match('/^\d{13}$/', $barcode)) {
                return ['success' => false, 'message' => 'Mã vạch EAN13 phải gồm đúng 13 chữ số.'];
            }
            if (!$this->validate_ean13_checksum($barcode)) {
                return ['success' => false, 'message' => 'Mã kiểm tra của mã vạch EAN13 không hợp lệ.'];
            }
        }

        if ($barcodeType === 'upc' && !preg_match('/^\d{12}$/', $barcode)) {
            return ['success' => false, 'message' => 'Mã vạch UPC phải gồm đúng 12 chữ số.'];
        }

        return ['success' => true];
    }

    public function parse_gs1_barcode($barcode)
    {
        $barcode = trim((string) $barcode);
        $clean = preg_replace('/\((\d+)\)/', '$1', $barcode);
        
        $gtin = null;
        $expiry = null;
        $lot = null;
        $serial = null;
        
        if (preg_match('/^01(\d{14})/', $clean, $matches)) {
            $gtin = $matches[1];
            $remaining = substr($clean, 16);
            
            $offset = 0;
            $len = strlen($remaining);
            while ($offset < $len) {
                $ai = substr($remaining, $offset, 2);
                if ($ai === '17') {
                    $yy = substr($remaining, $offset + 2, 2);
                    $mm = substr($remaining, $offset + 4, 2);
                    $dd = substr($remaining, $offset + 6, 2);
                    $year = '20' . $yy;
                    $expiry = "$year-$mm-$dd";
                    $offset += 8;
                } elseif ($ai === '10') {
                    $rest = substr($remaining, $offset + 2);
                    $gs_pos = strpos($rest, "\x1d");
                    if ($gs_pos === false) {
                        $gs_pos = strpos($rest, "^");
                    }
                    if ($gs_pos !== false) {
                        $lot = substr($rest, 0, $gs_pos);
                        $offset += 2 + $gs_pos + 1;
                    } else {
                        $lot = $rest;
                        break;
                    }
                } elseif ($ai === '21') {
                    $rest = substr($remaining, $offset + 2);
                    $gs_pos = strpos($rest, "\x1d");
                    if ($gs_pos === false) {
                        $gs_pos = strpos($rest, "^");
                    }
                    if ($gs_pos !== false) {
                        $serial = substr($rest, 0, $gs_pos);
                        $offset += 2 + $gs_pos + 1;
                    } else {
                        $serial = $rest;
                        break;
                    }
                } else {
                    $offset++;
                }
            }
        }
        
        if ($gtin) {
            return [
                'raw'     => $barcode,
                'parsed'  => true,
                'gtin'    => $gtin,
                'expiry'  => $expiry,
                'lot'     => $lot,
                'serial'  => $serial,
            ];
        }
        
        return [
            'raw'     => $barcode,
            'parsed'  => false,
            'message' => 'Không phải mã vạch GS1 hợp lệ hoặc định dạng chưa được hỗ trợ',
        ];
    }

    public function save_inventory_item($data, $id = null)
    {
        if (trim((string) ($data['sku'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập mã hàng.'];
        }
        if (empty($data['item_id'])) {
            return ['success' => false, 'message' => 'Vui lòng chọn hàng hóa liên kết.'];
        }

        $minStock = kt_inventory_normalize_decimal($data['min_stock'] ?? 0);
        $maxStock = kt_inventory_normalize_decimal($data['max_stock'] ?? 0);
        if ($maxStock > 0 && $maxStock < $minStock) {
            return ['success' => false, 'message' => 'Tồn kho tối đa phải lớn hơn hoặc bằng tồn kho tối thiểu.'];
        }

        if (!$this->record_exists('items', 'id', (int) $data['item_id'])) {
            return ['success' => false, 'message' => 'Hàng hóa liên kết được chọn không tồn tại.'];
        }

        $existingLink = $this->db->where('item_id', (int) $data['item_id']);
        if ($id) {
            $existingLink->where('id !=', $id);
        }
        if ($existingLink->get(db_prefix() . 'kt_inventory_items')->row()) {
            return ['success' => false, 'message' => 'Hàng hóa này đã được liên kết với một bản ghi kho khác.'];
        }

        $payload = [
            'item_id'     => (int) $data['item_id'],
            'sku'         => trim($data['sku']),
            'track_lot'   => isset($data['track_lot']) ? 1 : 0,
            'track_serial'=> isset($data['track_serial']) ? 1 : 0,
            'min_stock'   => $minStock,
            'max_stock'   => $maxStock,
            'is_active'   => isset($data['is_active']) ? 1 : 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($payload['track_serial'] && $payload['track_lot']) {
            return ['success' => false, 'message' => 'Không thể bật đồng thời theo dõi serial và theo dõi lô cho cùng một hàng hóa trong phiên bản hiện tại.'];
        }

        $existing = $this->db->where('sku', $payload['sku']);
        if ($id) {
            $existing->where('id !=', $id);
        }

        if ($existing->get(db_prefix() . 'kt_inventory_items')->row()) {
            return ['success' => false, 'message' => 'Mã hàng đã tồn tại.'];
        }

        if ($id) {
            $this->db->where('id', $id)->update(db_prefix() . 'kt_inventory_items', $payload);
            return ['success' => true, 'id' => $id];
        }

        $payload['created_at'] = $payload['updated_at'];
        $this->db->insert(db_prefix() . 'kt_inventory_items', $payload);
        $insertId = $this->db->insert_id();

        if (trim((string) ($data['barcode'] ?? '')) !== '') {
            $core_item = $this->db->where('id', (int) $data['item_id'])->get(db_prefix() . 'items')->row_array();
            $this->add_item_barcode([
                'inventory_item_id' => $insertId,
                'barcode'           => trim((string) $data['barcode']),
                'barcode_type'      => 'internal',
                'unit_type'         => trim((string) ($core_item['unit'] ?? '')),
                'package_quantity'  => 1,
                'is_primary'        => 1,
                'source'            => 'manual',
                'is_active'         => 1,
            ]);
        }

        return ['success' => true, 'id' => $insertId];
    }

    public function deactivate_inventory_item($id)
    {
        $this->db->where('id', $id)->update(db_prefix() . 'kt_inventory_items', [
            'is_active'  => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->affected_rows() > 0;
    }

    public function delete_inventory_item_with_clear($inventoryItemId, $deleteCoreItem = false)
    {
        $inventoryItemId = (int) $inventoryItemId;
        if ($inventoryItemId <= 0) {
            return ['success' => false, 'message' => _l('kt_inventory_invalid_request')];
        }

        $item = $this->db->where('id', $inventoryItemId)->get(db_prefix() . 'kt_inventory_items')->row_array();
        if (!$item) {
            return ['success' => false, 'message' => _l('kt_inventory_item_not_found')];
        }

        $this->db->trans_begin();
        try {
            $tableChecks = [
                ['table' => 'kt_stock_transactions', 'column' => 'inventory_item_id'],
                ['table' => 'kt_stock_balances', 'column' => 'inventory_item_id'],
                ['table' => 'kt_stock_reservations', 'column' => 'inventory_item_id'],
                ['table' => 'kt_item_barcodes', 'column' => 'inventory_item_id'],
                ['table' => 'kt_inventory_batches', 'column' => 'inventory_item_id'],
                ['table' => 'kt_goods_receipt_items', 'column' => 'inventory_item_id'],
                ['table' => 'kt_goods_issue_items', 'column' => 'inventory_item_id'],
                ['table' => 'kt_stock_adjustment_items', 'column' => 'inventory_item_id'],
                ['table' => 'kt_stock_transfer_items', 'column' => 'inventory_item_id'],
            ];

            foreach ($tableChecks as $entry) {
                $tableName = db_prefix() . $entry['table'];
                if ($this->db->table_exists($tableName)) {
                    $this->db->where($entry['column'], $inventoryItemId)->delete($tableName);
                }
            }

            if (!empty($item['item_id'])) {
                $coreItemId = (int) $item['item_id'];
                if ($this->db->table_exists(db_prefix() . 'itemable')) {
                    // Perfex itemable uses rel_id + rel_type, not item_id.
                    $this->db
                        ->where('rel_id', $coreItemId)
                        ->where('rel_type', 'item')
                        ->delete(db_prefix() . 'itemable');
                }
                if ($this->db->table_exists(db_prefix() . 'item_tax')) {
                    $this->db->where('itemid', $coreItemId)->delete(db_prefix() . 'item_tax');
                }
            }

            $this->db->where('id', $inventoryItemId)->delete(db_prefix() . 'kt_inventory_items');
            if ($this->db->affected_rows() <= 0) {
                throw new Exception('Failed to delete inventory item row');
            }

            if ($deleteCoreItem && !empty($item['item_id']) && $this->db->table_exists(db_prefix() . 'items')) {
                $coreItemId = (int) $item['item_id'];
                $this->db->where('id', $coreItemId)->delete(db_prefix() . 'items');
            }

            if ($this->db->trans_status() === false) {
                throw new Exception('Transaction failed');
            }
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_activity('KT Inventory: delete_inventory_item_with_clear failed. item=' . $inventoryItemId . ' error=' . $e->getMessage());
            return ['success' => false, 'message' => _l('kt_inventory_item_delete_failed')];
        }

        $this->db->trans_commit();
        return ['success' => true];
    }

    public function set_inventory_item_active($id, $isActive)
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'kt_inventory_items', [
            'is_active'  => $isActive ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->affected_rows() >= 0;
    }

    public function set_warehouse_active($id, $isActive)
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'kt_warehouses', [
            'is_active'  => $isActive ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->affected_rows() >= 0;
    }

    public function assign_warehouse_manager($id, $managerStaffId)
    {
        $payload = [
            'manager_staff_id' => $managerStaffId ? (int) $managerStaffId : null,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        $this->db->where('id', (int) $id)->update(db_prefix() . 'kt_warehouses', $payload);

        return $this->db->affected_rows() >= 0;
    }

    public function item_has_transactions($itemId)
    {
        return $this->db->where('inventory_item_id', $itemId)->count_all_results(db_prefix() . 'kt_stock_transactions') > 0;
    }

    public function get_stock_balances($filters = [])
    {
        $this->db->from(db_prefix() . 'kt_stock_balances b');
        $this->db->select('b.*, w.warehouse_code, w.warehouse_name, i.sku, tblitems.description as name, tblitems.unit as unit, pb.barcode as primary_barcode, i.min_stock, i.max_stock, bt.lot_number, bt.expiry_date, bt.qc_status');
        $this->db->join(db_prefix() . 'kt_warehouses w', 'w.id = b.warehouse_id');
        $this->db->join(db_prefix() . 'kt_inventory_items i', 'i.id = b.inventory_item_id');
        $this->db->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner');
        $this->db->join(db_prefix() . 'kt_inventory_batches bt', 'bt.id = b.batch_id', 'left');
        $this->db->join(
            '(SELECT inventory_item_id, barcode FROM ' . db_prefix() . 'kt_item_barcodes WHERE is_primary = 1 AND is_active = 1) pb',
            'pb.inventory_item_id = i.id',
            'left'
        );

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('b.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (!empty($filters['inventory_item_id'])) {
            $this->db->where('b.inventory_item_id', (int) $filters['inventory_item_id']);
        }

        if (!empty($filters['keyword'])) {
            $this->db->group_start()
                ->like('tblitems.description', $filters['keyword'])
                ->or_like('i.sku', $filters['keyword'])
                ->or_like('pb.barcode', $filters['keyword'])
                ->or_like('w.warehouse_name', $filters['keyword'])
            ->group_end();
        }

        $this->db->order_by('w.warehouse_name', 'asc');
        $this->db->order_by('tblitems.description', 'asc');

        return $this->db->get()->result_array();
    }

    public function get_low_stock_balances()
    {
        $rows = $this->get_stock_balances();

        return array_values(array_filter($rows, function ($row) {
            return kt_inventory_low_stock_label($row);
        }));
    }

    public function get_transactions($filters = [])
    {
        $this->db->from(db_prefix() . 'kt_stock_transactions t');
        $this->db->select('t.*, w.warehouse_name, i.sku, tblitems.description as item_name, CONCAT(s.firstname, " ", s.lastname) as created_by_name, bt.lot_number, bt.expiry_date', false);
        $this->db->join(db_prefix() . 'kt_warehouses w', 'w.id = t.warehouse_id', 'left');
        $this->db->join(db_prefix() . 'kt_inventory_items i', 'i.id = t.inventory_item_id', 'left');
        $this->db->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'left');
        $this->db->join(db_prefix() . 'kt_inventory_batches bt', 'bt.id = t.batch_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = t.created_by', 'left');

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('t.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (!empty($filters['inventory_item_id'])) {
            $this->db->where('t.inventory_item_id', (int) $filters['inventory_item_id']);
        }

        if (!empty($filters['transaction_type'])) {
            $this->db->where('t.transaction_type', $filters['transaction_type']);
        }

        if (!empty($filters['reference_type'])) {
            $this->db->where('t.reference_type', $filters['reference_type']);
        }

        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(t.created_at) >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(t.created_at) <=', $filters['date_to']);
        }

        $this->db->order_by('t.created_at', 'desc');

        if (!empty($filters['limit'])) {
            $this->db->limit((int) $filters['limit']);
        }

        return $this->db->get()->result_array();
    }

    public function get_reservations($filters = [])
    {
        $this->db->from(db_prefix() . 'kt_stock_reservations r');
        $this->db->select('r.*, w.warehouse_name, i.sku, tblitems.description as item_name, c.company as customer_name, CONCAT(s.firstname, " ", s.lastname) as reserved_by_name', false);
        $this->db->join(db_prefix() . 'kt_warehouses w', 'w.id = r.warehouse_id', 'left');
        $this->db->join(db_prefix() . 'kt_inventory_items i', 'i.id = r.inventory_item_id', 'left');
        $this->db->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'left');
        $this->db->join(db_prefix() . 'clients c', 'c.userid = r.customer_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = r.reserved_by', 'left');

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('r.warehouse_id', (int) $filters['warehouse_id']);
        }
        if (!empty($filters['inventory_item_id'])) {
            $this->db->where('r.inventory_item_id', (int) $filters['inventory_item_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('r.status', $filters['status']);
        }

        $this->db->order_by('r.created_at', 'desc');
        return $this->db->get()->result_array();
    }

    public function get_goods_receipts()
    {
        return $this->get_document_list('kt_goods_receipts', 'receipt_date', 'receipt_code', 'supplier_name');
    }

    public function get_goods_receipt($id)
    {
        return $this->get_document('kt_goods_receipts', 'kt_goods_receipt_items', 'receipt_id', $id);
    }

    public function save_goods_receipt($data, $lines, $id = null)
    {
        if (empty($data['warehouse_id']) || empty($data['receipt_date'])) {
            return ['success' => false, 'message' => 'Warehouse and receipt date are required.'];
        }
        if (!$this->warehouse_exists((int) $data['warehouse_id'])) {
            return ['success' => false, 'message' => 'Selected warehouse does not exist.'];
        }

        return $this->save_document('kt_goods_receipts', 'kt_goods_receipt_items', 'receipt_id', [
            'receipt_code'   => $data['receipt_code'] ?: kt_inventory_generate_code('receipt'),
            'warehouse_id'   => (int) $data['warehouse_id'],
            'supplier_name'  => trim((string) ($data['supplier_name'] ?? '')),
            'receipt_date'   => $data['receipt_date'],
            'status'         => 'draft',
            'note'           => trim((string) ($data['note'] ?? '')),
            'created_by'     => get_staff_user_id(),
        ], $lines, $id, function ($line) {
            $batch_id = 0;
            if (!empty($line['lot_number'])) {
                $batch_id = $this->get_or_create_batch((int) $line['inventory_item_id'], $line['lot_number'], $line['expiry_date']);
            }
            return [
                'inventory_item_id' => (int) $line['inventory_item_id'],
                'batch_id'          => $batch_id,
                'barcode_id'        => !empty($line['barcode_id']) ? (int) $line['barcode_id'] : null,
                'scanned_barcode'   => trim((string) ($line['scanned_barcode'] ?? '')),
                'quantity'          => kt_inventory_normalize_decimal($line['quantity']),
                'unit_cost'         => kt_inventory_normalize_decimal($line['unit_cost']),
                'lot_number'        => trim((string) ($line['lot_number'] ?? '')),
                'serial_number'     => trim((string) ($line['serial_number'] ?? '')),
                'expiry_date'       => !empty($line['expiry_date']) ? $line['expiry_date'] : null,
                'note'              => trim((string) ($line['note'] ?? '')),
            ];
        });
    }

    public function post_goods_receipt($id)
    {
        $document = $this->get_goods_receipt($id);
        if (!$document || $document['header']['status'] !== 'draft') {
            return false;
        }

        $this->db->trans_begin();
        foreach ($document['items'] as $line) {
            $qty = (float) $line['quantity'];
            if ($qty <= 0) {
                $this->db->trans_rollback();
                return false;
            }

            $batchId = (int) ($line['batch_id'] ?? 0);
            $balance = $this->touch_balance((int) $document['header']['warehouse_id'], (int) $line['inventory_item_id'], $batchId);
            $before = (float) $balance['quantity'];
            $after = $before + $qty;
            $this->update_balance((int) $balance['id'], $after, (float) $balance['reserved_quantity']);
            $this->insert_stock_transaction('receipt', 'goods_receipt', $id, (int) $document['header']['warehouse_id'], (int) $line['inventory_item_id'], $before, $qty, $after, $document['header']['note'], $batchId);
        }

        $this->set_document_status('kt_goods_receipts', $id, 'posted');
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        log_activity('KT Inventory: posted goods receipt #' . $id);
        return true;
    }

    public function cancel_goods_receipt($id)
    {
        return $this->cancel_document('kt_goods_receipts', $id, 'KT Inventory: cancelled goods receipt #' . $id);
    }

    public function get_goods_issues()
    {
        return $this->get_document_list('kt_goods_issues', 'issue_date', 'issue_code');
    }

    public function get_goods_issue($id)
    {
        return $this->get_document('kt_goods_issues', 'kt_goods_issue_items', 'issue_id', $id);
    }

    public function save_goods_issue($data, $lines, $id = null)
    {
        if (empty($data['warehouse_id']) || empty($data['issue_date'])) {
            return ['success' => false, 'message' => 'Warehouse and issue date are required.'];
        }
        if (!$this->warehouse_exists((int) $data['warehouse_id'])) {
            return ['success' => false, 'message' => 'Selected warehouse does not exist.'];
        }
        if (!empty($data['customer_id']) && !$this->record_exists('clients', 'userid', (int) $data['customer_id'])) {
            return ['success' => false, 'message' => 'Selected customer does not exist.'];
        }
        if (!empty($data['invoice_id']) && !$this->record_exists('invoices', 'id', (int) $data['invoice_id'])) {
            return ['success' => false, 'message' => 'Selected invoice does not exist.'];
        }

        return $this->save_document('kt_goods_issues', 'kt_goods_issue_items', 'issue_id', [
            'issue_code'   => $data['issue_code'] ?: kt_inventory_generate_code('issue'),
            'warehouse_id' => (int) $data['warehouse_id'],
            'customer_id'  => $data['customer_id'] !== '' ? (int) $data['customer_id'] : null,
            'invoice_id'   => $data['invoice_id'] !== '' ? (int) $data['invoice_id'] : null,
            'issue_date'   => $data['issue_date'],
            'status'       => 'draft',
            'note'         => trim((string) ($data['note'] ?? '')),
            'created_by'   => get_staff_user_id(),
        ], $lines, $id, function ($line) {
            $batch_id = !empty($line['batch_id']) ? (int) $line['batch_id'] : 0;
            $lot_number = '';
            $expiry_date = null;
            if ($batch_id > 0) {
                $batch = $this->db->where('id', $batch_id)->get(db_prefix() . 'kt_inventory_batches')->row_array();
                if ($batch) {
                    $lot_number = $batch['lot_number'];
                    $expiry_date = $batch['expiry_date'];
                }
            }
            return [
                'inventory_item_id' => (int) $line['inventory_item_id'],
                'batch_id'          => $batch_id,
                'barcode_id'        => !empty($line['barcode_id']) ? (int) $line['barcode_id'] : null,
                'scanned_barcode'   => trim((string) ($line['scanned_barcode'] ?? '')),
                'quantity'          => kt_inventory_normalize_decimal($line['quantity']),
                'lot_number'        => $lot_number,
                'serial_number'     => trim((string) ($line['serial_number'] ?? '')),
                'expiry_date'       => $expiry_date,
                'note'              => trim((string) ($line['note'] ?? '')),
            ];
        });
    }

    public function post_goods_issue($id)
    {
        $document = $this->get_goods_issue($id);
        if (!$document || $document['header']['status'] !== 'draft') {
            return false;
        }

        $allowNegative = kt_inventory_get_option('kt_inventory_allow_negative_stock', '0') === '1';

        $this->db->trans_begin();
        foreach ($document['items'] as $line) {
            $qty = (float) $line['quantity'];
            if ($qty <= 0) {
                $this->db->trans_rollback();
                return false;
            }

            $batchId = (int) ($line['batch_id'] ?? 0);
            if ($batchId > 0) {
                $batch = $this->db->where('id', $batchId)->get(db_prefix() . 'kt_inventory_batches')->row_array();
                if ($batch && $batch['qc_status'] !== 'released') {
                    $this->db->trans_rollback();
                    return ['error' => 'batch_not_released', 'lot_number' => $batch['lot_number']];
                }
            }

            $balance = $this->touch_balance((int) $document['header']['warehouse_id'], (int) $line['inventory_item_id'], $batchId);
            $before = (float) $balance['quantity'];
            $available = (float) $balance['available_quantity'];

            if (!$allowNegative && $available < $qty) {
                $this->db->trans_rollback();
                return ['error' => 'negative_stock'];
            }

            $after = $before - $qty;
            $consumedReserved = $this->consume_reservations(
                (int) $document['header']['warehouse_id'],
                (int) $line['inventory_item_id'],
                $qty,
                'goods_issue',
                $id,
                !empty($document['header']['invoice_id']) ? (int) $document['header']['invoice_id'] : null
            );
            $remainingReserved = max(0, (float) $balance['reserved_quantity'] - $consumedReserved);
            $this->update_balance((int) $balance['id'], $after, $remainingReserved);
            $this->insert_stock_transaction('issue', 'goods_issue', $id, (int) $document['header']['warehouse_id'], (int) $line['inventory_item_id'], $before, -1 * $qty, $after, $document['header']['note'], $batchId);
        }

        $this->set_document_status('kt_goods_issues', $id, 'posted');
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        log_activity('KT Inventory: posted goods issue #' . $id);
        return true;
    }

    public function cancel_goods_issue($id)
    {
        return $this->cancel_document('kt_goods_issues', $id, 'KT Inventory: cancelled goods issue #' . $id);
    }

    public function get_stock_adjustments()
    {
        return $this->get_document_list('kt_stock_adjustments', 'adjustment_date', 'adjustment_code');
    }

    public function get_stock_adjustment($id)
    {
        return $this->get_document('kt_stock_adjustments', 'kt_stock_adjustment_items', 'adjustment_id', $id);
    }

    public function save_stock_adjustment($data, $lines, $id = null)
    {
        if (empty($data['warehouse_id']) || empty($data['adjustment_date']) || trim((string) ($data['reason'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Warehouse, date and reason are required.'];
        }
        if (!$this->warehouse_exists((int) $data['warehouse_id'])) {
            return ['success' => false, 'message' => 'Selected warehouse does not exist.'];
        }

        return $this->save_document('kt_stock_adjustments', 'kt_stock_adjustment_items', 'adjustment_id', [
            'adjustment_code' => $data['adjustment_code'] ?: kt_inventory_generate_code('adjustment'),
            'warehouse_id'    => (int) $data['warehouse_id'],
            'adjustment_date' => $data['adjustment_date'],
            'reason'          => trim((string) ($data['reason'] ?? '')),
            'status'          => 'draft',
            'created_by'      => get_staff_user_id(),
        ], $lines, $id, function ($line) {
            $batch_id = !empty($line['batch_id']) ? (int) $line['batch_id'] : 0;
            $lot_number = '';
            $expiry_date = null;
            if ($batch_id > 0) {
                $batch = $this->db->where('id', $batch_id)->get(db_prefix() . 'kt_inventory_batches')->row_array();
                if ($batch) {
                    $lot_number = $batch['lot_number'];
                    $expiry_date = $batch['expiry_date'];
                }
            }
            $old = kt_inventory_normalize_decimal($line['old_quantity']);
            $new = kt_inventory_normalize_decimal($line['new_quantity']);
            return [
                'inventory_item_id'   => (int) $line['inventory_item_id'],
                'batch_id'            => $batch_id,
                'barcode_id'          => !empty($line['barcode_id']) ? (int) $line['barcode_id'] : null,
                'scanned_barcode'     => trim((string) ($line['scanned_barcode'] ?? '')),
                'old_quantity'        => $old,
                'new_quantity'        => $new,
                'difference_quantity' => $new - $old,
                'lot_number'          => $lot_number,
                'serial_number'       => trim((string) ($line['serial_number'] ?? '')),
                'expiry_date'         => $expiry_date,
                'note'                => trim((string) ($line['note'] ?? '')),
            ];
        });
    }

    public function post_stock_adjustment($id)
    {
        $document = $this->get_stock_adjustment($id);
        if (!$document || $document['header']['status'] !== 'draft') {
            return false;
        }

        $this->db->trans_begin();
        foreach ($document['items'] as $line) {
            $batchId = (int) ($line['batch_id'] ?? 0);
            $balance = $this->touch_balance((int) $document['header']['warehouse_id'], (int) $line['inventory_item_id'], $batchId);
            $before = (float) $balance['quantity'];
            $after = (float) $line['new_quantity'];
            $change = $after - $before;
            if ((float) $balance['reserved_quantity'] > $after) {
                $this->db->trans_rollback();
                return ['error' => 'reserved_conflict'];
            }

            $this->update_balance((int) $balance['id'], $after, (float) $balance['reserved_quantity']);
            $this->insert_stock_transaction('adjustment', 'stock_adjustment', $id, (int) $document['header']['warehouse_id'], (int) $line['inventory_item_id'], $before, $change, $after, $document['header']['reason'], $batchId);
        }

        $this->set_document_status('kt_stock_adjustments', $id, 'posted');
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        log_activity('KT Inventory: posted stock adjustment #' . $id);
        return true;
    }

    public function cancel_stock_adjustment($id)
    {
        return $this->cancel_document('kt_stock_adjustments', $id, 'KT Inventory: cancelled stock adjustment #' . $id);
    }

    public function get_stock_transfers()
    {
        return $this->get_document_list('kt_stock_transfers', 'transfer_date', 'transfer_code', null, true);
    }

    public function get_stock_transfer($id)
    {
        return $this->get_document('kt_stock_transfers', 'kt_stock_transfer_items', 'transfer_id', $id);
    }

    public function save_stock_transfer($data, $lines, $id = null)
    {
        if (empty($data['from_warehouse_id']) || empty($data['to_warehouse_id']) || empty($data['transfer_date'])) {
            return ['success' => false, 'message' => 'Source warehouse, destination warehouse and transfer date are required.'];
        }
        if (!$this->warehouse_exists((int) $data['from_warehouse_id']) || !$this->warehouse_exists((int) $data['to_warehouse_id'])) {
            return ['success' => false, 'message' => 'Selected warehouse does not exist.'];
        }

        if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
            return ['success' => false, 'message' => 'Source and destination warehouses must be different.'];
        }

        return $this->save_document('kt_stock_transfers', 'kt_stock_transfer_items', 'transfer_id', [
            'transfer_code'      => $data['transfer_code'] ?: kt_inventory_generate_code('transfer'),
            'from_warehouse_id'  => (int) $data['from_warehouse_id'],
            'to_warehouse_id'    => (int) $data['to_warehouse_id'],
            'transfer_date'      => $data['transfer_date'],
            'status'             => 'draft',
            'note'               => trim((string) ($data['note'] ?? '')),
            'created_by'         => get_staff_user_id(),
        ], $lines, $id, function ($line) {
            $batch_id = !empty($line['batch_id']) ? (int) $line['batch_id'] : 0;
            $lot_number = '';
            $expiry_date = null;
            if ($batch_id > 0) {
                $batch = $this->db->where('id', $batch_id)->get(db_prefix() . 'kt_inventory_batches')->row_array();
                if ($batch) {
                    $lot_number = $batch['lot_number'];
                    $expiry_date = $batch['expiry_date'];
                }
            }
            return [
                'inventory_item_id' => (int) $line['inventory_item_id'],
                'batch_id'          => $batch_id,
                'barcode_id'        => !empty($line['barcode_id']) ? (int) $line['barcode_id'] : null,
                'scanned_barcode'   => trim((string) ($line['scanned_barcode'] ?? '')),
                'quantity'          => kt_inventory_normalize_decimal($line['quantity']),
                'lot_number'        => $lot_number,
                'serial_number'     => trim((string) ($line['serial_number'] ?? '')),
                'expiry_date'       => $expiry_date,
                'note'              => trim((string) ($line['note'] ?? '')),
            ];
        });
    }

    public function post_stock_transfer($id)
    {
        $document = $this->get_stock_transfer($id);
        if (!$document || $document['header']['status'] !== 'draft') {
            return false;
        }

        if ((int) $document['header']['from_warehouse_id'] === (int) $document['header']['to_warehouse_id']) {
            return ['error' => 'same_warehouse'];
        }

        $allowNegative = kt_inventory_get_option('kt_inventory_allow_negative_stock', '0') === '1';

        $this->db->trans_begin();
        foreach ($document['items'] as $line) {
            $qty = (float) $line['quantity'];
            if ($qty <= 0) {
                $this->db->trans_rollback();
                return false;
            }

            $batchId = (int) ($line['batch_id'] ?? 0);
            if ($batchId > 0) {
                $batch = $this->db->where('id', $batchId)->get(db_prefix() . 'kt_inventory_batches')->row_array();
                if ($batch && $batch['qc_status'] !== 'released') {
                    $this->db->trans_rollback();
                    return ['error' => 'batch_not_released', 'lot_number' => $batch['lot_number']];
                }
            }

            $source = $this->touch_balance((int) $document['header']['from_warehouse_id'], (int) $line['inventory_item_id'], $batchId);
            $target = $this->touch_balance((int) $document['header']['to_warehouse_id'], (int) $line['inventory_item_id'], $batchId);
            $sourceBefore = (float) $source['quantity'];
            $sourceAfter = $sourceBefore - $qty;

            if (!$allowNegative && (float) $source['available_quantity'] < $qty) {
                $this->db->trans_rollback();
                return ['error' => 'negative_stock'];
            }
            if ((float) $source['reserved_quantity'] > $sourceAfter) {
                $this->db->trans_rollback();
                return ['error' => 'reserved_conflict'];
            }

            $targetBefore = (float) $target['quantity'];
            $targetAfter = $targetBefore + $qty;

            $this->update_balance((int) $source['id'], $sourceAfter, (float) $source['reserved_quantity']);
            $this->update_balance((int) $target['id'], $targetAfter, (float) $target['reserved_quantity']);

            $this->insert_stock_transaction('transfer_out', 'stock_transfer', $id, (int) $document['header']['from_warehouse_id'], (int) $line['inventory_item_id'], $sourceBefore, -1 * $qty, $sourceAfter, $document['header']['note'], $batchId);
            $this->insert_stock_transaction('transfer_in', 'stock_transfer', $id, (int) $document['header']['to_warehouse_id'], (int) $line['inventory_item_id'], $targetBefore, $qty, $targetAfter, $document['header']['note'], $batchId);
        }

        $this->set_document_status('kt_stock_transfers', $id, 'posted');
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        log_activity('KT Inventory: posted stock transfer #' . $id);
        return true;
    }

    public function cancel_stock_transfer($id)
    {
        return $this->cancel_document('kt_stock_transfers', $id, 'KT Inventory: cancelled stock transfer #' . $id);
    }

    public function get_customers()
    {
        return $this->db->select('userid, company')->order_by('company', 'asc')->get(db_prefix() . 'clients')->result_array();
    }

    public function get_invoices()
    {
        return $this->db
            ->select('tblinvoices.id, tblinvoices.number, tblinvoices.clientid, tblclients.company')
            ->from(db_prefix() . 'invoices')
            ->join(db_prefix() . 'clients', 'tblclients.userid = tblinvoices.clientid', 'left')
            ->order_by('tblinvoices.id', 'desc')
            ->limit(200)
            ->get()
            ->result_array();
    }

    public function get_core_items()
    {
        return $this->db->select('id, description, long_description')->order_by('description', 'asc')->get(db_prefix() . 'items')->result_array();
    }

    public function import_items_from_csv($rows)
    {
        if (empty($rows)) {
            return ['success' => false, 'message' => 'No rows found in CSV file.'];
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        $this->db->trans_begin();
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($sku === '' || $name === '') {
                $errors[] = 'Row ' . $rowNumber . ': SKU and name are required.';
                continue;
            }

            $item_id = ($row['item_id'] ?? '') !== '' ? (int) $row['item_id'] : null;
            if (empty($item_id)) {
                // Auto create core item
                if (!class_exists('Invoice_items_model', false)) {
                    $this->load->model('invoice_items_model');
                }
                $item_id = $this->invoice_items_model->add([
                    'description'      => $name,
                    'long_description' => '',
                    'rate'             => 0.00,
                    'unit'             => trim((string) ($row['unit'] ?? '')),
                ]);
            }

            if (empty($item_id)) {
                $errors[] = 'Row ' . $rowNumber . ': linked item could not be resolved.';
                continue;
            }

            $payload = [
                'item_id'      => $item_id,
                'sku'          => $sku,
                'track_lot'    => $this->csvBoolean($row['track_lot'] ?? ''),
                'track_serial' => $this->csvBoolean($row['track_serial'] ?? ''),
                'min_stock'    => kt_inventory_normalize_decimal($row['min_stock'] ?? 0),
                'max_stock'    => kt_inventory_normalize_decimal($row['max_stock'] ?? 0),
                'is_active'    => array_key_exists('is_active', $row) ? $this->csvBoolean($row['is_active']) : 1,
                'updated_at'   => date('Y-m-d H:i:s'),
            ];

            if ($payload['track_lot'] && $payload['track_serial']) {
                $errors[] = 'Row ' . $rowNumber . ': track_lot and track_serial cannot both be enabled.';
                continue;
            }

            if ($payload['max_stock'] > 0 && $payload['max_stock'] < $payload['min_stock']) {
                $errors[] = 'Row ' . $rowNumber . ': max_stock must be greater than or equal to min_stock.';
                continue;
            }

            if (!$this->record_exists('items', 'id', $item_id)) {
                $errors[] = 'Row ' . $rowNumber . ': linked item does not exist.';
                continue;
            }

            $existing = $this->db->where('item_id', $item_id)->get(db_prefix() . 'kt_inventory_items')->row_array();
            if ($existing) {
                $this->db->where('id', $existing['id'])->update(db_prefix() . 'kt_inventory_items', $payload);
                $updated++;
                $itemId = (int) $existing['id'];
            } else {
                $payload['created_at'] = $payload['updated_at'];
                $this->db->insert(db_prefix() . 'kt_inventory_items', $payload);
                $created++;
                $itemId = (int) $this->db->insert_id();
            }

            $barcode = trim((string) ($row['barcode'] ?? ''));
            if ($barcode !== '') {
                $barcodeType = trim((string) ($row['barcode_type'] ?? 'internal'));
                $source = trim((string) ($row['source'] ?? 'imported'));
                $barcodeExisting = $this->db->where('barcode', $barcode)->get(db_prefix() . 'kt_item_barcodes')->row_array();
                if ($barcodeExisting && (int) $barcodeExisting['inventory_item_id'] !== $itemId) {
                    $errors[] = 'Row ' . $rowNumber . ': barcode already belongs to another item.';
                    continue;
                }

                $barcodePayload = [
                    'inventory_item_id' => $itemId,
                    'barcode'           => $barcode,
                    'barcode_type'      => $barcodeType,
                    'unit_type'         => trim((string) ($row['unit_type'] ?? ($row['unit'] ?? ''))),
                    'package_quantity'  => max(kt_inventory_normalize_decimal($row['package_quantity'] ?? 1), 1),
                    'is_primary'        => $this->csvBoolean($row['is_primary'] ?? 0),
                    'source'            => $source !== '' ? $source : 'imported',
                    'is_active'         => 1,
                ];

                if ($barcodeExisting) {
                    $barcodeResult = $this->update_item_barcode((int) $barcodeExisting['id'], $barcodePayload);
                } else {
                    $barcodeResult = $this->add_item_barcode($barcodePayload);
                }

                if (empty($barcodeResult['success'])) {
                    $errors[] = 'Row ' . $rowNumber . ': ' . $barcodeResult['message'];
                }
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['success' => false, 'message' => 'Unable to import items from CSV.'];
        }

        $this->db->trans_commit();
        $message = 'Items import finished. Created: ' . $created . ', Updated: ' . $updated . '.';
        if (!empty($errors)) {
            $message .= ' Skipped: ' . count($errors) . '. ' . implode(' | ', array_slice($errors, 0, 5));
        }

        log_activity('KT Inventory: imported items from CSV. Created=' . $created . ', Updated=' . $updated . ', Skipped=' . count($errors));
        return ['success' => true, 'message' => $message];
    }

    public function import_stock_balances_from_csv($rows)
    {
        if (empty($rows)) {
            return ['success' => false, 'message' => 'No rows found in CSV file.'];
        }

        $updated = 0;
        $errors = [];

        $this->db->trans_begin();
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $warehouseCode = trim((string) ($row['warehouse_code'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $quantity = kt_inventory_normalize_decimal($row['quantity'] ?? 0);
            $reserved = kt_inventory_normalize_decimal($row['reserved_quantity'] ?? 0);

            if ($warehouseCode === '' || $sku === '') {
                $errors[] = 'Row ' . $rowNumber . ': warehouse_code and sku are required.';
                continue;
            }

            if ($reserved < 0 || $quantity < 0 || $reserved > $quantity) {
                $errors[] = 'Row ' . $rowNumber . ': invalid quantity/reserved_quantity.';
                continue;
            }

            $warehouse = $this->db->where('warehouse_code', $warehouseCode)->get(db_prefix() . 'kt_warehouses')->row_array();
            $item = $this->db->where('sku', $sku)->get(db_prefix() . 'kt_inventory_items')->row_array();
            if (!$warehouse || !$item) {
                $errors[] = 'Row ' . $rowNumber . ': warehouse or item not found.';
                continue;
            }

            $balance = $this->touch_balance((int) $warehouse['id'], (int) $item['id']);
            $this->update_balance((int) $balance['id'], $quantity, $reserved);
            $updated++;
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['success' => false, 'message' => 'Unable to import stock balances from CSV.'];
        }

        $this->db->trans_commit();
        $message = 'Stock balance import finished. Updated: ' . $updated . '.';
        if (!empty($errors)) {
            $message .= ' Skipped: ' . count($errors) . '. ' . implode(' | ', array_slice($errors, 0, 5));
        }

        log_activity('KT Inventory: imported stock balances from CSV. Updated=' . $updated . ', Skipped=' . count($errors));
        return ['success' => true, 'message' => $message];
    }

    public function reserve_stock($data)
    {
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $itemId = (int) ($data['inventory_item_id'] ?? 0);
        $quantity = kt_inventory_normalize_decimal($data['quantity'] ?? 0);

        if (!$this->warehouse_exists($warehouseId) || !$this->inventory_item_exists($itemId) || $quantity <= 0) {
            return ['success' => false, 'message' => 'Warehouse, item and quantity are required.'];
        }

        if (!empty($data['customer_id']) && !$this->record_exists('clients', 'userid', (int) $data['customer_id'])) {
            return ['success' => false, 'message' => 'Selected customer does not exist.'];
        }
        if (!empty($data['invoice_id']) && !$this->record_exists('invoices', 'id', (int) $data['invoice_id'])) {
            return ['success' => false, 'message' => 'Selected invoice does not exist.'];
        }

        $balance = $this->touch_balance($warehouseId, $itemId);
        if ((float) $balance['available_quantity'] < $quantity) {
            return ['success' => false, 'message' => 'Not enough available stock to reserve.'];
        }

        $this->db->trans_begin();
        $this->db->insert(db_prefix() . 'kt_stock_reservations', [
            'warehouse_id'      => $warehouseId,
            'inventory_item_id' => $itemId,
            'reference_type'    => trim((string) ($data['reference_type'] ?? 'manual')),
            'reference_id'      => !empty($data['reference_id']) ? (int) $data['reference_id'] : null,
            'customer_id'       => !empty($data['customer_id']) ? (int) $data['customer_id'] : null,
            'invoice_id'        => !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null,
            'quantity'          => $quantity,
            'status'            => 'active',
            'note'              => trim((string) ($data['note'] ?? '')),
            'reserved_by'       => get_staff_user_id(),
            'created_at'        => date('Y-m-d H:i:s'),
            'released_at'       => null,
        ]);
        $this->sync_reserved_quantity($warehouseId, $itemId);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['success' => false, 'message' => 'Unable to create reservation.'];
        }

        $this->db->trans_commit();
        log_activity('KT Inventory: reserved stock for item #' . $itemId . ' in warehouse #' . $warehouseId);
        return ['success' => true];
    }

    public function release_reservation($reservationId)
    {
        $reservation = $this->db->where('id', $reservationId)->get(db_prefix() . 'kt_stock_reservations')->row_array();
        if (!$reservation || $reservation['status'] !== 'active') {
            return false;
        }

        $this->db->trans_begin();
        $this->db->where('id', $reservationId)->update(db_prefix() . 'kt_stock_reservations', [
            'status'      => 'released',
            'released_at' => date('Y-m-d H:i:s'),
        ]);
        $this->sync_reserved_quantity((int) $reservation['warehouse_id'], (int) $reservation['inventory_item_id']);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        log_activity('KT Inventory: released stock reservation #' . $reservationId);
        return true;
    }

    private function get_transaction_depth()
    {
        try {
            $reflector = new ReflectionClass($this->db);
            $property = $reflector->getProperty('_trans_depth');
            $property->setAccessible(true);
            return (int) $property->getValue($this->db);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function clear_invoice_reservations($invoiceId)
    {
        $reservations = $this->db->where('invoice_id', (int) $invoiceId)
                                 ->where('status', 'active')
                                 ->get(db_prefix() . 'kt_stock_reservations')
                                 ->result_array();
        if (empty($reservations)) {
            return;
        }

        $trans_depth = $this->get_transaction_depth();
        $use_trans = ($trans_depth === 0);

        if ($use_trans) {
            $this->db->trans_begin();
        }

        $this->db->where('invoice_id', (int) $invoiceId)
                 ->where('status', 'active')
                 ->delete(db_prefix() . 'kt_stock_reservations');

        foreach ($reservations as $r) {
            $this->sync_reserved_quantity((int) $r['warehouse_id'], (int) $r['inventory_item_id']);
        }

        if ($use_trans) {
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return;
            }
            $this->db->trans_commit();
        }
    }

    public function auto_reserve_from_invoice($invoiceId)
    {
        $CI = &get_instance();
        $CI->load->helper('invoices');
        $CI->load->helper('sales');

        if (!class_exists('Invoices_model', false)) {
            $CI->load->model('invoices_model');
        }
        $invoice = $CI->invoices_model->get($invoiceId);

        if (!$invoice) {
            return;
        }

        // If invoice is cancelled (5) or draft (6), clear reservations and return
        if (in_array((int) $invoice->status, [5, 6])) {
            $this->clear_invoice_reservations($invoiceId);
            return;
        }

        $warehouseId = (int) kt_inventory_get_option('kt_inventory_default_warehouse_id');
        if ($warehouseId <= 0) {
            return; // Default warehouse not configured
        }

        $this->db->trans_begin();

        // Clear old reservations first to avoid duplication
        $this->clear_invoice_reservations($invoiceId);

        foreach ($invoice->items as $item) {
            // Find core item by description (item name)
            $coreItem = $this->db->select('id')
                                 ->where('description', $item['description'])
                                 ->get(db_prefix() . 'items')
                                 ->row_array();
            if (!$coreItem) {
                continue;
            }

            // Find matching inventory item configuration
            $invItem = $this->db->select('id')
                                ->where('item_id', $coreItem['id'])
                                ->get(db_prefix() . 'kt_inventory_items')
                                ->row_array();
            if (!$invItem) {
                continue;
            }

            $quantity = (float) $item['qty'];
            if ($quantity <= 0) {
                continue;
            }

            // Check current stock balance
            $balance = $this->touch_balance($warehouseId, (int) $invItem['id']);
            $available = (float) $balance['available_quantity'];

            $allowNegative = kt_inventory_get_option('kt_inventory_allow_negative_stock', '0') === '1';
            $reserveQty = $quantity;
            if (!$allowNegative && $available < $quantity) {
                $reserveQty = max(0.0, $available);
            }

            if ($reserveQty > 0) {
                $this->db->insert(db_prefix() . 'kt_stock_reservations', [
                    'warehouse_id'      => $warehouseId,
                    'inventory_item_id' => (int) $invItem['id'],
                    'reference_type'    => 'invoice',
                    'reference_id'      => $invoiceId,
                    'customer_id'       => (int) $invoice->clientid,
                    'invoice_id'        => $invoiceId,
                    'quantity'          => $reserveQty,
                    'status'            => 'active',
                    'note'              => 'Auto reserved from Invoice #' . $invoice->number,
                    'reserved_by'       => get_staff_user_id() ?: 0,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);
                $this->sync_reserved_quantity($warehouseId, (int) $invItem['id']);
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return;
        }
        $this->db->trans_commit();
    }

    public function get_staff_members()
    {
        return $this->db->select('staffid, firstname, lastname')->order_by('firstname', 'asc')->get(db_prefix() . 'staff')->result_array();
    }

    public function save_settings($data)
    {
        update_option('kt_inventory_allow_negative_stock', isset($data['kt_inventory_allow_negative_stock']) ? '1' : '0');
        update_option('kt_inventory_default_warehouse_id', $data['kt_inventory_default_warehouse_id'] ?? '');
        update_option('kt_inventory_low_stock_notification_enabled', isset($data['kt_inventory_low_stock_notification_enabled']) ? '1' : '0');
        update_option('kt_inventory_code_prefix_receipt', trim($data['kt_inventory_code_prefix_receipt'] ?? 'RCP'));
        update_option('kt_inventory_code_prefix_issue', trim($data['kt_inventory_code_prefix_issue'] ?? 'ISS'));
        update_option('kt_inventory_code_prefix_adjustment', trim($data['kt_inventory_code_prefix_adjustment'] ?? 'ADJ'));
        update_option('kt_inventory_code_prefix_transfer', trim($data['kt_inventory_code_prefix_transfer'] ?? 'TRF'));
        update_option('kt_inventory_internal_barcode_prefix', trim($data['kt_inventory_internal_barcode_prefix'] ?? 'KTINV'));
        update_option('kt_inventory_internal_barcode_type', trim($data['kt_inventory_internal_barcode_type'] ?? 'code128'));
        update_option('kt_inventory_next_barcode_number', (string) max((int) ($data['kt_inventory_next_barcode_number'] ?? 1), 1));
    }

    public function migrate_from_legacy_warehouse()
    {
        $result = [
            'warehouses' => 0,
            'items'      => 0,
            'balances'   => 0,
        ];

        if ($this->db->table_exists(db_prefix() . 'warehouse')) {
            $legacyWarehouses = $this->db->get(db_prefix() . 'warehouse')->result_array();
            foreach ($legacyWarehouses as $legacy) {
                $code = trim((string) ($legacy['warehouse_code'] ?? ''));
                if ($code === '') {
                    $code = 'LEGACY-WH-' . (int) $legacy['warehouse_id'];
                }

                $exists = $this->db->where('warehouse_code', $code)->get(db_prefix() . 'kt_warehouses')->row_array();
                if ($exists) {
                    continue;
                }

                $this->db->insert(db_prefix() . 'kt_warehouses', [
                    'warehouse_code'   => $code,
                    'warehouse_name'   => trim((string) ($legacy['warehouse_name'] ?? 'Warehouse ' . $legacy['warehouse_id'])),
                    'address'          => trim((string) ($legacy['warehouse_address'] ?? '')),
                    'manager_staff_id' => null,
                    'is_active'        => 1,
                    'created_at'       => date('Y-m-d H:i:s'),
                    'updated_at'       => date('Y-m-d H:i:s'),
                ]);
                $result['warehouses']++;
            }
        }

        if ($this->db->table_exists(db_prefix() . 'items')) {
            $legacyItems = $this->db->get(db_prefix() . 'items')->result_array();
            foreach ($legacyItems as $legacy) {
                $exists = $this->db->where('item_id', (int) $legacy['id'])->get(db_prefix() . 'kt_inventory_items')->row_array();
                if ($exists) {
                    continue;
                }

                $sku = trim((string) ($legacy['commodity_code'] ?? ''));
                if ($sku === '') {
                    $sku = trim((string) ($legacy['sku_code'] ?? ''));
                }
                if ($sku === '') {
                    $sku = 'ITEM-' . (int) $legacy['id'];
                }

                // Ensure SKU uniqueness
                $basesku = $sku;
                $counter = 1;
                while ($this->db->where('sku', $sku)->count_all_results(db_prefix() . 'kt_inventory_items') > 0) {
                    $sku = $basesku . '-' . $counter;
                    $counter++;
                }

                $this->db->insert(db_prefix() . 'kt_inventory_items', [
                    'item_id'     => (int) $legacy['id'],
                    'sku'         => $sku,
                    'min_stock'   => 0,
                    'max_stock'   => 0,
                    'is_active'   => (int) ($legacy['active'] ?? 1) === 1 ? 1 : 0,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                $result['items']++;
            }
        }

        if ($this->db->table_exists(db_prefix() . 'inventory_manage')) {
            $legacyBalances = $this->db->get(db_prefix() . 'inventory_manage')->result_array();
            foreach ($legacyBalances as $legacy) {
                $mappedItem = $this->db->where('item_id', (int) $legacy['commodity_id'])->get(db_prefix() . 'kt_inventory_items')->row_array();
                $mappedWarehouse = $this->mapLegacyWarehouseId((int) $legacy['warehouse_id']);

                if (!$mappedItem || !$mappedWarehouse) {
                    continue;
                }

                $balance = $this->touch_balance((int) $mappedWarehouse['id'], (int) $mappedItem['id']);
                $quantity = kt_inventory_normalize_decimal($legacy['inventory_number'] ?? 0);
                $this->update_balance((int) $balance['id'], $quantity, 0);
                $result['balances']++;
            }
        }

        return $result;
    }

    public function get_movement_report($filters = [])
    {
        $this->db->select('i.sku, tblitems.description as name, 
            SUM(CASE WHEN t.transaction_type = "receipt" THEN t.quantity_change ELSE 0 END) as qty_in,
            SUM(CASE WHEN t.transaction_type = "issue" THEN ABS(t.quantity_change) ELSE 0 END) as qty_out,
            SUM(CASE WHEN t.transaction_type = "adjustment" THEN t.quantity_change ELSE 0 END) as qty_adjustment,
            SUM(CASE WHEN t.transaction_type = "transfer_in" THEN t.quantity_change ELSE 0 END) as qty_transfer_in,
            SUM(CASE WHEN t.transaction_type = "transfer_out" THEN ABS(t.quantity_change) ELSE 0 END) as qty_transfer_out', false);
        $this->db->from(db_prefix() . 'kt_stock_transactions t');
        $this->db->join(db_prefix() . 'kt_inventory_items i', 'i.id = t.inventory_item_id');
        $this->db->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner');

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('t.warehouse_id', (int) $filters['warehouse_id']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(t.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(t.created_at) <=', $filters['date_to']);
        }

        $this->db->group_by('t.inventory_item_id');
        $this->db->order_by('tblitems.description', 'asc');
        return $this->db->get()->result_array();
    }

    private function count_by_status($table, $status)
    {
        return $this->db->where('status', $status)->count_all_results(db_prefix() . $table);
    }

    private function csvBoolean($value)
    {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'yes', 'y', 'true', 'on'], true) ? 1 : 0;
    }

    private function validate_ean13_checksum($barcode)
    {
        $digits = array_map('intval', str_split($barcode));
        $checksum = array_pop($digits);
        $sum = 0;

        foreach ($digits as $index => $digit) {
            $sum += ($index % 2 === 0) ? $digit : ($digit * 3);
        }

        $calculated = (10 - ($sum % 10)) % 10;
        return $calculated === $checksum;
    }

    private function sync_legacy_primary_barcode($inventoryItemId)
    {
        $primary = $this->db
            ->where('inventory_item_id', (int) $inventoryItemId)
            ->where('is_primary', 1)
            ->where('is_active', 1)
            ->get(db_prefix() . 'kt_item_barcodes')
            ->row_array();

        $this->db->where('id', (int) $inventoryItemId)->update(db_prefix() . 'kt_inventory_items', [
            'barcode'    => $primary['barcode'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function warehouse_exists($warehouseId)
    {
        return $this->record_exists('kt_warehouses', 'id', $warehouseId);
    }

    private function inventory_item_exists($itemId)
    {
        return $this->record_exists('kt_inventory_items', 'id', $itemId);
    }

    private function record_exists($table, $key, $value)
    {
        if (!$value) {
            return false;
        }

        return $this->db->where($key, $value)->count_all_results(db_prefix() . $table) > 0;
    }

    private function mapLegacyWarehouseId($legacyWarehouseId)
    {
        if (!$this->db->table_exists(db_prefix() . 'warehouse')) {
            return null;
        }

        $legacy = $this->db->where('warehouse_id', $legacyWarehouseId)->get(db_prefix() . 'warehouse')->row_array();
        if (!$legacy) {
            return null;
        }

        $code = trim((string) ($legacy['warehouse_code'] ?? ''));
        if ($code === '') {
            $code = 'LEGACY-WH-' . (int) $legacyWarehouseId;
        }

        return $this->db->where('warehouse_code', $code)->get(db_prefix() . 'kt_warehouses')->row_array();
    }

    private function get_document_list($table, $dateColumn, $codeColumn, $extraNameColumn = null, $transfer = false)
    {
        $this->db->from(db_prefix() . $table . ' d');
        $this->db->select('d.*');

        if ($transfer) {
            $this->db->select('w1.warehouse_name as from_warehouse_name, w2.warehouse_name as to_warehouse_name');
            $this->db->join(db_prefix() . 'kt_warehouses w1', 'w1.id = d.from_warehouse_id', 'left');
            $this->db->join(db_prefix() . 'kt_warehouses w2', 'w2.id = d.to_warehouse_id', 'left');
        } else {
            $this->db->select('w.warehouse_name');
            $this->db->join(db_prefix() . 'kt_warehouses w', 'w.id = d.warehouse_id', 'left');
        }

        if ($extraNameColumn) {
            $this->db->select('d.' . $extraNameColumn);
        }

        $this->db->order_by('d.' . $dateColumn, 'desc');
        $this->db->order_by('d.' . $codeColumn, 'desc');

        return $this->db->get()->result_array();
    }

    private function get_document($table, $itemsTable, $fk, $id)
    {
        $header = $this->db->where('id', $id)->get(db_prefix() . $table)->row_array();
        if (!$header) {
            return null;
        }

        $items = $this->db->select('li.*, tblitems.description as item_name, i.sku, tblitems.unit as unit, i.track_lot, i.track_serial, pb.barcode as primary_barcode, b.barcode as linked_barcode, b.barcode_type as linked_barcode_type, b.unit_type as linked_unit_type, b.package_quantity as linked_package_quantity')
            ->from(db_prefix() . $itemsTable . ' li')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = li.inventory_item_id', 'left')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'left')
            ->join(db_prefix() . 'kt_item_barcodes b', 'b.id = li.barcode_id', 'left')
            ->join('(SELECT inventory_item_id, barcode FROM ' . db_prefix() . 'kt_item_barcodes WHERE is_primary = 1 AND is_active = 1) pb', 'pb.inventory_item_id = i.id', 'left')
            ->where('li.' . $fk, $id)
            ->get()
            ->result_array();

        return [
            'header' => $header,
            'items'  => $items,
        ];
    }

    private function save_document($table, $itemsTable, $fk, $headerPayload, $lines, $id, $lineFormatter)
    {
        $now = date('Y-m-d H:i:s');
        $cleanLines = [];

        foreach ($lines as $line) {
            if (empty($line['inventory_item_id'])) {
                continue;
            }

            if (!$this->inventory_item_exists((int) $line['inventory_item_id'])) {
                return ['success' => false, 'message' => 'Hàng hóa được chọn không tồn tại.'];
            }

            $cleanLines[] = $lineFormatter($line);
        }

        if (empty($cleanLines)) {
            return ['success' => false, 'message' => 'Cần có ít nhất một dòng hàng hóa.'];
        }

        foreach ($cleanLines as $line) {
            $item = $this->get_inventory_item((int) $line['inventory_item_id']);
            if (!$item || !(int) $item['is_active']) {
                return ['success' => false, 'message' => 'Hàng hóa được chọn đang ngừng hoạt động hoặc không khả dụng.'];
            }
            if (!empty($line['barcode_id'])) {
                $barcode = $this->get_barcode((int) $line['barcode_id']);
                if (!$barcode || !(int) $barcode['is_active'] || (int) $barcode['inventory_item_id'] !== (int) $line['inventory_item_id']) {
                    return ['success' => false, 'message' => 'Mã vạch được chọn không hợp lệ đối với hàng hóa này.'];
                }
            }
            if (isset($line['quantity']) && (float) $line['quantity'] <= 0) {
                return ['success' => false, 'message' => 'Số lượng phải lớn hơn 0.'];
            }
            if (!empty($item['track_lot']) && trim((string) ($line['lot_number'] ?? '')) === '') {
                return ['success' => false, 'message' => 'Vui lòng nhập số lô cho hàng hóa có theo dõi lô.'];
            }
            if (!empty($item['track_serial']) && trim((string) ($line['serial_number'] ?? '')) === '') {
                return ['success' => false, 'message' => 'Vui lòng nhập số serial cho hàng hóa có theo dõi serial.'];
            }
        }

        if ($id) {
            $current = $this->db->where('id', $id)->get(db_prefix() . $table)->row_array();
            if (!$current || $current['status'] !== 'draft') {
                return ['success' => false, 'message' => 'Only draft documents can be edited.'];
            }

            $headerPayload['created_by'] = $current['created_by'];
            $this->db->where('id', $id)->update(db_prefix() . $table, array_merge($headerPayload, ['created_at' => $current['created_at']]));
            $this->db->where($fk, $id)->delete(db_prefix() . $itemsTable);
        } else {
            $headerPayload['created_at'] = $now;
            $this->db->insert(db_prefix() . $table, $headerPayload);
            $id = $this->db->insert_id();
        }

        foreach ($cleanLines as $line) {
            $line[$fk] = $id;
            $this->db->insert(db_prefix() . $itemsTable, $line);
        }

        return ['success' => true, 'id' => $id];
    }

    private function cancel_document($table, $id, $logMessage)
    {
        $document = $this->db->where('id', $id)->get(db_prefix() . $table)->row_array();
        if (!$document || $document['status'] !== 'draft') {
            return false;
        }

        $this->set_document_status($table, $id, 'cancelled', false);
        log_activity($logMessage);
        return true;
    }

    private function set_document_status($table, $id, $status, $withPostedAt = true)
    {
        $payload = ['status' => $status];
        if ($withPostedAt) {
            $payload['posted_at'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', $id)->update(db_prefix() . $table, $payload);
    }

    private function touch_balance($warehouseId, $itemId, $batchId = 0)
    {
        $row = $this->db->query(
            "SELECT * FROM " . db_prefix() . "kt_stock_balances 
             WHERE warehouse_id = ? AND inventory_item_id = ? AND batch_id = ? 
             FOR UPDATE", 
            [(int) $warehouseId, (int) $itemId, (int) $batchId]
        )->row_array();

        if ($row) {
            return $row;
        }

        $insert = [
            'warehouse_id'       => (int) $warehouseId,
            'inventory_item_id'  => (int) $itemId,
            'batch_id'           => (int) $batchId,
            'quantity'           => 0.00,
            'reserved_quantity'  => 0.00,
            'available_quantity' => 0.00,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        // Prevent CI3 from halting on duplicate key by temporarily disabling db_debug
        $db_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        $inserted = $this->db->insert(db_prefix() . 'kt_stock_balances', $insert);

        $this->db->db_debug = $db_debug;

        if (!$inserted) {
            // Concurrency fallback: row must have been inserted by another thread, SELECT FOR UPDATE again
            $row = $this->db->query(
                "SELECT * FROM " . db_prefix() . "kt_stock_balances 
                 WHERE warehouse_id = ? AND inventory_item_id = ? AND batch_id = ? 
                 FOR UPDATE", 
                [(int) $warehouseId, (int) $itemId, (int) $batchId]
            )->row_array();
            if ($row) {
                return $row;
            }
        } else {
            $insert['id'] = $this->db->insert_id();
            return $insert;
        }

        return $insert;
    }

    private function update_balance($balanceId, $quantity, $reservedQuantity)
    {
        $available = $quantity - $reservedQuantity;
        $this->db->where('id', $balanceId)->update(db_prefix() . 'kt_stock_balances', [
            'quantity'           => $quantity,
            'reserved_quantity'  => $reservedQuantity,
            'available_quantity' => $available,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
    }

    private function sync_reserved_quantity($warehouseId, $itemId, $batchId = 0)
    {
        $balance = $this->touch_balance($warehouseId, $itemId, $batchId);
        $row = $this->db
            ->select_sum('quantity')
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $itemId)
            ->where('batch_id', $batchId)
            ->where('status', 'active')
            ->get(db_prefix() . 'kt_stock_reservations')
            ->row_array();
        $reservedQuantity = (float) ($row['quantity'] ?? 0);

        $this->update_balance((int) $balance['id'], (float) $balance['quantity'], $reservedQuantity);
    }

    private function consume_reservations($warehouseId, $itemId, $quantity, $referenceType, $referenceId, $invoiceId = null)
    {
        $remaining = $quantity;
        $consumed = 0;
        $processedReservationIds = [];

        if ($remaining <= 0) {
            return 0;
        }

        if ($invoiceId) {
            $invoiceReservations = $this->db
                ->where('warehouse_id', $warehouseId)
                ->where('inventory_item_id', $itemId)
                ->where('invoice_id', $invoiceId)
                ->where('status', 'active')
                ->order_by('id', 'asc')
                ->get(db_prefix() . 'kt_stock_reservations')
                ->result_array();

            foreach ($invoiceReservations as $reservation) {
                if ($remaining <= 0) {
                    break;
                }

                $processedReservationIds[] = (int) $reservation['id'];
                $applied = $this->apply_reservation_consumption($reservation, $remaining, $referenceType, $referenceId);
                $consumed += $applied;
                $remaining -= $applied;
            }
        }

        if ($remaining > 0) {
            $fallbackReservations = $this->db
                ->where('warehouse_id', $warehouseId)
                ->where('inventory_item_id', $itemId)
                ->where('status', 'active')
                ->order_by('id', 'asc')
                ->get(db_prefix() . 'kt_stock_reservations')
                ->result_array();

            foreach ($fallbackReservations as $reservation) {
                if ($remaining <= 0) {
                    break;
                }
                if (in_array((int) $reservation['id'], $processedReservationIds, true)) {
                    continue;
                }

                $applied = $this->apply_reservation_consumption($reservation, $remaining, $referenceType, $referenceId);
                $consumed += $applied;
                $remaining -= $applied;
            }
        }

        $this->sync_reserved_quantity($warehouseId, $itemId);
        return $consumed;
    }

    private function apply_reservation_consumption($reservation, $remaining, $referenceType, $referenceId)
    {
        if ($remaining <= 0 || $reservation['status'] !== 'active') {
            return 0;
        }

        $reservedQty = (float) $reservation['quantity'];
        $consumeQty = min($reservedQty, $remaining);

        if ($consumeQty <= 0) {
            return 0;
        }

        if ($consumeQty >= $reservedQty) {
            $this->db->where('id', $reservation['id'])->update(db_prefix() . 'kt_stock_reservations', [
                'status'         => 'fulfilled',
                'released_at'    => date('Y-m-d H:i:s'),
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
            ]);
            return $consumeQty;
        }

        $this->db->where('id', $reservation['id'])->update(db_prefix() . 'kt_stock_reservations', [
            'quantity' => $reservedQty - $consumeQty,
        ]);

        $fulfilledRow = [
            'warehouse_id'      => $reservation['warehouse_id'],
            'inventory_item_id' => $reservation['inventory_item_id'],
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'customer_id'       => $reservation['customer_id'],
            'invoice_id'        => $reservation['invoice_id'],
            'quantity'          => $consumeQty,
            'status'            => 'fulfilled',
            'note'              => $reservation['note'],
            'reserved_by'       => $reservation['reserved_by'],
            'created_at'        => $reservation['created_at'],
            'released_at'       => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'kt_stock_reservations', $fulfilledRow);

        return $consumeQty;
    }

    private function insert_stock_transaction($type, $referenceType, $referenceId, $warehouseId, $inventoryItemId, $before, $change, $after, $note, $batchId = 0)
    {
        $this->db->insert(db_prefix() . 'kt_stock_transactions', [
            'transaction_type' => $type,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'warehouse_id'     => $warehouseId,
            'inventory_item_id'=> $inventoryItemId,
            'batch_id'         => $batchId,
            'quantity_before'  => $before,
            'quantity_change'  => $change,
            'quantity_after'   => $after,
            'note'             => $note,
            'created_by'       => get_staff_user_id() ?: 1,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_or_create_batch($inventoryItemId, $lotNumber, $expiryDate = null, $manufacturingDate = null)
    {
        $lotNumber = trim((string) $lotNumber);
        if ($lotNumber === '') {
            return 0;
        }

        $row = $this->db
            ->where('inventory_item_id', $inventoryItemId)
            ->where('lot_number', $lotNumber)
            ->get(db_prefix() . 'kt_inventory_batches')
            ->row_array();

        if ($row) {
            if (empty($row['expiry_date']) && !empty($expiryDate)) {
                $this->db->where('id', $row['id'])->update(db_prefix() . 'kt_inventory_batches', [
                    'expiry_date' => $expiryDate,
                    'updated_at'  => date('Y-m-d H:i:s')
                ]);
            }
            return (int) $row['id'];
        }

        $payload = [
            'inventory_item_id'  => $inventoryItemId,
            'lot_number'         => $lotNumber,
            'expiry_date'        => !empty($expiryDate) ? $expiryDate : null,
            'manufacturing_date' => !empty($manufacturingDate) ? $manufacturingDate : null,
            'qc_status'          => 'released',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s')
        ];

        $this->db->insert(db_prefix() . 'kt_inventory_batches', $payload);
        return (int) $this->db->insert_id();
    }

    public function get_batches($filters = [])
    {
        $this->db->from(db_prefix() . 'kt_inventory_batches bt');
        $this->db->select('bt.*, i.sku, tblitems.description as item_name, tblitems.unit as unit');
        $this->db->join(db_prefix() . 'kt_inventory_items i', 'i.id = bt.inventory_item_id', 'inner');
        $this->db->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner');

        if (!empty($filters['inventory_item_id'])) {
            $this->db->where('bt.inventory_item_id', (int) $filters['inventory_item_id']);
        }
        if (!empty($filters['qc_status'])) {
            $this->db->where('bt.qc_status', $filters['qc_status']);
        }
        if (!empty($filters['keyword'])) {
            $this->db->group_start()
                ->like('bt.lot_number', $filters['keyword'])
                ->or_like('tblitems.description', $filters['keyword'])
                ->or_like('i.sku', $filters['keyword'])
            ->group_end();
        }

        $this->db->order_by('bt.expiry_date', 'asc');
        $this->db->order_by('bt.lot_number', 'asc');
        
        $batches = $this->db->get()->result_array();
        
        foreach ($batches as $key => $batch) {
            $balances = $this->db->select_sum('quantity')
                                 ->where('inventory_item_id', $batch['inventory_item_id'])
                                 ->where('batch_id', $batch['id'])
                                 ->get(db_prefix() . 'kt_stock_balances')
                                 ->row_array();
            $batches[$key]['total_quantity'] = (float) ($balances['quantity'] ?? 0);
        }
        
        return $batches;
    }

    public function get_batch($id)
    {
        return $this->db->select('bt.*, i.sku, tblitems.description as item_name, tblitems.unit as unit')
            ->from(db_prefix() . 'kt_inventory_batches bt')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = bt.inventory_item_id', 'inner')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner')
            ->where('bt.id', $id)
            ->get()
            ->row_array();
    }

    public function save_batch($data, $id)
    {
        $payload = [
            'qc_status'          => $data['qc_status'],
            'expiry_date'        => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
            'manufacturing_date' => !empty($data['manufacturing_date']) ? $data['manufacturing_date'] : null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];
        
        $this->db->where('id', $id)->update(db_prefix() . 'kt_inventory_batches', $payload);
        return $this->db->affected_rows() > 0;
    }

    public function set_batch_qc_status($id, $qcStatus)
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'kt_inventory_batches', [
            'qc_status'  => trim((string) $qcStatus),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->affected_rows() >= 0;
    }

    public function get_batches_by_item($itemId, $warehouseId = null)
    {
        $this->db->from(db_prefix() . 'kt_inventory_batches bt');
        $this->db->select('bt.*, b.warehouse_id, b.quantity as stock_qty, b.available_quantity as available_qty');
        $this->db->join(db_prefix() . 'kt_stock_balances b', 'b.batch_id = bt.id AND b.inventory_item_id = bt.inventory_item_id', 'left');
        $this->db->where('bt.inventory_item_id', (int) $itemId);
        
        if ($warehouseId) {
            $this->db->where('b.warehouse_id', (int) $warehouseId);
        }
        
        $this->db->order_by('bt.expiry_date', 'asc');
        return $this->db->get()->result_array();
    }

    public function get_expiry_alerts($filters = [])
    {
        $this->db->from(db_prefix() . 'kt_inventory_batches bt');
        $this->db->select('bt.*, sb.warehouse_id, w.warehouse_name, sb.quantity as available_qty, i.sku, tblitems.description as item_name, DATEDIFF(bt.expiry_date, CURRENT_DATE()) as days_left', false);
        $this->db->join(db_prefix() . 'kt_stock_balances sb', 'sb.batch_id = bt.id AND sb.inventory_item_id = bt.inventory_item_id', 'inner');
        $this->db->join(db_prefix() . 'kt_warehouses w', 'w.id = sb.warehouse_id', 'inner');
        $this->db->join(db_prefix() . 'kt_inventory_items i', 'i.id = bt.inventory_item_id', 'inner');
        $this->db->join(db_prefix() . 'items', 'tblitems.id = i.item_id', 'inner');
        
        $this->db->where('sb.quantity >', 0);
        
        if (!empty($filters['warehouse_id'])) {
            $this->db->where('sb.warehouse_id', (int) $filters['warehouse_id']);
        }
        
        $this->db->where('bt.expiry_date <=', date('Y-m-d', strtotime('+6 months')));
        
        $this->db->order_by('bt.expiry_date', 'asc');
        return $this->db->get()->result_array();
    }

    public function get_recall_trace($lot_number)
    {
        $lot_number = trim($lot_number);
        if ($lot_number === '') {
            return null;
        }
        
        $receipts = $this->db->select('gr.receipt_code, gr.supplier_name, gr.receipt_date, w.warehouse_name, gri.quantity, gri.unit_cost, i.sku, tblitems.description as item_name')
            ->from(db_prefix() . 'kt_goods_receipt_items gri')
            ->join(db_prefix() . 'kt_goods_receipts gr', 'gr.id = gri.receipt_id')
            ->join(db_prefix() . 'kt_warehouses w', 'w.id = gr.warehouse_id')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = gri.inventory_item_id')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id')
            ->where('gri.lot_number', $lot_number)
            ->get()
            ->result_array();
            
        $issues = $this->db->select('gi.issue_code, c.company as customer_name, inv.number as invoice_number, gi.issue_date, w.warehouse_name, gii.quantity, i.sku, tblitems.description as item_name')
            ->from(db_prefix() . 'kt_goods_issue_items gii')
            ->join(db_prefix() . 'kt_goods_issues gi', 'gi.id = gii.issue_id')
            ->join(db_prefix() . 'kt_warehouses w', 'w.id = gi.warehouse_id')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = gii.inventory_item_id')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id')
            ->join(db_prefix() . 'clients c', 'c.userid = gi.customer_id', 'left')
            ->join(db_prefix() . 'invoices inv', 'inv.id = gi.invoice_id', 'left')
            ->where('gii.lot_number', $lot_number)
            ->get()
            ->result_array();
            
        $transfers = $this->db->select('st.transfer_code, st.transfer_date, w_from.warehouse_name as from_warehouse_name, w_to.warehouse_name as to_warehouse_name, sti.quantity, i.sku, tblitems.description as item_name')
            ->from(db_prefix() . 'kt_stock_transfer_items sti')
            ->join(db_prefix() . 'kt_stock_transfers st', 'st.id = sti.transfer_id')
            ->join(db_prefix() . 'kt_warehouses w_from', 'w_from.id = st.from_warehouse_id')
            ->join(db_prefix() . 'kt_warehouses w_to', 'w_to.id = st.to_warehouse_id')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = sti.inventory_item_id')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id')
            ->where('sti.lot_number', $lot_number)
            ->get()
            ->result_array();
            
        $adjustments = $this->db->select('sa.adjustment_code, sa.adjustment_date, sa.reason, w.warehouse_name, sai.old_quantity, sai.new_quantity, sai.difference_quantity, i.sku, tblitems.description as item_name')
            ->from(db_prefix() . 'kt_stock_adjustment_items sai')
            ->join(db_prefix() . 'kt_stock_adjustments sa', 'sa.id = sai.adjustment_id')
            ->join(db_prefix() . 'kt_warehouses w', 'w.id = sa.warehouse_id')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = sai.inventory_item_id')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id')
            ->where('sai.lot_number', $lot_number)
            ->get()
            ->result_array();
            
        $balances = $this->db->select('w.warehouse_name, i.sku, tblitems.description as item_name, sb.quantity as stock_qty, bt.qc_status')
            ->from(db_prefix() . 'kt_stock_balances sb')
            ->join(db_prefix() . 'kt_inventory_batches bt', 'bt.id = sb.batch_id AND bt.inventory_item_id = sb.inventory_item_id')
            ->join(db_prefix() . 'kt_warehouses w', 'w.id = sb.warehouse_id')
            ->join(db_prefix() . 'kt_inventory_items i', 'i.id = sb.inventory_item_id')
            ->join(db_prefix() . 'items', 'tblitems.id = i.item_id')
            ->where('bt.lot_number', $lot_number)
            ->where('sb.quantity >', 0)
            ->get()
            ->result_array();
            
        return [
            'lot_number'  => $lot_number,
            'receipts'    => $receipts,
            'issues'      => $issues,
            'transfers'   => $transfers,
            'adjustments' => $adjustments,
            'balances'    => $balances,
        ];
    }
}
