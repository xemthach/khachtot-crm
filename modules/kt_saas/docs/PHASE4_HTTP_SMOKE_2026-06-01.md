# KT SaaS Landing - Phase 4 HTTP Smoke (2026-06-01)

## Lệnh chạy

```bash
curl -k -I https://khachtot.test/
curl -k -I https://khachtot.test/pricing
curl -k -I https://khachtot.test/signup
curl -k -I https://khachtot.test/signup/status
curl -k -I https://khachtot.test/clients
curl -k -I https://khachtot.test/login
curl -k -I https://khachtot.test/admin
```

## Kết quả
- `https://khachtot.test/` -> `200`
- `https://khachtot.test/pricing` -> `200`
- `https://khachtot.test/signup` -> `200`
- `https://khachtot.test/signup/status` -> `303` (đúng vì cần flashdata)
- `https://khachtot.test/clients` -> `303` (đúng vì redirect auth)
- `https://khachtot.test/login` -> `200`
- `https://khachtot.test/admin` -> `303` (đúng vì redirect auth)

## Kết luận
- Smoke regression route ở mức HTTP status đã pass.
- Chưa thay thế E2E browser (submit form thật, webhook thật, retry provisioning thật).
