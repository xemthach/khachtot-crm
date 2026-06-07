<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KT eInvoice — Mapper Service
 *
 * Chuyển đổi dữ liệu Perfex Invoice → payload SePay eInvoice API
 * Seller info lấy từ settings tenant (tự nhập), buyer info từ Perfex client data.
 */
class KtEinvoiceMapperService
{
    /**
     * Build payload tạo hóa đơn gửi lên SePay
     *
     * @param array $perfexInvoice  Dữ liệu invoice từ Tenant DB
     * @param array $settings       Cấu hình eInvoice của tenant (seller info, template...)
     * @param array $client         Dữ liệu khách hàng từ Tenant DB
     * @param array $invoiceItems   Các dòng hàng hóa/dịch vụ
     * @param array $taxItems       Các dòng thuế (taxes)
     */
    public function buildCreatePayload(
        array $perfexInvoice,
        array $settings,
        array $client,
        array $invoiceItems,
        array $taxItems = []
    ): array {
        $invoiceDate = $this->formatDate($perfexInvoice['date'] ?? date('Y-m-d'));

        $payload = [
            // Thông tin hóa đơn
            'invoice_date'      => $invoiceDate,
            'invoice_series'    => $settings['invoice_series'] ?? 'C',
            'template_code'     => $settings['invoice_template_code'] ?? '01GTKT',
            'currency'          => 'VND',
            'exchange_rate'     => 1,

            // Người bán — bắt buộc từ settings của tenant
            'seller' => [
                'tax_code' => trim($settings['seller_tax_code'] ?? ''),
                'name'     => trim($settings['seller_name'] ?? ''),
                'address'  => trim($settings['seller_address'] ?? ''),
                'phone'    => trim($settings['seller_phone'] ?? ''),
                'email'    => trim($settings['seller_email'] ?? ''),
                'bank'     => [
                    'name'    => trim($settings['seller_bank_name'] ?? ''),
                    'account' => trim($settings['seller_bank_account'] ?? ''),
                ],
            ],

            // Người mua — từ Perfex client
            'buyer' => $this->buildBuyerInfo($client, $perfexInvoice),

            // Danh sách hàng hóa/dịch vụ
            'items' => $this->mapInvoiceItems($invoiceItems, $taxItems),

            // Tổng tiền
            'totals' => $this->buildTotals($perfexInvoice),

            // Reference (để trace về invoice Perfex)
            'reference_code' => 'KTINV-' . ($perfexInvoice['id'] ?? ''),
            'note'           => trim($perfexInvoice['adminnote'] ?? ''),
        ];

        // Provider account ID nếu có
        if (!empty($settings['provider_account_id'])) {
            $payload['provider_account_id'] = $settings['provider_account_id'];
        }

        return $payload;
    }

    /**
     * Build thông tin người mua
     * MST là optional — chỉ khi khách hàng là doanh nghiệp
     */
    private function buildBuyerInfo(array $client, array $invoice): array
    {
        $buyer = [
            'name'     => trim($client['company'] ?: ($client['firstname'] . ' ' . $client['lastname'])),
            'address'  => trim($client['billing_street'] . ', ' . $client['billing_city']),
            'email'    => trim($client['email'] ?? ''),
            'phone'    => trim($client['phonenumber'] ?? ''),
        ];

        // MST — optional, chỉ điền nếu có
        // Tìm trong custom fields của invoice hoặc client
        $taxCode = $this->extractBuyerTaxCode($client, $invoice);
        if (!empty($taxCode)) {
            $buyer['tax_code'] = $taxCode;
        }

        return $buyer;
    }

    /**
     * Tìm MST của buyer từ custom fields
     * Không bắt buộc — trả về null nếu không có
     */
    private function extractBuyerTaxCode(array $client, array $invoice): ?string
    {
        // Kiểm tra field 'vat' trực tiếp của client (Perfex có field này)
        if (!empty($client['vat'])) {
            return $this->sanitizeTaxCode($client['vat']);
        }

        // Kiểm tra billing_vat nếu có
        if (!empty($client['billing_vat'])) {
            return $this->sanitizeTaxCode($client['billing_vat']);
        }

        // Tìm trong custom fields của client (field tên 'mst' hoặc 'tax_code')
        if (!empty($client['custom_fields'])) {
            foreach ((array) $client['custom_fields'] as $field) {
                $fieldName = strtolower($field['fieldto'] ?? '');
                if (in_array($fieldName, ['mst', 'tax_code', 'ma_so_thue', 'vat_number'])) {
                    if (!empty($field['value'])) {
                        return $this->sanitizeTaxCode($field['value']);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Map dòng hàng hóa/dịch vụ từ Perfex → SePay format
     */
    private function mapInvoiceItems(array $items, array $taxItems): array
    {
        if (empty($items)) {
            return [];
        }

        // Build tax rate map từ taxItems
        $taxRateMap = [];
        foreach ($taxItems as $tax) {
            $itemId = (int) ($tax['itemid'] ?? 0);
            if ($itemId) {
                $taxRateMap[$itemId][] = (float) ($tax['taxrate'] ?? 0);
            }
        }

        $mapped = [];
        foreach ($items as $item) {
            $itemId   = (int) ($item['id'] ?? 0);
            $qty      = (float) ($item['qty'] ?? 1);
            $price    = (float) ($item['rate'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);

            // Tax rate — lấy tổng các tax của item này
            $taxRate = 0;
            if (isset($taxRateMap[$itemId])) {
                $taxRate = array_sum($taxRateMap[$itemId]);
            }

            $unitPrice    = $price;
            $discountAmt  = $unitPrice * $qty * ($discount / 100);
            $beforeTax    = ($unitPrice * $qty) - $discountAmt;
            $taxAmount    = $beforeTax * ($taxRate / 100);

            $mapped[] = [
                'item_code'       => (string) $itemId,
                'item_name'       => trim($item['description'] ?? ''),
                'unit'            => trim($item['unit'] ?? ''),
                'quantity'        => $qty,
                'unit_price'      => round($unitPrice, 0),
                'discount_rate'   => $discount,
                'discount_amount' => round($discountAmt, 0),
                'tax_rate'        => $taxRate,
                'tax_amount'      => round($taxAmount, 0),
                'amount'          => round($beforeTax + $taxAmount, 0),
            ];
        }

        return $mapped;
    }

    /**
     * Build tổng tiền hóa đơn
     */
    private function buildTotals(array $invoice): array
    {
        return [
            'subtotal'       => round((float) ($invoice['subtotal'] ?? 0), 0),
            'discount_total' => round((float) ($invoice['discount_total'] ?? 0), 0),
            'tax_total'      => round((float) ($invoice['tax'] ?? 0) + (float) ($invoice['tax2'] ?? 0), 0),
            'total'          => round((float) ($invoice['total'] ?? 0), 0),
        ];
    }

    /**
     * Validate payload trước khi gửi
     */
    public function validatePayload(array $payload): array
    {
        $errors = [];

        // Seller
        if (empty($payload['seller']['tax_code'])) {
            $errors[] = 'Chưa cấu hình Mã số thuế người bán (seller.tax_code). Vào Cài Đặt eInvoice để nhập MST.';
        } elseif (!$this->isValidTaxCode($payload['seller']['tax_code'])) {
            $errors[] = 'Mã số thuế người bán không hợp lệ: ' . $payload['seller']['tax_code'];
        }

        if (empty($payload['seller']['name'])) {
            $errors[] = 'Chưa cấu hình Tên công ty người bán.';
        }

        if (empty($payload['seller']['address'])) {
            $errors[] = 'Chưa cấu hình Địa chỉ người bán.';
        }

        // Items
        if (empty($payload['items'])) {
            $errors[] = 'Hóa đơn không có dòng hàng hóa/dịch vụ nào.';
        }

        // Buyer
        if (empty($payload['buyer']['name'])) {
            $errors[] = 'Thông tin khách hàng (tên người mua) trống.';
        }

        // Invoice date
        if (empty($payload['invoice_date'])) {
            $errors[] = 'Ngày hóa đơn không hợp lệ.';
        }

        // Total phải > 0
        if (empty($payload['totals']['total']) || $payload['totals']['total'] <= 0) {
            $errors[] = 'Tổng tiền hóa đơn phải lớn hơn 0.';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function sanitizeTaxCode(string $code): string
    {
        return preg_replace('/[^0-9\-]/', '', trim($code));
    }

    private function isValidTaxCode(string $code): bool
    {
        // Định dạng MST VN: 10 số hoặc 10-3 số
        return (bool) preg_match('/^\d{10}(-\d{3})?$/', $code);
    }
}
