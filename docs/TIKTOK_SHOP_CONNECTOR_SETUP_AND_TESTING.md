# TikTok Shop Connector V1 - Setup and Testing

## Scope

TikTok Shop Connector V1 is limited to order event intake and channel order staging.

It does not create CRM invoices, stock movements, fulfillment updates, refunds, or product sync.

## Official References

- TikTok Shop Partner Center: https://partner.tiktokshop.com/
- TikTok Shop API concepts: https://partner.tiktokshop.com/docv2/page/tts-api-concepts-overview
- TikTok Shop Seller API overview: https://partner.tiktokshop.com/docv2/page/seller-api-overview
- TikTok Shop API Testing Tool: https://partner.tiktokshop.com/api/document
- TikTok for Developers: https://developers.tiktok.com/

The TikTok Shop documentation pages are rendered as a JavaScript app. Endpoint paths, request signing, and token exchange must be verified inside Partner Center/API Testing Tool with real app credentials before enabling production API calls.

## Current Implementation

| Area | Status |
| --- | --- |
| Provider metadata | `tiktok_shop` beta |
| Connection mode | Dry-run/mock ready |
| OAuth callback | Prepared, token exchange pending verification |
| Webhook | Global endpoint ready |
| Order processing | Staging only |
| Product sync | Planned |
| Inventory sync | Planned |
| Invoice creation | Disabled in V1 |

## Routes

Webhook:

```text
https://khachtot.com/kt_integration_hub/webhook/tiktok_shop/{connection_public_key}
```

OAuth callback:

```text
https://khachtot.com/kt_integration_hub/oauth/tiktok_shop/callback/{connection_public_key}
```

Cron:

```bash
php index.php kt_integration_hub/cron/process_jobs
```

## Dry-run Connection

Create a tenant connection:

```text
Provider: TikTok Shop
Connection mode: Dry-run / Mock
Shop ID: TTS-SHOP-001
Shop Name: TikTok Shop Test
Sync orders: enabled
Dry-run mode: enabled
Active: enabled
```

## Mock Webhook Test

```bash
RAW='{"event_id":"tts-event-001","event_type":"ORDER_STATUS_CHANGE","order_id":"TTS-ORDER-001","shop_id":"TTS-SHOP-001","status":"AWAITING_SHIPMENT","timestamp":1780000000,"mock_order_detail":{"order_id":"TTS-ORDER-001","order_code":"TTS-ORDER-001","status":"AWAITING_SHIPMENT","payment_status":"PAID","fulfillment_status":"PENDING","buyer":{"name":"Nguyen Van TikTok","phone":"0909000002"},"currency":"VND","subtotal":250000,"shipping_fee":20000,"discount_total":10000,"grand_total":260000,"items":[{"item_id":"product_001","sku_id":"sku_001","sku":"SKU-TTS-001","name":"San pham TikTok Test","quantity":1,"unit_price":250000,"total_price":250000}],"ordered_at":"2026-06-08 10:00:00"}}'

curl -i -X POST "WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  --data "$RAW"
```

Expected:

```text
HTTP 200
webhook event stored
sync job queued
signature_status=unchecked
```

Run cron:

```bash
php index.php kt_integration_hub/cron/process_jobs
```

Expected:

```text
checked 1
done 1
channel order created or updated
order item replaced/upserted
tiktok_order entity link created
```

## Duplicate and Status Update

Send the same `event_id` again:

```text
Webhook event is treated as duplicate. No duplicate staging order.
```

Send a new `event_id` with the same `order_id` and a new status:

```text
Same channel order row is updated. Items are replaced from the latest detail payload.
```

## Tables

- `tblkt_integration_connections`
- `tblkt_integration_webhook_events`
- `tblkt_integration_sync_jobs`
- `tblkt_integration_entity_links`
- `tblkt_integration_logs`
- `tblkt_integration_channel_orders`
- `tblkt_integration_channel_order_items`

## Security

- App secret is stored in `webhook_secret_encrypted`.
- Access token is stored in `access_token_encrypted`.
- Refresh token is stored in `refresh_token_encrypted`.
- Headers and logs are redacted through `kt_integration_hub_redact_secrets()`.
- Buyer phone is stored masked in channel order staging.

## Production Notes

Before using real TikTok Shop API calls:

1. Confirm API base URL in Partner Center.
2. Confirm OAuth authorization URL and token exchange endpoint.
3. Confirm request signing algorithm.
4. Confirm webhook signing headers and verification formula.
5. Disable dry-run unsigned webhook acceptance.
6. Test with Partner Center API Testing Tool.

