<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Test_inventory extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        $this->load->model('kt_inventory/kt_inventory_model');
        $this->load->helper('kt_inventory/kt_inventory');
        require_once __DIR__ . '/../kt_inventory.php';
    }

    public function run()
    {
        echo "<h1>Running KT Inventory Integration Tests</h1>";

        $this->run_gs1_parser_tests();
        $this->run_phase2_tests();
        $this->run_phase3_reporting_tests();
        $this->run_concurrency_and_reservation_tests();

        echo "<h2>All KT Inventory Regression Tests Passed Successfully!</h2>";
    }



    private function run_gs1_parser_tests()
    {
        echo "<h3>1. Running GS1 Barcode Parser Tests</h3>";

        // Test string 1: Clean scanner output
        $scan1 = '01089350018000181726123110LOT999';
        $res1 = $this->kt_inventory_model->parse_gs1_barcode($scan1);
        
        $this->assert($res1['parsed'], "GS1 parse should succeed for standard format");
        $this->assert($res1['gtin'] === '08935001800018', "GTIN matches");
        $this->assert($res1['expiry'] === '2026-12-31', "Expiry date matches");
        $this->assert($res1['lot'] === 'LOT999', "Lot matches");

        // Test string 2: Visual parenthesized format
        $scan2 = '(01)08935001800018(17)261231(10)LOT999';
        $res2 = $this->kt_inventory_model->parse_gs1_barcode($scan2);
        
        $this->assert($res2['parsed'], "GS1 parse should succeed for parenthesized format");
        $this->assert($res2['gtin'] === '08935001800018', "GTIN matches");
        $this->assert($res2['expiry'] === '2026-12-31', "Expiry date matches");
        $this->assert($res2['lot'] === 'LOT999', "Lot matches");

        // Test string 3: Non-GS1 string
        $scan3 = 'EAN138935001800018';
        $res3 = $this->kt_inventory_model->parse_gs1_barcode($scan3);
        $this->assert(!$res3['parsed'], "GS1 parse should fail for invalid format");

        echo "Pass: GS1 Barcode Parser parsed formats correctly.<br>";
    }

    private function run_phase2_tests()
    {
        echo "<h3>2. Running Phase 2 Core Tests</h3>";

        $warehouse_id = $this->setup_test_warehouse();
        $ids = $this->setup_test_item();
        $core_item_id = $ids['core_item_id'];
        $item_id = $ids['item_id'];

        // Register Barcode for the item
        $barcode_str = '08935001800018';
        $this->kt_inventory_model->add_item_barcode([
            'inventory_item_id' => $item_id,
            'barcode' => $barcode_str,
            'barcode_type' => 'gs1',
            'unit_type' => 'box',
            'package_quantity' => 1,
            'is_primary' => 1,
            'is_active' => 1,
        ]);

        // Test GS1 barcode scanning lookup
        $scan = '01089350018000181726123110TLOT-PH3';
        $match = $this->kt_inventory_model->find_item_by_barcode($scan);
        
        $this->assert($match !== null, "GS1 scan lookup found the product");
        $this->assert($match['inventory_item_id'] == $item_id, "Linked product ID matches");
        $this->assert($match['lot_number'] === 'TLOT-PH3', "Parsed lot matches");
        $this->assert($match['expiry_date'] === '2026-12-31', "Parsed expiry matches");

        // Goods Receipt
        $lot_number = "LOT-REG-" . time();
        $expiry_date = date('Y-m-d', strtotime('+1 year'));
        $receipt_data = [
            'receipt_code' => 'TEST-GR-' . time(),
            'warehouse_id' => $warehouse_id,
            'supplier_name' => 'Supplier Phase 3',
            'receipt_date' => date('Y-m-d'),
        ];
        $receipt_lines = [
            [
                'inventory_item_id' => $item_id,
                'quantity' => 100,
                'unit_cost' => 15.0,
                'lot_number' => $lot_number,
                'expiry_date' => $expiry_date,
            ]
        ];

        $gr_res = $this->kt_inventory_model->save_goods_receipt($receipt_data, $receipt_lines);
        $this->assert($gr_res['success'], "Goods Receipt saved");
        $this->kt_inventory_model->post_goods_receipt($gr_res['id']);
        echo "Pass: Goods Receipt posted successfully.<br>";

        // Verify Batch
        $batch = $this->db->where('inventory_item_id', $item_id)->where('lot_number', $lot_number)->get(db_prefix() . 'kt_inventory_batches')->row_array();
        $this->assert($batch !== null, "Batch registered automatically");

        // Verify Balance
        $balance = $this->db->where('warehouse_id', $warehouse_id)->where('inventory_item_id', $item_id)->where('batch_id', $batch['id'])->get(db_prefix() . 'kt_stock_balances')->row_array();
        $this->assert($balance && (float)$balance['quantity'] === 100.0, "Stock updated by batch");

        // Test Quarantine block
        $this->db->where('id', $batch['id'])->update(db_prefix() . 'kt_inventory_batches', ['qc_status' => 'quarantine']);
        $issue_data = [
            'issue_code' => 'TEST-GI-' . time(),
            'warehouse_id' => $warehouse_id,
            'issue_date' => date('Y-m-d'),
        ];
        $issue_lines = [
            [
                'inventory_item_id' => $item_id,
                'batch_id' => $batch['id'],
                'quantity' => 10,
            ]
        ];
        $gi_res = $this->kt_inventory_model->save_goods_issue($issue_data, $issue_lines);
        $post_gi_res = $this->kt_inventory_model->post_goods_issue($gi_res['id']);
        $this->assert(is_array($post_gi_res) && $post_gi_res['error'] === 'batch_not_released', "Issue of quarantined batch blocked");

        $this->cleanup_test_data($warehouse_id, $item_id, $core_item_id);
    }

    private function run_phase3_reporting_tests()
    {
        echo "<h3>3. Running Phase 3 Reporting & FEFO Tests</h3>";

        $warehouse_id = $this->setup_test_warehouse();
        $ids = $this->setup_test_item();
        $core_item_id = $ids['core_item_id'];
        $item_id = $ids['item_id'];

        // Create two batches with different expiries (FEFO check)
        $lot_early = 'LOT-EARLY';
        $lot_late = 'LOT-LATE';
        $exp_early = date('Y-m-d', strtotime('+1 month'));
        $exp_late = date('Y-m-d', strtotime('+5 months'));

        $this->kt_inventory_model->save_goods_receipt([
            'receipt_code' => 'TEST-FEFO-1',
            'warehouse_id' => $warehouse_id,
            'receipt_date' => date('Y-m-d'),
        ], [
            [
                'inventory_item_id' => $item_id,
                'quantity' => 50,
                'unit_cost' => 10,
                'lot_number' => $lot_late,
                'expiry_date' => $exp_late,
            ]
        ]);
        $this->kt_inventory_model->save_goods_receipt([
            'receipt_code' => 'TEST-FEFO-2',
            'warehouse_id' => $warehouse_id,
            'receipt_date' => date('Y-m-d'),
        ], [
            [
                'inventory_item_id' => $item_id,
                'quantity' => 30,
                'unit_cost' => 10,
                'lot_number' => $lot_early,
                'expiry_date' => $exp_early,
            ]
        ]);

        // Post receipts to establish stock balances
        $grs = $this->db->like('receipt_code', 'TEST-FEFO-')->get(db_prefix() . 'kt_goods_receipts')->result_array();
        foreach ($grs as $gr) {
            $this->kt_inventory_model->post_goods_receipt($gr['id']);
        }

        // Test FEFO Sorting
        $batches = $this->kt_inventory_model->get_batches_by_item($item_id, $warehouse_id);
        $this->assert(count($batches) === 2, "Both batches loaded");
        $this->assert($batches[0]['lot_number'] === 'LOT-EARLY', "First suggested lot is earliest expiring (FEFO)");

        // Expiry warning report check
        $alerts = $this->kt_inventory_model->get_expiry_alerts(['warehouse_id' => $warehouse_id]);
        $this->assert(count($alerts) >= 2, "Expiring lots picked up in warning alert report");
        
        // Recall Trace check
        $trace = $this->kt_inventory_model->get_recall_trace('LOT-EARLY');
        $this->assert($trace !== null, "Recall trace generated");
        $this->assert(count($trace['receipts']) === 1, "Recall traces import source");
        $this->assert(count($trace['balances']) === 1, "Recall traces current storage position");

        $this->cleanup_test_data($warehouse_id, $item_id, $core_item_id);
        // delete extra goods receipts
        foreach ($grs as $gr) {
            $this->db->where('receipt_id', $gr['id'])->delete(db_prefix() . 'kt_goods_receipt_items');
            $this->db->where('id', $gr['id'])->delete(db_prefix() . 'kt_goods_receipts');
        }
    }

    private function run_concurrency_and_reservation_tests()
    {
        echo "<h3>4. Running Concurrency Locking & Auto-Reservation Tests</h3>";

        $warehouse_id = $this->setup_test_warehouse();
        echo "Warehouse ID: " . $warehouse_id . "<br>";
        
        $ids = $this->setup_test_item();
        $core_item_id = $ids['core_item_id'];
        $item_id = $ids['item_id'];
        echo "Item ID: " . $item_id . ", Core Item ID: " . $core_item_id . "<br>";
        
        $item_name = $this->db->select('description')->where('id', $core_item_id)->get(db_prefix() . 'items')->row()->description;
        echo "Item Name: " . $item_name . "<br>";

        // 1. Concurrency Locking compilation test
        // Manually insert stock balance first so available quantity is enough
        echo "Inserting manual stock balance...<br>";
        $this->db->insert(db_prefix() . 'kt_stock_balances', [
            'warehouse_id'       => $warehouse_id,
            'inventory_item_id'  => $item_id,
            'batch_id'           => 0,
            'quantity'           => 100.00,
            'reserved_quantity'  => 0.00,
            'available_quantity' => 100.00,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        echo "Calling reserve_stock inside transaction...<br>";
        $this->db->trans_begin();
        $res_status = $this->kt_inventory_model->reserve_stock([
            'warehouse_id' => $warehouse_id,
            'inventory_item_id' => $item_id,
            'quantity' => 5.00,
        ]);
        if (empty($res_status['success'])) {
            echo "FAILED: " . ($res_status['message'] ?? 'no message') . "<br>";
            print_r($this->db->error());
        }
        $this->assert(!empty($res_status['success']), "reserve_stock executes successfully and compiles FOR UPDATE select");
        $this->db->trans_commit();
        echo "Passed concurrency locking test<br>";

        // Release the test reservation so it doesn't skew balance checks
        echo "Releasing locking test reservations...<br>";
        $this->db->where('warehouse_id', $warehouse_id)->where('inventory_item_id', $item_id)->delete(db_prefix() . 'kt_stock_reservations');

        echo "Fetching balance row...<br>";
        $balance = $this->db->where('warehouse_id', $warehouse_id)->where('inventory_item_id', $item_id)->get(db_prefix() . 'kt_stock_balances')->row_array();
        echo "Balance ID: " . ($balance['id'] ?? 'none') . "<br>";

        // 2. Set default warehouse option
        echo "Setting default warehouse...<br>";
        $orig_default_wh = get_option('kt_inventory_default_warehouse_id');
        update_option('kt_inventory_default_warehouse_id', $warehouse_id);

        // Put some stock in balances
        echo "Updating balance stock quantities...<br>";
        $this->db->where('id', $balance['id'])->update(db_prefix() . 'kt_stock_balances', [
            'quantity' => 100.00,
            'reserved_quantity' => 0.00,
            'available_quantity' => 100.00,
        ]);

        // Insert mock invoice
        echo "Inserting mock invoice...<br>";
        $invoice_data = [
            'clientid' => 1,
            'number' => 9999,
            'status' => 1, // Unpaid
            'date' => date('Y-m-d'),
            'duedate' => date('Y-m-d', strtotime('+30 days')),
            'datecreated' => date('Y-m-d H:i:s'),
            'prefix' => 'INV-',
            'number_format' => 1,
            'hash' => md5(time()),
            'addedfrom' => 1,
            'total' => 150.00,
            'subtotal' => 150.00,
            'currency' => 1,
            'discount_type' => '',
            'include_shipping' => 0,
        ];
        $this->db->insert(db_prefix() . 'invoices', $invoice_data);
        $invoice_id = $this->db->insert_id();
        echo "Mock Invoice ID: " . $invoice_id . "<br>";

        // Insert itemable line
        echo "Inserting itemable line...<br>";
        $this->db->insert(db_prefix() . 'itemable', [
            'rel_id' => $invoice_id,
            'rel_type' => 'invoice',
            'description' => $item_name,
            'qty' => 10.00,
            'rate' => 15.00,
            'unit' => 'box',
        ]);
        $itemable_id = $this->db->insert_id();
        echo "Itemable ID: " . $itemable_id . "<br>";

        // Trigger Auto-Reservation Hook
        echo "Triggering kt_inventory_invoice_created_or_updated hook...<br>";
        kt_inventory_invoice_created_or_updated($invoice_id);
        echo "Hook triggered successfully<br>";

        // Check if reservation is created
        $res = $this->db->where('invoice_id', $invoice_id)
                        ->where('inventory_item_id', $item_id)
                        ->where('status', 'active')
                        ->get(db_prefix() . 'kt_stock_reservations')
                        ->row_array();
        $this->assert($res !== null, "Auto-reservation created for the invoice item");
        $this->assert((float)$res['quantity'] === 10.00, "Reserved quantity is 10");

        // Verify balance updated
        $bal = $this->db->where('id', $balance['id'])->get(db_prefix() . 'kt_stock_balances')->row_array();
        $this->assert((float)$bal['reserved_quantity'] === 10.00, "Stock balance reserved quantity updated to 10");
        $this->assert((float)$bal['available_quantity'] === 90.00, "Stock balance available quantity is 90");

        // Test Update Reservation
        $this->db->where('id', $itemable_id)->update(db_prefix() . 'itemable', ['qty' => 25.00]);
        kt_inventory_invoice_created_or_updated($invoice_id);

        $res2 = $this->db->where('invoice_id', $invoice_id)
                         ->where('inventory_item_id', $item_id)
                         ->where('status', 'active')
                         ->get(db_prefix() . 'kt_stock_reservations')
                         ->row_array();
        $this->assert($res2 !== null, "Auto-reservation still exists after update");
        $this->assert((float)$res2['quantity'] === 25.00, "Updated reserved quantity is 25");

        $bal2 = $this->db->where('id', $balance['id'])->get(db_prefix() . 'kt_stock_balances')->row_array();
        $this->assert((float)$bal2['reserved_quantity'] === 25.00, "Stock balance reserved quantity updated to 25");
        $this->assert((float)$bal2['available_quantity'] === 75.00, "Stock balance available quantity is 75");

        // Test Cancel Invoice
        kt_inventory_invoice_cancelled($invoice_id);
        $res3 = $this->db->where('invoice_id', $invoice_id)
                         ->where('inventory_item_id', $item_id)
                         ->where('status', 'active')
                         ->get(db_prefix() . 'kt_stock_reservations')
                         ->row_array();
        $this->assert($res3 === null, "Active reservations cleared after invoice cancelled");

        $bal3 = $this->db->where('id', $balance['id'])->get(db_prefix() . 'kt_stock_balances')->row_array();
        $this->assert((float)$bal3['reserved_quantity'] === 0.00, "Stock balance reserved quantity reset to 0");
        $this->assert((float)$bal3['available_quantity'] === 100.00, "Stock balance available quantity reset to 100");

        // Test Delete Invoice
        // Create reservation again
        kt_inventory_invoice_created_or_updated($invoice_id);
        $res4 = $this->db->where('invoice_id', $invoice_id)->get(db_prefix() . 'kt_stock_reservations')->row_array();
        $this->assert($res4 !== null, "Active reservation recreated");

        // Trigger Delete hook
        kt_inventory_invoice_deleted($invoice_id);
        $res5 = $this->db->where('invoice_id', $invoice_id)->get(db_prefix() . 'kt_stock_reservations')->row_array();
        $this->assert($res5 === null, "Active reservations cleared after invoice deleted");

        // Clean up mock invoice & settings
        $this->db->where('id', $invoice_id)->delete(db_prefix() . 'invoices');
        $this->db->where('rel_id', $invoice_id)->where('rel_type', 'invoice')->delete(db_prefix() . 'itemable');
        update_option('kt_inventory_default_warehouse_id', $orig_default_wh);

        $this->cleanup_test_data($warehouse_id, $item_id, $core_item_id);
        echo "Pass: Concurrency locking and auto-reservation lifecycle tests passed successfully.<br>";
    }

    private function assert($condition, $message)
    {
        if (!$condition) {
            die("Assertion Failed: " . $message);
        }
    }

    private function setup_test_warehouse()
    {
        $warehouse_code = 'TWH-' . time();
        $res = $this->kt_inventory_model->save_warehouse([
            'warehouse_code' => $warehouse_code,
            'warehouse_name' => 'Warehouse Phase 3',
            'is_active' => 1,
        ]);
        return $res['id'];
    }

    private function setup_test_item()
    {
        $this->db->insert(db_prefix() . 'items', [
            'description' => 'Test Item Phase 3 ' . time(),
            'rate' => 20.0,
            'unit' => 'box',
        ]);
        $core_item_id = $this->db->insert_id();

        $res = $this->kt_inventory_model->save_inventory_item([
            'item_id' => $core_item_id,
            'sku' => 'TSKU-' . time(),
            'track_lot' => 1,
            'is_active' => 1,
        ]);
        return [
            'core_item_id' => $core_item_id,
            'item_id' => $res['id'],
        ];
    }

    private function cleanup_test_data($warehouse_id, $item_id, $core_item_id)
    {
        $this->db->where('warehouse_id', $warehouse_id)->delete(db_prefix() . 'kt_stock_balances');
        $this->db->where('warehouse_id', $warehouse_id)->delete(db_prefix() . 'kt_stock_transactions');
        
        $this->db->where('inventory_item_id', $item_id)->delete(db_prefix() . 'kt_inventory_batches');
        $this->db->where('inventory_item_id', $item_id)->delete(db_prefix() . 'kt_item_barcodes');
        $this->db->where('id', $item_id)->delete(db_prefix() . 'kt_inventory_items');
        $this->db->where('id', $warehouse_id)->delete(db_prefix() . 'kt_warehouses');
        $this->db->where('id', $core_item_id)->delete(db_prefix() . 'items');
    }
}
