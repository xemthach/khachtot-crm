<?php

defined('BASEPATH') or exit('No direct script access allowed');

class TikTokShopApiClient
{
    protected $timeout = 20;

    public function buildAuthorizationUrl(array $connection)
    {
        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED_UNTIL_DOC_VERIFIED',
            'message' => 'TikTok Shop authorization URL must be confirmed in Partner Center for the app region.',
        ];
    }

    public function exchangeCodeForToken(array $connection, $code)
    {
        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED_UNTIL_DOC_VERIFIED',
            'message' => 'TikTok Shop token exchange endpoint is pending Partner Center credential verification.',
        ];
    }

    public function refreshAccessToken(array $connection)
    {
        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED_UNTIL_DOC_VERIFIED',
            'message' => 'TikTok Shop token refresh endpoint is pending Partner Center credential verification.',
        ];
    }

    public function verifyWebhookSignature($rawBody, array $headers, array $connection)
    {
        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED_UNTIL_DOC_VERIFIED',
            'message' => 'TikTok Shop webhook signing algorithm must be confirmed in Partner Center.',
        ];
    }

    public function fetchOrderDetail(array $connection, $externalOrderId, array $eventPayload = [])
    {
        if (!empty($eventPayload['mock_order_detail']) && is_array($eventPayload['mock_order_detail'])) {
            return [
                'success' => true,
                'mode' => 'mock',
                'order' => $eventPayload['mock_order_detail'],
            ];
        }

        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED_UNTIL_DOC_VERIFIED',
            'message' => 'Real TikTok Shop order detail pull requires verified Seller API endpoint and request signing.',
        ];
    }

    public function listRecentOrders(array $connection, $since, $limit = 50)
    {
        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED_UNTIL_DOC_VERIFIED',
            'message' => 'Real TikTok Shop order listing requires verified Seller API endpoint and request signing.',
        ];
    }

    public function testConnection(array $connection)
    {
        $settings = function_exists('kt_integration_hub_json_decode')
            ? kt_integration_hub_json_decode((string) ($connection['settings_json'] ?? ''), [])
            : [];
        if (($settings['connection_mode'] ?? 'dry_run') === 'dry_run') {
            return [
                'success' => true,
                'mode' => 'dry_run',
                'message' => 'Dry-run mode is ready.',
            ];
        }

        return [
            'success' => false,
            'status' => 'NOT_IMPLEMENTED_UNTIL_DOC_VERIFIED',
            'message' => 'Real TikTok Shop API test requires Partner Center credentials and verified endpoint configuration.',
        ];
    }
}
