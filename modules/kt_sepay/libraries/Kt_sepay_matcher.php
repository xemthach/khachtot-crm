<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Kt_sepay_matcher
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_SEPAY_MODULE . '/Kt_sepay_model');
    }

    public function extractCandidateReferences(array $payload)
    {
        $candidates = [];
        $fields = [
            $payload['code'] ?? '',
            $payload['referenceCode'] ?? '',
            $payload['reference_code'] ?? '',
            $payload['content'] ?? '',
            $payload['transaction_content'] ?? '',
            $payload['transfer_content'] ?? '',
            $payload['description'] ?? '',
        ];

        foreach ($fields as $field) {
            $field = strtoupper(trim((string) $field));
            if ($field === '') {
                continue;
            }

            $candidates[] = $field;
            if (preg_match_all('/\b[A-Z0-9]{6,64}\b/', $field, $matches)) {
                foreach ($matches[0] as $match) {
                    $candidates[] = strtoupper($match);
                }
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    public function matchPaymentRequest(array $payload, $tenantId = null)
    {
        $candidates = $this->extractCandidateReferences($payload);

        foreach ($candidates as $candidate) {
            $request = $this->CI->Kt_sepay_model->get_payment_request_by_reference($candidate, $tenantId);
            if ($request) {
                return [
                    'matched'           => true,
                    'reference_code'    => $candidate,
                    'payment_request'   => $request,
                ];
            }
        }

        return [
            'matched'         => false,
            'reference_code'  => '',
            'payment_request' => null,
            'candidates'      => $candidates,
        ];
    }

    public function evaluateAmount(array $request, array $payload, $allowPartial = false)
    {
        $expected = (float) ($request['amount'] ?? 0);
        $received = (float) ($payload['transferAmount'] ?? $payload['transfer_amount'] ?? $payload['amount'] ?? 0);

        if ($received <= 0) {
            return ['status' => 'invalid', 'expected' => $expected, 'received' => $received];
        }

        if (abs($expected - $received) < 0.01) {
            return ['status' => 'exact', 'expected' => $expected, 'received' => $received];
        }

        if ($received < $expected) {
            return ['status' => $allowPartial ? 'partial' : 'short', 'expected' => $expected, 'received' => $received];
        }

        return ['status' => 'overpaid', 'expected' => $expected, 'received' => $received];
    }
}
