# KT Integration Hub Local Webhook Testing

## Scope

This guide covers the MVP Custom Webhook connector.

Planned providers such as Facebook, Zalo OA, Shopee and TikTok Shop should not be tested as real connections until their OAuth/partner API connectors are implemented.

## Custom Webhook URL

Connections use the global landlord endpoint:

```text
https://khachtot.com/kt_integration_hub/webhook/{provider}/{connection_public_key}
```

For local testing:

```text
https://khachtot.test/kt_integration_hub/webhook/custom_webhook/{connection_public_key}
```

The endpoint resolves the tenant from `connection_public_key`, so it does not need the tenant subdomain.

## HMAC Signature

Required headers:

```text
X-KT-Timestamp: <unix_timestamp>
X-KT-Signature: <hex_hmac>
```

Signature formula:

```text
hash_hmac('sha256', timestamp + '.' + rawBody, webhook_secret)
```

The timestamp must be close to server `time()`; the default allowed window is 300 seconds.

## cURL Test

Use PHP to produce the timestamp so Windows timezone formatting does not drift from PHP:

```bash
RAW='{"event_id":"test-lead-001","event_type":"lead.created","lead":{"name":"Nguyen Van Test","phone":"0909000001","email":"test.integration@example.com","company":"Cong ty Test Integration","message":"Lead tu custom webhook"}}'
TS=$(php -r 'echo time();')
SIG=$(php -r 'echo hash_hmac("sha256", $argv[1] . "." . $argv[2], $argv[3]);' "$TS" "$RAW" "WEBHOOK_SECRET")

curl -i -X POST "WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -H "X-KT-Timestamp: $TS" \
  -H "X-KT-Signature: $SIG" \
  --data "$RAW"
```

Expected:

```text
HTTP 200
webhook event created
sync job queued
```

Duplicate `event_id` should return duplicate and must not create another lead.

## Processing Jobs

Run the local processor:

```bash
php index.php kt_integration_hub/cron/process_jobs
```

Expected:

```text
queued job processed
lead created or deduped in the correct tenant database
entity link created
```

## External Platform Testing

Domains ending in `.test` are local-only. Facebook, Zalo, Shopee, TikTok and other public platforms cannot call:

```text
https://khachtot.test/...
```

Use one of these options when a public callback URL is required:

```bash
ngrok http https://khachtot.test
```

or:

```bash
cloudflared tunnel --url https://khachtot.test
```

Then configure the generated public HTTPS URL in the external platform dashboard.

## Secret Handling

Do not paste full webhook secrets into screenshots, tickets, logs or release notes.

The app should show the secret only once after creation or rotation. Later screens should only expose the webhook URL and a test cURL template with `WEBHOOK_SECRET` placeholder.
