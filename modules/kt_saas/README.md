# KT SaaS

Clean-room multi-tenant SaaS foundation module for Perfex CRM.

Current scope:

- landlord schema
- SaaS admin dashboard
- tenant CRUD
- plan CRUD
- subscription CRUD
- domain registry CRUD
- provision job monitoring and re-queue
- manual run-now provisioning action
- activity log monitoring
- landlord settings for base domain and DB defaults
- plan seed data
- provisioning engine with database creation, schema clone, baseline seed, admin bootstrap, and manifest output
- billing and tenant service skeleton
- detailed architecture blueprint
- tenant runtime guard for status and provisioning readiness
- session boundary hardening to prevent auth reuse across tenant hosts
- helper utilities for tenant-scoped cache keys and storage paths
- plan-based module entitlement guard for tenant routes
- managed module fallback policy for internal addons such as `kt_inventory`
- plan limit enforcement for tenant staff, clients, projects, invoices, and warehouses
- landlord usage snapshot sync on tenant create-flow events
- scheduled usage recalculation runner for landlord cron
- landlord dashboard visibility for usage overview and latest snapshots
- delete-side usage resync for staff, clients, projects, invoices, and warehouses
- overage watchlist on landlord dashboard based on plan limits versus latest usage
- usage retention cleanup for old snapshots
- domain readiness verification with expected target, DNS diagnostics, SSL diagnostics, and cron re-check
- recurring billing foundation for trial expiration, grace transitions, free renewals, renewal invoice generation, and suspension workflow
- landlord invoice and payment monitoring views
- overdue invoice escalation and dunning attempt tracking
- manual invoice payment marking with subscription and tenant reactivation

Main architecture document:

- `docs/ARCHITECTURE.md`

Phase 1 status:

- implemented inside module scope
- minimal hook extension added through `application/config/my_hooks.php`
- domain-based tenant bootstrap scaffold is in place and can be toggled on via settings
- provisioning output avoids persisting plaintext bootstrap password in tenant manifest
- billing automation is foundation-only and does not yet collect payments through external gateways
