# SUBDOMAIN VALIDATION FIX REPORT

## Current Logic
- Public signup previously only validated subdomain format in the browser.
- Server-side tenant creation already checked `kt_saas_tenants.subdomain`, but live UI did not show real availability.

## Root Cause
- The signup wizard was using a local regex-only hint.
- Browser validation also pointed to an absolute `https://` endpoint while the page was loaded over `http://`, which caused CORS failure and a false "used" state.

## Availability Check
- Added public endpoint: `signup/check-subdomain`
- Live check now validates:
  - format
  - reserved names
  - existing tenants
  - existing tenant domains
  - alias tables if present

## Reserved Names
- `admin`
- `crm`
- `api`
- `mail`
- `smtp`
- `ftp`
- `cpanel`
- `www`
- `support`
- `billing`
- `invoice`
- `checkout`
- `login`

## Suggestions
- Available alternatives are generated from the requested slug:
  - `mrthien2`
  - `mrthien-crm`
  - `mrthien-office`
  - `mrthien2026`
  - plus numeric fallbacks when needed

## Race Condition Protection
- Final re-check happens before tenant creation.
- `save_tenant()` now rejects reserved/occupied subdomains.
- Duplicate-key DB failure is handled and returned as a clean validation error.

## Browser Verification
- `newbrand` -> `🟢 Có thể sử dụng`
- `admin` -> `🔴 Tên này đã bị cấm`
- `xem-gpt2` -> `🔴 Đã được sử dụng`

## Regression Result
- Public signup still renders normally.
- Signup submission flow is unchanged except for stronger subdomain validation.
- No change to billing, pricing, provisioning, or landing routes.

## Final Status
- Subdomain validation now checks real availability, not format only.
