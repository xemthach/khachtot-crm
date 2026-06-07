# KIEN TRUC MODULE KT SAAS

## 1. Executive Summary

`KT SaaS` duoc thiet ke la mot module noi bo clean-room cho Perfex CRM, muc tieu bien mot he thong Perfex don le thanh mot nen tang SaaS da tenant co kha nang scale dai han.

Phan tach nghiep vu thanh 2 lop:

- `Landlord system`: quan ly tenant, plan, billing, subscription, domain, usage, backup, provisioning.
- `Tenant system`: moi tenant van la mot CRM rieng, DB rieng, module rieng, branding rieng, lifecycle rieng.

Huong xay dung khuyen nghi:

- Phase 1: landlord core + registry + plan/subscription + provisioning queue + subdomain.
- Phase 2: payment gateway + usage metering + module marketplace + automation.
- Phase 3: HA routing + domain automation + distributed queue + storage abstraction + analytics.

Nguyen tac clean-room:

- Khong copy code tu module SaaS hien co.
- Khong patch, bypass, go license module SaaS cu.
- Chi scan convention Perfex va tham khao bai toan nghiep vu cap cao.

## 2. Multi-tenant strategy

### Danh gia 3 option

#### Option A - Shared Database

Uu diem:

- Trien khai nhanh.
- De thong ke toan cuc.
- Don gian cho backup metadata landlord.

Nhuoc diem:

- Isolation yeu.
- Rui ro query thieu `tenant_id`.
- Kho tach tenant lon.
- Kho dap ung yeu cau compliance khi tenant can DB rieng.

Danh gia:

- Scale: trung binh
- Backup: trung binh
- Security: thap hon
- Migration: de giai doan dau, kho ve sau
- Performance: de nghen khi tenant lon
- Maintenance: don gian luc dau, tang no ky thuat nhanh

#### Option B - Database per Tenant

Uu diem:

- Isolation du lieu manh.
- Backup/restore theo tenant de dang.
- Tenant lon co the tach server DB rieng.
- Giam xac suat ro du lieu cheo tenant.
- Phu hop tinh chat CRM/ERP co nhieu giao dich, hoa don, kho, barcode, batch.

Nhuoc diem:

- Provisioning phuc tap hon.
- Quan ly migration schema tenant kho hon.
- Can co co che registry landlord + switch DB som trong request lifecycle.

Danh gia:

- Scale: cao
- Backup: rat tot
- Security: rat tot
- Migration: phuc tap hon nhung co kiem soat
- Performance: tot hon khi tenant tang truong khong dong deu
- Maintenance: can ky luat van hanh cao

#### Option C - Hybrid

Mau de xuat:

- Landlord DB rieng.
- Moi tenant co DB rieng.
- Shared services cho queue, cache, file metadata, billing webhook, usage aggregator.

Danh gia:

- Day la lua chon tot nhat cho Perfex CRM trong bai toan SaaS trung va dai han.

### Khuyen nghi cuoi cung

Chon `Option C - Hybrid`, trong do:

- `Landlord`: DB trung tam luu metadata va quan tri toan bo he thong.
- `Tenant`: moi tenant 1 database rieng.
- `Shared services`: Redis, queue DB/Redis, object storage, webhook, billing workers.

Ly do:

- Perfex ban chat la CRM transaction-heavy, khong duoc thiet ke goc cho shared-row tenant isolation.
- ERP duoc/kho/hoa don/bao gia/API de bi lan query neu dung shared table.
- Database-per-tenant cho rollback, backup, tuning, restore va forensic tot hon rat nhieu.

## 3. Landlord architecture

Landlord la mot lop dieu phoi trung tam, van chay tren Perfex/CodeIgniter convention nhung tach ro service layer:

- `controllers/`: landlord UI va API
- `models/`: truy van landlord metadata
- `services/`: business workflow
- `tenant_bootstrap/`: tenant resolver, db switcher, tenant context
- `provisioning/`: create db, seed, rollback, retry
- `billing/`: recurring billing, invoice lifecycle, dunning
- `cron/`: scheduler runner
- `events/`: event catalog va dispatch adapters

Landlord modules chinh:

- Tenant Registry
- Plan Catalog
- Subscription Manager
- Billing Engine
- Domain Manager
- Usage Meter
- Provisioning Orchestrator
- Backup Manager
- Marketplace/Entitlement Manager
- Audit Log

## 4. Tenant architecture

Moi tenant duoc xem nhu mot Perfex instance logic doc lap:

- DB rieng
- session scope rieng
- cache namespace rieng
- file storage namespace rieng
- settings rieng
- module enablement rieng
- theme/branding rieng

Tenant runtime can co:

- `tenant_context`
- `tenant_db_connection`
- `tenant_module_registry`
- `tenant_plan_limits`
- `tenant_entitlements`

Khong nen de landlord query truc tiep vao bang nghiep vu tenant trong request thong thuong. Thay vao do:

- usage snapshots
- async sync jobs
- on-demand maintenance workers

## 5. Database schema

Landlord schema khoi tao trong `install.php`:

- `tblkt_saas_tenants`
- `tblkt_saas_plans`
- `tblkt_saas_subscriptions`
- `tblkt_saas_invoices`
- `tblkt_saas_payments`
- `tblkt_saas_domains`
- `tblkt_saas_modules`
- `tblkt_saas_usage`
- `tblkt_saas_activity_logs`
- `tblkt_saas_provision_jobs`
- `tblkt_saas_backups`

### Ghi chu thiet ke

- PK: `BIGINT UNSIGNED` cho cac bang co the tang lon.
- FK logic: giai doan dau co the dung index logic thay vi FK cung de tranh lock va phuc tap backup.
- Index:
  - tenant_id
  - status
  - expires_at / next_billing_at / scheduled_at
  - domain unique
  - tenant_code unique
- Soft delete:
  - `deleted_at`, `deleted_by`
- Audit:
  - `created_at`, `updated_at`, `created_by`, `updated_by`

### Bang tenant

Field nghiep vu chinh:

- id
- tenant_code
- company_name
- owner_name
- owner_email
- phone
- status
- plan_id
- db_name
- db_host
- db_port
- db_user
- db_password_encrypted
- subdomain
- custom_domain
- timezone
- locale
- currency
- provisioning_status
- expires_at

### Bang plan

Bao gom:

- plan_code
- gia
- billing_cycle
- trial_days
- grace_days
- module_json
- limit_staff
- limit_clients
- limit_storage_mb
- limit_invoices
- limit_projects
- limit_api_requests_daily
- limit_warehouses
- limit_automations

## 6. Provisioning engine

Provisioning engine nen duoc to chuc theo `job orchestration`, khong tao tenant dong bo ngay trong HTTP request.

Flow de xuat:

1. Admin/checkout tao `tenant draft`
2. Tao `provision_job` status `queued`
3. Cron/worker nhan job
4. Chay cac buoc:
   - create database
   - create db user
   - import base Perfex schema
   - seed landlord-allowed modules
   - create tenant admin
   - generate tenant settings
   - tao storage namespace
   - tao domain mapping
   - gui email onboarding
5. Neu loi:
   - retry theo policy
   - rollback partial resources neu co the
6. Cap nhat `tenant.status` + `tenant.provisioning_status`

### Retry va rollback

- Retry:
  - tang `attempts`
  - `max_attempts`
  - exponential backoff
- Rollback:
  - xoa DB moi tao
  - xoa user DB
  - xoa folder storage
  - mark job `failed`

## 7. Billing engine

Billing engine nen tach thanh cac service:

- `SubscriptionService`
- `InvoiceGenerationService`
- `PaymentCollectionService`
- `DunningService`
- `CouponPromotionService`
- `TaxCalculationService`

### Invoice lifecycle

- draft
- issued
- pending_payment
- paid
- overdue
- void
- written_off

### Payment retry

- attempt 1: ngay due
- attempt 2: +3 ngay
- attempt 3: +7 ngay
- chuyen `grace`
- het grace thi suspend tenant

### Gateway de xuat

- Stripe: uu tien cho recurring va webhook ro rang
- PayPal: bo sung cho thi truong quoc te
- VNPay, Momo: adapter rieng cho noi dia

Khong hardcode gateway trong controller. Dung strategy pattern qua `services/gateways/`.

## 8. Subscription lifecycle

Trang thai subscription:

- draft
- trial
- active
- grace
- suspended
- cancelled
- expired
- terminated

Su kien chinh:

- create
- start_trial
- activate
- renew
- upgrade
- downgrade
- cancel_at_period_end
- move_to_grace
- suspend
- reactivate
- terminate

Rule:

- trial het han ma chua thanh toan => grace
- grace het han => suspend tenant
- thanh toan thanh cong trong grace => active lai
- terminate chi dung khi dong tenant vinh vien

## 9. Tenant isolation

Bat buoc:

- DB isolation: moi tenant DB rieng
- Session isolation: session cookie namespace theo host, session table/cache key theo tenant
- Cache isolation: prefix `tenant:{tenant_code}:`
- Queue isolation: payload co `tenant_id` va `tenant_code`
- File isolation: `/uploads/tenants/{tenant_code}/`
- Audit isolation: landlord log + tenant log tach ro

### Vi tri bootstrap tenant

Yeu cau ky thuat quan trong:

- resolve tenant theo domain truoc khi auth/session/DB nghiep vu hoat dong.

Trong Perfex, de dat muc isolation dung nghia, se can mot diem bootstrap som hon `AdminController`.

## 10. Module isolation

Muc tieu:

- tenant free khong co module kho
- tenant pro co kho
- tenant enterprise co API + automation

Cach lam:

- Landlord luu entitlement trong `tblkt_saas_modules` + `plan.module_json`
- Khi tenant bootstrap:
  - load plan entitlements
  - load tenant overrides
  - build `tenant_module_registry`

Module enablement stack:

- core modules
- plan modules
- paid addons
- tenant custom overrides

Can check 3 lop:

- module duoc plan cho phep?
- tenant co dang active?
- usage/limit co vuot nguong?

## 11. Security architecture

Bat buoc:

- CSRF cho admin forms va public billing forms
- Rate limit cho login, API, webhook
- API auth dung token ky han / HMAC / JWT noi bo
- Encrypt DB credential tenant bang `encryption_key`
- Audit log moi hanh dong admin SaaS
- Failed login tracking
- Domain ownership verification cho custom domain
- Webhook signature verification
- Principle of least privilege cho DB user tenant

Khong nen:

- luu plaintext DB password
- dung chung 1 DB superuser cho moi tenant neu co the tach quyen
- query tenant DB bang root user

## 12. Queue & cron architecture

Giai doan 1:

- Dung DB-backed jobs (`tblkt_saas_provision_jobs`)
- Cron Perfex goi scheduler landlord

Giai doan 2:

- Tach queue adapter:
  - database
  - Redis

Tac vu cron:

- recurring billing
- trial expiration
- grace expiration
- overdue dunning
- provisioning retry
- usage aggregation
- backup scheduling
- storage cleanup

Rule:

- Cron controller khong chua business logic lon
- Cron chi goi service runner

## 13. API architecture

API landlord noi bo:

- `POST /kt_saas/api/tenants`
- `POST /kt_saas/api/tenants/{id}/suspend`
- `POST /kt_saas/api/tenants/{id}/reactivate`
- `POST /kt_saas/api/subscriptions/{id}/renew`
- `POST /kt_saas/api/payments/webhook/{gateway}`
- `GET /kt_saas/api/usage/{tenant_id}`
- `POST /kt_saas/api/provision-jobs/{id}/retry`

Webhook su kien:

- payment success
- payment failed
- subscription renewed
- tenant created
- tenant suspended

Khuyen nghi:

- Tach API auth middleware rieng
- Log tat ca request/response metadata cua webhook
- Idempotency key cho callback thanh toan

## 14. Performance & scale

De xuat:

- Redis cho cache, session, queue o phase sau
- object storage cho backup/file lon
- CDN cho static assets landlord/public page
- DB pooling o layer ha tang
- read replica cho landlord analytics neu quy mo tang
- usage aggregation async, khong dem realtime qua nhieu query cheo tenant

Chien luoc storage:

- Landlord metadata: DB trung tam
- Tenant file: local theo namespace luc dau, len S3-compatible sau
- Backup: luu checksum + storage driver + retention policy

## 15. ERP/CRM duoc compatibility

Huong tuong thich rat quan trong vi he thong dang co module kho/duoc:

- multi warehouse
- batch/lot
- barcode
- recall
- FEFO
- distributor
- TDV
- route sale
- pharmacy POS

Khuyen nghi:

- plan co `limit_warehouses`
- module pharma enable theo tenant
- usage track them:
  - warehouse transactions
  - batch records
  - barcode scans
  - recall incidents

Khi tenant thuoc nhom duoc:

- DB rieng cang quan trong
- backup va audit phai manh hon
- event recall va traceability can luu vung

## 16. DevOps recommendation

De xuat van hanh:

- Docker cho local/dev/staging
- CI/CD:
  - lint PHP
  - syntax check
  - package module
  - deploy staging
- Monitoring:
  - app log
  - cron lag
  - failed provisioning
  - failed payments
  - DB growth
- Backup:
  - landlord DB daily
  - tenant DB theo policy plan
  - restore drill dinh ky

## 17. Migration roadmap

### Phase 1

- landlord core
- tenant registry
- plans
- subscriptions
- provision job queue
- subdomain foundation
- landlord dashboard
- audit logs

### Phase 2

- Stripe/PayPal/VNPay/Momo adapters
- usage tracking
- billing automation
- dunning workflow
- module marketplace
- custom domain verification

### Phase 3

- scale out worker
- Redis queue/cache
- HA routing
- analytics
- tenant cloning/templates
- advanced isolation
- DR automation

## 18. Risk assessment

Rui ro chinh:

- Perfex khong duoc thiet ke native multi-tenant => can bootstrap tenant som
- Migration schema tenant co the tro thanh diem nghen neu khong version hoa chat
- Payment webhook race condition
- Domain mapping va SSL automation phuc tap khi len production
- Queue DB se thanh diem nghen khi so tenant lon

Giam thieu:

- versioned tenant schema
- idempotent billing jobs
- strong audit log
- worker retry + rollback
- staging environment bat buoc

## 19. Technical debt warning

Can tranh ngay tu dau:

- putting all logic in controllers
- shared-db tenant isolation bang `tenant_id` tren core Perfex tables
- hardcode gateway logic trong 1 file
- synchronous provisioning trong request web
- direct cross-tenant analytics query theo request
- mix landlord va tenant settings trong cung bang options

Neu di nhanh bang shortcut o Phase 1, no ky thuat se rat dat o Phase 2-3.

## 20. Final recommendation

Khuyen nghi cuoi cung:

- Xay `KT SaaS` theo hybrid architecture: landlord DB + database-per-tenant.
- Giu Perfex core khong sua trong phase scaffold hien tai.
- Khi vao phase bootstrap tenant thuc te, uu tien mot thay doi nho, co kiem soat de chay tenant resolver som.

### Neu can sua core / application bootstrap

Thay doi de xuat nho nhat:

- File: `application/config/hooks.php`
- Ly do: dang ky `pre_system` hoac `pre_controller` hook de resolve tenant theo domain truoc khi session/auth nghiep vu tenant chay.
- Impact: tao diem bootstrap som, giam rui ro sai DB/session.
- Rollback: bo hook registration, he thong tro ve single-tenant nhu cu.

Neu muon tranh sua file nay, co the tri hoan full domain-based tenant runtime sang phase sau va chi dung landlord dashboard + provisioning registry trong phase hien tai.
