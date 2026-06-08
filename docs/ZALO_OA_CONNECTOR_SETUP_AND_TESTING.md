# Zalo OA Connector V1 Setup And Testing

## Scope

Zalo OA Connector V1 supports inbound webhook intake for local and controlled live testing.

Implemented:

- Zalo OA connection settings.
- Global webhook endpoint resolved by `connection_public_key`.
- Raw event storage.
- Queue job processing.
- Lead creation/dedupe by Zalo user id.
- Entity links for `zalo_user` and `zalo_message`.

Not implemented in V1:

- Outbound messages.
- ZNS/ZBS.
- Full inbox/conversation UI.
- Production OAuth token exchange until real Zalo credentials are tested.

## Official References

- Zalo Developers: https://developers.zalo.me/
- Zalo Docs: https://developers.zalo.me/docs
- OA OpenAPI guide: https://oa.zalo.me/home/documents/vie/guides/Khoi-tao-ung-dung-va-cap-quyen_117071366476220195
- Webhook overview: https://developers.zalo.me/docs/official-account/webhook/tong-quan
- User sends message event: https://developers.zalo.me/docs/official-account/webhook/tin-nhan/su-kien-nguoi-dung-gui-tin-nhan

## Required Zalo Resources

Before production use, prepare:

- Zalo Developer account.
- Zalo App.
- Zalo Official Account.
- Zalo OA authorization/access token.
- Webhook URL configured in Zalo Developer/OA settings.

## Connection Fields

In tenant admin:

```text
Connections -> New connection -> Zalo OA
```

Fields:

- Connection name.
- Zalo App ID.
- Zalo App Secret / OA secret key.
- Zalo OA ID.
- OA display name.
- Default lead source.
- Assigned staff ID.
- Lead status ID.

The full secret is stored encrypted and is not printed in logs.

## URLs

Webhook URL:

```text
https://khachtot.com/kt_integration_hub/webhook/zalo_oa/{connection_public_key}
```

OAuth callback URL:

```text
https://khachtot.com/kt_integration_hub/oauth/zalo_oa/callback/{connection_public_key}
```

Local:

```text
https://khachtot.test/kt_integration_hub/webhook/zalo_oa/{connection_public_key}
```

The endpoint resolves tenant ownership from the public connection key.

## Webhook Security

If Zalo sends `X-ZEvent-Signature`, the connector verifies it using:

```text
sha256(app_id + raw_json_body + timestamp + app_secret)
```

If no signature header is present, the event is accepted for local simulation with:

```text
signature_status = unchecked
```

Do not treat `unchecked` as production-verified.

## Local Simulated Webhook Test

`.test` domains cannot receive callbacks from external Zalo infrastructure. Use local cURL for simulation:

```bash
RAW='{"event_name":"user_send_text","sender":{"id":"zalo_user_001"},"recipient":{"id":"oa_test_001"},"message":{"msg_id":"msg_001","text":"Toi can tu van CRM"},"timestamp":1780000000000}'

curl -i -X POST "WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  --data "$RAW"
```

Expected:

```text
HTTP 200
webhook event stored
sync job queued
signature_status = unchecked
```

Process the queue:

```bash
php index.php kt_integration_hub/cron/process_jobs
```

Expected:

```text
lead created in tenant DB
zalo_user entity link created
zalo_message entity link created
```

Duplicate test:

- Send the same `msg_id` again.
- Expected: duplicate event response, no new job, no new lead.

Same user new message:

- Send `msg_002` with the same sender id.
- Expected: new message link, same lead id.

## External Webhook Testing

For public platform callbacks in local development, expose local HTTPS through:

```bash
ngrok http https://khachtot.test
```

or:

```bash
cloudflared tunnel --url https://khachtot.test
```

## Live Notes

- Use HTTPS.
- Configure Cloudflare bypass/challenge exemptions for:

```text
/kt_integration_hub/webhook/*
/kt_integration_hub/oauth/*
```

- Keep the cron processor running:

```cron
*/2 * * * * cd /home/khachtotcom/khachtot.com/public_html && flock -n /tmp/khachtot_integration_hub.lock /usr/bin/php index.php kt_integration_hub/cron/process_jobs >> /home/khachtotcom/khachtot.com/cron-logs/kt-integration-hub.log 2>&1
```

## Troubleshooting

- `401 Unauthorized`: check `X-ZEvent-Signature`, app id, timestamp and app secret.
- `400 Invalid payload`: check JSON body and `Content-Type: application/json`.
- Event stored but no lead: run cron and inspect `tblkt_integration_sync_jobs`.
- Lead duplicated: verify entity links for `zalo_user` by tenant/provider/user id.
