# IMPLEMENTATION_PLAN.md

## Purpose

This document defines the implementation sequence for the POS SaaS MVP.

Rules:

- Follow phases in dependency order.
- Do not start a blocked phase.
- Keep scope aligned with `docs/TZ.md`.
- Mark tasks complete only after tests and formatting pass.
- Use the status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

---

# Phase 0 — Project Bootstrap

Status: TODO

Goal:

Create the Laravel 13 foundation and development tooling.

Tasks:

- [ ] Create Laravel 13 project.
- [ ] Configure PHP 8.4 requirements.
- [ ] Configure PostgreSQL 17.
- [ ] Configure Redis.
- [ ] Install Filament 4.
- [ ] Install Laravel Sanctum.
- [ ] Install Spatie Laravel Permission.
- [ ] Enable Spatie teams support with `organization_id`.
- [ ] Install/configure Filament Shield.
- [ ] Install Pest.
- [ ] Configure Pint.
- [ ] Install Vue 3 + TypeScript + Pinia + Tailwind + Vite.
- [ ] Create `/platform`, `/admin`, `/pos` entry points.
- [ ] Add project folders from TZ.
- [ ] Add CI-safe `.env.example`.

Acceptance criteria:

- Laravel boots.
- PostgreSQL connection works.
- Redis connection works.
- Platform Filament panel loads.
- Admin Filament panel loads.
- Vue POS shell loads at `/pos`.
- Pest runs.
- Pint runs.

Tests:

- Basic application boot test.
- Authentication smoke test if auth is already enabled.

---

# Phase 1 — Organization and Store Foundation

Status: TODO

Depends on:

- Phase 0

Goal:

Create SaaS tenant and store foundation.

Database:

- organizations
- stores
- organization_user
- store_user

Models:

- Organization
- Store

Support:

- TenantContext
- StoreContext

Tasks:

- [ ] Create migrations.
- [ ] Create models and relations.
- [ ] Add organization membership relation to User.
- [ ] Add store access relation to User.
- [ ] Implement current organization resolution.
- [ ] Implement current store resolution.
- [ ] Add middleware/context initialization.
- [ ] Prevent user-selected tenant IDs from becoming security source.
- [ ] Add indexes for organization/store relations.

Acceptance criteria:

- One user may belong to multiple organizations.
- One organization may contain multiple stores.
- User can be restricted to selected stores.
- Current organization/store are server-resolved.
- Cross-tenant model access helpers exist.

Tests:

- Organization membership.
- Store membership.
- Tenant context.
- Store context.
- Cross-organization access rejection.

---

# Phase 2 — Roles, Permissions and Policies

Status: TODO

Depends on:

- Phase 1

Goal:

Implement organization-scoped authorization.

Tasks:

- [ ] Configure Spatie teams with `organization_id`.
- [ ] Define permission naming convention.
- [ ] Add default permissions seeder.
- [ ] Add default roles:
  - Owner
  - Manager
  - Cashier
  - Waiter
- [ ] Create organization onboarding role assignment.
- [ ] Configure Filament Shield for admin panel.
- [ ] Create reusable authorization helpers.
- [ ] Add store access checks.
- [ ] Add base policy conventions.
- [ ] Add role management UI in organization admin.
- [ ] Ensure menu visibility follows authorization.
- [ ] Ensure backend policies remain authoritative.

Acceptance criteria:

- Same user may have different roles in different organizations.
- Organization owner can manage roles/permissions.
- Cashier cannot access manager-only pages/actions.
- Hidden UI cannot be bypassed via direct request.

Tests:

- Organization-specific roles.
- Permission denial.
- Store access denial.
- Cross-tenant policy denial.

---

# Phase 3 — Plans, Features and Subscriptions

Status: TODO

Depends on:

- Phase 1
- Phase 2

Goal:

Create SaaS subscription foundation without payment gateway integration.

Database:

- plans
- features
- plan_features
- subscriptions

Enums:

- SubscriptionStatus

Tasks:

- [ ] Create migrations/models.
- [ ] Create plan management in `/platform`.
- [ ] Create feature management in `/platform`.
- [ ] Create subscription management in `/platform`.
- [ ] Implement `organizationHasFeature()` style service/helper.
- [ ] Implement active subscription check.
- [ ] Block new POS transactions for expired subscriptions.
- [ ] Keep admin read access available when subscription expires.
- [ ] Create starter seed plans/features if useful.
- [ ] Add max_stores and max_users enforcement.

Acceptance criteria:

- Organization has one active subscription context.
- Features are plan-based.
- Permissions and features remain separate.
- Expired organization cannot create new POS transactions.
- Platform admin can manually activate/extend subscription.

Tests:

- Active subscription.
- Expired subscription.
- Feature enabled/disabled.
- User permission + feature combination.
- max_stores/max_users enforcement.

---

# Phase 4 — Organization Onboarding

Status: TODO

Depends on:

- Phase 2
- Phase 3

Goal:

Create one clean flow to provision a new customer organization.

Action:

- CreateOrganization

Flow:

```text
Create Organization
→ create subscription
→ create first store
→ create default roles
→ assign owner
→ prepare default print route placeholders
```

Tasks:

- [ ] Create onboarding Action.
- [ ] Create platform form/page for onboarding.
- [ ] Assign owner membership.
- [ ] Assign Owner role in organization context.
- [ ] Create first store.
- [ ] Apply selected plan/subscription.
- [ ] Validate plan user/store limits.
- [ ] Make process transactional where appropriate.

Acceptance criteria:

- Platform admin can provision a usable customer organization from one flow.
- Owner can immediately enter `/admin`.
- First store exists.
- Roles exist.
- Subscription exists.

Tests:

- Successful onboarding.
- Transaction rollback on onboarding failure.
- Correct role assignment.
- Correct tenant isolation.

---

# Phase 5 — Catalog

Status: TODO

Depends on:

- Phase 1
- Phase 2
- Phase 3

Goal:

Provide minimal product catalog required by POS.

Database:

- categories
- products

Tasks:

- [ ] Create category migration/model.
- [ ] Create product migration/model.
- [ ] Add `organization_id`.
- [ ] Add `is_active`.
- [ ] Add `sort_order`.
- [ ] Store product prices as integer UZS.
- [ ] Create admin Filament resources.
- [ ] Add policies.
- [ ] Add plan/feature checks only if required by TZ.
- [ ] Add catalog cache strategy for POS bootstrap.
- [ ] Invalidate cache after catalog changes.

Acceptance criteria:

- Owner/authorized manager can manage catalog.
- Cashier cannot manage catalog unless permitted.
- Product belongs to organization, not directly to one store.
- Inactive products do not appear in POS.
- Price changes do not modify historical order items later.

Tests:

- Tenant isolation.
- Permission checks.
- Active/inactive catalog filtering.
- Money integer validation.

---

# Phase 6 — Tables

Status: TODO

Depends on:

- Phase 1
- Phase 2

Goal:

Support dine-in table selection.

Database:

- tables

Tasks:

- [ ] Create migration/model.
- [ ] Add organization/store ownership.
- [ ] Create Filament resource.
- [ ] Add policy.
- [ ] Implement active-order-derived table occupancy service/query.
- [ ] Do not persist duplicated `occupied` state.

Acceptance criteria:

- Tables are store-specific.
- Table occupancy is derived from active order.
- Store A users cannot see Store B tables.

Tests:

- Store isolation.
- Table occupancy calculation.
- Permission checks.

---

# Phase 7 — Devices

Status: TODO

Depends on:

- Phase 1
- Phase 2

Goal:

Represent POS terminals as first-class entities for printing and future offline support.

Database:

- devices

Tasks:

- [ ] Create ULID device model.
- [ ] Add organization/store ownership.
- [ ] Add name/code/is_active/last_seen_at.
- [ ] Implement DeviceContext.
- [ ] Create registration/setup API.
- [ ] Create admin device listing.
- [ ] Create POS device setup shell.
- [ ] Validate device belongs to current tenant/store.

Acceptance criteria:

- POS session can resolve its device.
- Devices are store-specific.
- Disabled device cannot create POS transactions.

Tests:

- Device registration.
- Device store isolation.
- Disabled device denial.

---

# Phase 8 — Printer Foundation

Status: TODO

Depends on:

- Phase 7

Goal:

Build configurable printer routing without hardcoded device names.

Database:

- printers
- print_routes

Enums:

- PrintType

Initial PrintType values:

- CUSTOMER_RECEIPT
- KITCHEN_TICKET

Tasks:

- [ ] Create printer migration/model.
- [ ] Create print route migration/model.
- [ ] Add organization/store/device ownership where defined.
- [ ] Create admin Filament resources.
- [ ] Implement PrinterRoutingService.
- [ ] Create PrinterService interface on POS frontend.
- [ ] Isolate QZ Tray implementation.
- [ ] Implement printer discovery on POS device.
- [ ] Implement test print.
- [ ] Implement device-local printer binding.
- [ ] Allow both print types to route to one physical printer.
- [ ] Allow admin to route kitchen ticket to another printer later.
- [ ] Add reprint support marker foundation.

Acceptance criteria:

- One printer can serve both receipt and kitchen.
- Print route can be changed from UI.
- No business logic contains physical printer name.
- Test print works from POS device.
- Print failure does not mutate order/payment data.

Tests:

- Printer route resolution.
- Tenant/store isolation.
- Permission checks.
- Mocked PrinterService behavior.

Manual test:

- QZ Tray sees a real thermal printer.
- Test ticket prints.

---

# Phase 9 — Customers and Delivery Details

Status: TODO

Depends on:

- Phase 1

Goal:

Support minimal delivery customer information.

Database:

- customers
- delivery_details

Tasks:

- [ ] Create ULID customer model.
- [ ] Create delivery details model.
- [ ] Add tenant/store ownership as defined in TZ.
- [ ] Customer phone required where customer is used.
- [ ] Delivery address snapshot stored on order.
- [ ] Create admin customer list if useful.
- [ ] Create POS customer lookup by phone.
- [ ] Keep MVP fields minimal.

Acceptance criteria:

- Delivery order can capture phone and address.
- Existing customer may be reused by phone.
- Historical address snapshot remains unchanged.

Tests:

- Tenant isolation.
- Customer phone lookup.
- Delivery snapshot behavior.

---

# Phase 10 — Order Domain Foundation

Status: TODO

Depends on:

- Phase 5
- Phase 6
- Phase 7
- Phase 9

Goal:

Create the central transaction model.

Database:

- orders
- order_items

Enums:

- OrderType
- OrderStatus
- PaymentStatus

Actions:

- CreateOrder
- AddOrderItem
- UpdateOpenOrder
- CancelOrder

Tasks:

- [ ] Create ULID orders.
- [ ] Create ULID order_items.
- [ ] Add snapshot `product_name`.
- [ ] Add snapshot `unit_price`.
- [ ] Add `kitchen_printed_at`.
- [ ] Implement store-level display number generation.
- [ ] Implement DINE_IN.
- [ ] Implement TAKEAWAY.
- [ ] Implement DELIVERY.
- [ ] Validate table belongs to current store.
- [ ] Validate customer belongs to current organization.
- [ ] Implement idempotent order creation.
- [ ] Implement idempotent order item creation.
- [ ] Use DB transactions where required.
- [ ] Add policies.
- [ ] Do not implement inventory/modifiers.

Acceptance criteria:

- All 3 order types can be created.
- Dine-in order may remain open unpaid.
- Order item snapshots survive catalog changes.
- Duplicate replay does not create duplicate order.
- Cross-tenant/store manipulation is blocked.

Tests:

- Dine-in flow.
- Takeaway flow.
- Delivery flow.
- Idempotency.
- Tenant isolation.
- Store isolation.
- Snapshot integrity.
- Invalid table/customer rejection.

---

# Phase 11 — Incremental Kitchen Printing

Status: TODO

Depends on:

- Phase 8
- Phase 10

Goal:

Send only unprinted order items to the kitchen.

Actions:

- PrepareKitchenTicket
- MarkKitchenItemsPrinted

Tasks:

- [ ] Query only `kitchen_printed_at IS NULL`.
- [ ] Build kitchen ticket DTO/payload.
- [ ] Resolve KITCHEN_TICKET printer route.
- [ ] Trigger frontend PrinterService.
- [ ] Mark items printed only after client-confirmed successful print.
- [ ] Preserve order if print fails.
- [ ] Add REPRINT flow.
- [ ] Ensure reprint is visibly marked.

Acceptance criteria:

Scenario:

```text
2x Lavash → print
+1 Cola → print
```

Second kitchen print contains only `1x Cola`.

Tests:

- Initial kitchen ticket.
- Incremental ticket.
- Print failure.
- Reprint marker.
- Permission enforcement.

---

# Phase 12 — Payments

Status: TODO

Depends on:

- Phase 10

Goal:

Support order payments with future split payment compatibility.

Database:

- payments

Enum:

- PaymentMethod

Methods:

- CASH
- CARD
- CLICK
- PAYME
- OTHER

Actions:

- CreatePayment
- RecalculatePaymentStatus

Tasks:

- [ ] Create ULID payments.
- [ ] Add idempotency.
- [ ] Prevent duplicate payment replay.
- [ ] Calculate paid amount from payment records.
- [ ] Calculate remaining amount.
- [ ] Update payment status transactionally.
- [ ] Support multiple payment records per order.
- [ ] Add payment policies.
- [ ] Do not physically delete payments.

Acceptance criteria:

- Unpaid order works.
- Partial payment works.
- Full payment works.
- Mixed payment schema works.
- Duplicate replay cannot double-charge internal accounting.

Tests:

- Unpaid.
- Partial.
- Paid.
- Mixed.
- Duplicate payment.
- Tenant/store access.
- Money integer behavior.

---

# Phase 13 — Order Completion and Customer Receipt

Status: TODO

Depends on:

- Phase 8
- Phase 11
- Phase 12

Goal:

Close orders correctly and print customer receipts.

Actions:

- CompleteOrder
- PrepareCustomerReceipt

Tasks:

- [ ] Complete DINE_IN after payment rules are satisfied.
- [ ] Complete TAKEAWAY according to MVP flow.
- [ ] Support delivery unpaid/paid lifecycle defined by TZ.
- [ ] Resolve CUSTOMER_RECEIPT printer route.
- [ ] Print receipt through PrinterService.
- [ ] Preserve payment/order if printer fails.
- [ ] Free table when dine-in order is completed/cancelled.
- [ ] Support receipt reprint with REPRINT marker.

Acceptance criteria:

- Completed dine-in frees table.
- Receipt may use same printer as kitchen.
- Receipt may later use a different printer via route change.
- Printer failure does not rollback payment.

Tests:

- Completion rules.
- Table freeing.
- Receipt route.
- Print failure.
- Reprint.

---

# Phase 14 — Shifts

Status: TODO

Depends on:

- Phase 7
- Phase 12

Goal:

Provide minimal cashier shift control.

Database:

- shifts

Enum:

- ShiftStatus

Actions:

- OpenShift
- CloseShift

Tasks:

- [ ] Create ULID shifts.
- [ ] Link store/device/user.
- [ ] Add opening_cash.
- [ ] Add closing_cash.
- [ ] Prevent multiple invalid active shifts for same business rule.
- [ ] Require active shift for POS operations as defined.
- [ ] Associate cash payments with current operational context.
- [ ] Add shift open/close POS UI.
- [ ] Add permissions.

Acceptance criteria:

- Cashier can open shift.
- Cashier can close shift.
- POS shows current shift.
- Invalid shift state blocks required operations.

Tests:

- Open.
- Duplicate open prevention.
- Close.
- Wrong store/device.
- Permission denial.

---

# Phase 15 — POS Bootstrap API

Status: TODO

Depends on:

- Phase 3
- Phase 5
- Phase 6
- Phase 7
- Phase 8
- Phase 14

Goal:

Load POS startup state efficiently.

Endpoint:

```text
GET /api/pos/bootstrap
```

Response:

- organization
- store
- device
- user
- permissions
- features
- categories
- products
- tables
- printers
- print_routes
- active_shift

Tasks:

- [ ] Create DTO/resource response.
- [ ] Cache suitable catalog/config portions.
- [ ] Add invalidation after admin changes.
- [ ] Avoid N+1 queries.
- [ ] Ensure tenant/store scoping.

Acceptance criteria:

- POS loads required base state in one main request.
- Response contains no other tenant data.
- Product catalog is cached safely.

Tests:

- Response structure.
- Tenant isolation.
- Store isolation.
- Permissions/features.
- Cache invalidation.

---

# Phase 16 — Vue POS Core UI

Status: TODO

Depends on:

- Phase 15
- Phase 10
- Phase 12

Goal:

Create fast cashier-facing POS experience.

Pages:

- PosPage
- TablesPage
- OrdersPage
- DeliveryOrderPage
- PaymentPage
- Shift page/modal

Stores:

- auth
- context
- cart
- order
- sync placeholder

Tasks:

- [ ] Implement POS layout.
- [ ] Implement category selection.
- [ ] Implement product grid.
- [ ] Implement local cart in Pinia.
- [ ] Product click must not hit API.
- [ ] Implement DINE_IN entry.
- [ ] Implement TAKEAWAY entry.
- [ ] Implement DELIVERY entry.
- [ ] Implement open table/order.
- [ ] Implement item notes.
- [ ] Implement payment UI.
- [ ] Implement permission-based actions.
- [ ] Implement online status indicator.
- [ ] Keep touch targets large.
- [ ] Keep keyboard optional.

Acceptance criteria:

- New cashier can understand main flow quickly.
- Product add feels instant.
- No request per product click.
- All three order types usable.

Tests:

- Pinia cart unit tests.
- API service tests where valuable.
- Permission-based visibility tests if practical.

Manual acceptance:

- Complete one full dine-in scenario.
- Complete one takeaway scenario.
- Complete one delivery scenario.

---

# Phase 17 — QZ Tray POS Integration

Status: TODO

Depends on:

- Phase 8
- Phase 16

Goal:

Connect POS UI to local thermal printer.

Tasks:

- [ ] Implement QZ Tray adapter inside PrinterService.
- [ ] Printer discovery.
- [ ] Test print.
- [ ] Kitchen print.
- [ ] Customer receipt print.
- [ ] Print error UX.
- [ ] Reprint UX.
- [ ] Prepare structure for signed/silent production printing.

Acceptance criteria:

- Real printer receives kitchen ticket.
- Real printer receives customer receipt.
- Same physical printer may print both.
- Route change redirects kitchen ticket without code changes.

Manual tests required.

---

# Phase 18 — Organization Admin UX

Status: TODO

Depends on:

- prior domain phases

Goal:

Provide clean Filament organization management.

Resources/pages:

- Dashboard
- Stores
- Products
- Categories
- Tables
- Orders
- Customers
- Users
- Roles
- Printers
- Reports
- Settings

Tasks:

- [ ] Apply policies to every resource/page.
- [ ] Hide navigation where access is denied.
- [ ] Keep owner/manager flows simple.
- [ ] Add organization/store switch where required.
- [ ] Add printer route management.
- [ ] Add user/store assignment.
- [ ] Add role/permission editing.

Acceptance criteria:

- Organization cannot see another organization.
- Menu matches permissions/features.
- Admin can configure operational setup without code changes.

Tests:

- Resource authorization.
- Cross-tenant listing protection.
- Store scoping.

---

# Phase 19 — Platform Admin UX

Status: TODO

Depends on:

- Phase 3
- Phase 4

Goal:

Manage SaaS customers.

Resources/pages:

- Organizations
- Plans
- Features
- Subscriptions
- Platform Users
- System Settings

Tasks:

- [ ] Organization onboarding UI.
- [ ] Subscription extension/activation.
- [ ] Plan management.
- [ ] Feature assignment.
- [ ] Organization status management.
- [ ] Basic support visibility.

Acceptance criteria:

- New customer can be provisioned from platform panel.
- Subscription can be managed manually.
- Platform authorization is separate from organization authorization.

Tests:

- Platform-only access.
- Onboarding.
- Subscription updates.

---

# Phase 20 — Audit Log

Status: TODO

Depends on:

- core transaction phases

Goal:

Record critical administrative and financial changes.

Database:

- audit_logs

Track:

- order.cancel
- payment.refund when added
- product.price_changed
- user.role_changed
- role.permissions_changed
- printer.changed
- print_route.changed
- store.changed
- subscription.changed

Tasks:

- [ ] Create audit storage.
- [ ] Add service/action integration.
- [ ] Avoid noisy read logging.
- [ ] Avoid sensitive unnecessary data.

Acceptance criteria:

- Owner/platform admin can determine who changed critical configuration.
- Audit data is tenant-safe.

Tests:

- Critical change creates audit.
- Correct actor/tenant.
- No cross-tenant reads.

---

# Phase 21 — MVP Reports

Status: TODO

Depends on:

- Phase 10
- Phase 12
- Phase 13

Goal:

Provide useful basic business reporting.

Reports:

- Revenue
- Order count
- Average check
- Payment breakdown
- Order type breakdown
- Top products
- Store filter

Tasks:

- [ ] Implement optimized report queries.
- [ ] Add date filter.
- [ ] Add store filter.
- [ ] Add authorization.
- [ ] Avoid expensive unbounded queries.
- [ ] Add indexes if query plan requires them.

Acceptance criteria:

- Owner can understand today's business performance.
- Multi-store owner can filter by store.
- Reports never mix tenant data.

Tests:

- Revenue totals.
- Payment breakdown.
- Top products.
- Store filters.
- Tenant isolation.

---

# Phase 22 — Performance Hardening

Status: TODO

Depends on:

- MVP feature phases

Goal:

Ensure POS and admin remain responsive.

Tasks:

- [ ] Review query counts.
- [ ] Fix N+1 queries.
- [ ] Add indexes based on actual query patterns.
- [ ] Cache POS catalog/bootstrap configuration where appropriate.
- [ ] Add cache invalidation.
- [ ] Ensure pagination on admin lists.
- [ ] Review Redis usage.
- [ ] Review queue boundaries.
- [ ] Ensure order/payment writes remain synchronous.

Acceptance criteria:

- POS product clicks are instant/local.
- Bootstrap query count is reasonable.
- Common order/report queries use suitable indexes.

---

# Phase 23 — Security and Tenant Hardening

Status: TODO

Depends on:

- all major modules

Goal:

Prevent tenant and authorization failures before launch.

Tasks:

- [ ] Attempt IDOR across organizations.
- [ ] Attempt cross-store access.
- [ ] Attempt permission bypass through direct API.
- [ ] Attempt expired subscription bypass.
- [ ] Review mass assignment.
- [ ] Review organization/store ID handling.
- [ ] Review device access.
- [ ] Review printer route access.
- [ ] Review rate limiting where useful.
- [ ] Review sensitive logs.

Acceptance criteria:

- No tested cross-tenant data leak.
- No direct-request permission bypass.
- No client-provided organization ID can switch tenant context.

Tests:

- Dedicated security feature test suite.

---

# Phase 24 — Offline Compatibility Review

Status: TODO

Depends on:

- core MVP complete

Goal:

Verify current MVP can later gain offline synchronization without redesign.

This phase does NOT implement full offline mode.

Review:

- [ ] Transactional ULIDs.
- [ ] device_id coverage.
- [ ] idempotent order create.
- [ ] idempotent order item create.
- [ ] idempotent payment create.
- [ ] updated_at availability.
- [ ] inactive/soft-deactivation behavior.
- [ ] POS service abstractions.
- [ ] StorageService placeholder.
- [ ] SyncService placeholder.
- [ ] display number strategy.
- [ ] print flow independence from internet after local data exists.

Acceptance criteria:

A future IndexedDB + SyncEngine can be added without replacing core backend domain models.

---

# Phase 25 — Launch Readiness

Status: TODO

Depends on:

- all prior MVP phases

Goal:

Prepare first real fast food/cafe installation.

Tasks:

- [ ] Production env configuration.
- [ ] Nginx configuration.
- [ ] PostgreSQL backup process.
- [ ] Redis configuration.
- [ ] Supervisor workers.
- [ ] Laravel optimization.
- [ ] Filament optimization.
- [ ] Build Vue production assets.
- [ ] QZ Tray installation instructions.
- [ ] Printer setup instructions.
- [ ] First organization onboarding.
- [ ] First store setup.
- [ ] First cashier setup.
- [ ] Real printer test.
- [ ] Dine-in acceptance test.
- [ ] Takeaway acceptance test.
- [ ] Delivery acceptance test.
- [ ] Subscription expiry acceptance test.
- [ ] Tenant isolation final test.

Launch acceptance:

A real cafe/fast food can:

1. Login.
2. Open a shift.
3. Create dine-in order.
4. Print kitchen ticket.
5. Add later items and print only new items.
6. Accept payment.
7. Print receipt.
8. Create takeaway order.
9. Create delivery order with phone/address.
10. View daily reports.
11. Manage users/roles.
12. Configure printers from UI.
13. Operate without exposing another organization's data.

---

# Suggested Commit Boundaries

Use small meaningful commits where practical.

Examples:

```text
feat: bootstrap laravel 13 pos monolith
feat: add organization and store tenancy
feat: add organization scoped roles and permissions
feat: add subscription plans and feature gates
feat: add organization onboarding
feat: add product catalog
feat: add store tables
feat: add pos devices
feat: add configurable printer routing
feat: add order domain
feat: add incremental kitchen printing
feat: add payments
feat: add cashier shifts
feat: add pos bootstrap api
feat: add vue pos workflow
feat: integrate qz tray printing
feat: add organization reports
test: harden tenant isolation
```

---

# Codex Execution Instruction

When asked to continue implementation:

1. Read `docs/TZ.md`.
2. Read this file.
3. Find the first phase that is not `DONE` and whose dependencies are `DONE`.
4. Mark it `IN_PROGRESS`.
5. Implement that phase only.
6. Run relevant tests.
7. Run Pint.
8. Update this file to `DONE` only after acceptance criteria pass.
9. Continue to the next unblocked phase only if explicitly instructed to continue automatically.
10. Never add out-of-scope modules without explicit user instruction.
