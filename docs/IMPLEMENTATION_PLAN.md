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

Status: DONE

Goal:

Create the Laravel 13 foundation and development tooling.

Tasks:

- [x] Create Laravel 13 project.
- [x] Configure PHP 8.4 requirements.
- [x] Configure PostgreSQL 17.
- [x] Configure Redis.
- [x] Install Filament 4.
- [x] Install Laravel Sanctum.
- [x] Install Spatie Laravel Permission.
- [x] Enable Spatie teams support with `organization_id`.
- [x] Install/configure Filament Shield.
- [x] Install Pest.
- [x] Configure Pint.
- [x] Install Vue 3 + TypeScript + Pinia + Tailwind + Vite.
- [x] Create `/platform`, `/admin`, `/pos` entry points.
- [x] Add project folders from TZ.
- [x] Add CI-safe `.env.example`.

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

Status: DONE

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

- [x] Create migrations.
- [x] Create models and relations.
- [x] Add organization membership relation to User.
- [x] Add store access relation to User.
- [x] Implement current organization resolution.
- [x] Implement current store resolution.
- [x] Add middleware/context initialization.
- [x] Prevent user-selected tenant IDs from becoming security source.
- [x] Add indexes for organization/store relations.

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

Status: DONE

Depends on:

- Phase 1

Goal:

Implement organization-scoped authorization.

Tasks:

- [x] Configure Spatie teams with `organization_id`.
- [x] Define permission naming convention.
- [x] Add default permissions seeder.
- [x] Add default roles:
  - Owner
  - Manager
  - Cashier
  - Waiter
- [x] Create organization onboarding role assignment.
- [x] Configure Filament Shield for admin panel.
- [x] Create reusable authorization helpers.
- [x] Add store access checks.
- [x] Add base policy conventions.
- [x] Add role management UI in organization admin.
- [x] Ensure menu visibility follows authorization.
- [x] Ensure backend policies remain authoritative.

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

Status: DONE

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

- [x] Create migrations/models.
- [x] Create plan management in `/platform`.
- [x] Create feature management in `/platform`.
- [x] Create subscription management in `/platform`.
- [x] Implement `organizationHasFeature()` style service/helper.
- [x] Implement active subscription check.
- [x] Block new POS transactions for expired subscriptions.
- [x] Keep admin read access available when subscription expires.
- [x] Create starter seed plans/features if useful.
- [x] Add max_stores and max_users enforcement.

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

Status: DONE

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

- [x] Create onboarding Action.
- [x] Create platform form/page for onboarding.
- [x] Assign owner membership.
- [x] Assign Owner role in organization context.
- [x] Create first store.
- [x] Apply selected plan/subscription.
- [x] Validate plan user/store limits.
- [x] Make process transactional where appropriate.

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

Status: DONE

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

- [x] Create category migration/model.
- [x] Create product migration/model.
- [x] Add `organization_id`.
- [x] Add `is_active`.
- [x] Add `sort_order`.
- [x] Store product prices as integer UZS.
- [x] Create admin Filament resources.
- [x] Add policies.
- [x] Add plan/feature checks only if required by TZ.
- [x] Add catalog cache strategy for POS bootstrap.
- [x] Invalidate cache after catalog changes.

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

Status: DONE

Depends on:

- Phase 1
- Phase 2

Goal:

Support dine-in table selection.

Database:

- tables

Tasks:

- [x] Create migration/model.
- [x] Add organization/store ownership.
- [x] Create Filament resource.
- [x] Add policy.
- [x] Implement active-order-derived table occupancy service/query.
- [x] Do not persist duplicated `occupied` state.

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

Status: DONE

Depends on:

- Phase 1
- Phase 2

Goal:

Represent POS terminals as first-class entities for printing and future offline support.

Database:

- devices

Tasks:

- [x] Create ULID device model.
- [x] Add organization/store ownership.
- [x] Add name/code/is_active/last_seen_at.
- [x] Implement DeviceContext.
- [x] Create registration/setup API.
- [x] Create admin device listing.
- [x] Create POS device setup shell.
- [x] Validate device belongs to current tenant/store.

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

Status: DONE

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

- [x] Create printer migration/model.
- [x] Create print route migration/model.
- [x] Add organization/store/device ownership where defined.
- [x] Create admin Filament resources.
- [x] Implement PrinterRoutingService.
- [x] Create PrinterService interface on POS frontend.
- [x] Isolate QZ Tray implementation.
- [x] Implement printer discovery on POS device.
- [x] Implement test print.
- [x] Implement device-local printer binding.
- [x] Allow both print types to route to one physical printer.
- [x] Allow admin to route kitchen ticket to another printer later.
- [x] Add reprint support marker foundation.

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

Status: DONE

Depends on:

- Phase 1

Goal:

Support minimal delivery customer information.

Database:

- customers
- delivery_details

Tasks:

- [x] Create ULID customer model.
- [x] Create delivery details model.
- [x] Add tenant/store ownership as defined in TZ.
- [x] Customer phone required where customer is used.
- [x] Delivery address snapshot stored on order.
- [x] Create admin customer list if useful.
- [x] Create POS customer lookup by phone.
- [x] Keep MVP fields minimal.

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

Status: DONE

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

- [x] Create ULID orders.
- [x] Create ULID order_items.
- [x] Add snapshot `product_name`.
- [x] Add snapshot `unit_price`.
- [x] Add `kitchen_printed_at`.
- [x] Implement store-level display number generation.
- [x] Implement DINE_IN.
- [x] Implement TAKEAWAY.
- [x] Implement DELIVERY.
- [x] Validate table belongs to current store.
- [x] Validate customer belongs to current organization.
- [x] Implement idempotent order creation.
- [x] Implement idempotent order item creation.
- [x] Use DB transactions where required.
- [x] Add policies.
- [x] Do not implement inventory/modifiers.

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

Status: DONE

Depends on:

- Phase 8
- Phase 10

Goal:

Send only unprinted order items to the kitchen.

Actions:

- PrepareKitchenTicket
- MarkKitchenItemsPrinted

Tasks:

- [x] Query only `kitchen_printed_at IS NULL`.
- [x] Build kitchen ticket DTO/payload.
- [x] Resolve KITCHEN_TICKET printer route.
- [x] Trigger frontend PrinterService.
- [x] Mark items printed only after client-confirmed successful print.
- [x] Preserve order if print fails.
- [x] Add REPRINT flow.
- [x] Ensure reprint is visibly marked.

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

Status: DONE

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

- [x] Create ULID payments.
- [x] Add idempotency.
- [x] Prevent duplicate payment replay.
- [x] Calculate paid amount from payment records.
- [x] Calculate remaining amount.
- [x] Update payment status transactionally.
- [x] Support multiple payment records per order.
- [x] Add payment policies.
- [x] Do not physically delete payments.

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

Status: DONE

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

- [x] Complete DINE_IN after payment rules are satisfied.
- [x] Complete TAKEAWAY according to MVP flow.
- [x] Support delivery unpaid/paid lifecycle defined by TZ.
- [x] Resolve CUSTOMER_RECEIPT printer route.
- [x] Print receipt through PrinterService.
- [x] Preserve payment/order if printer fails.
- [x] Free table when dine-in order is completed/cancelled.
- [x] Support receipt reprint with REPRINT marker.

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

Status: IN_PROGRESS

Depends on:

- Phase 7
- Phase 12

Goal:

Provide complete MVP cashier shift control and reconciliation.

Database:

- shifts
- payments.shift_id

Enum:

- ShiftStatus

Actions:

- OpenShift
- CloseShift

Tasks:

- [x] Create ULID shifts.
- [x] Link store/device/user.
- [x] Add opening_cash.
- [x] Add closing_cash.
- [x] Prevent multiple invalid active shifts for same business rule.
- [x] Require active shift for POS operations as defined.
- [x] Associate cash payments with current operational context.
- [x] Add shift open/close POS UI.
- [x] Add permissions.
- [x] Associate every payment made during a shift with that shift.
- [x] Calculate payment-method totals, expected cash, and closing difference.
- [x] Block closing while the current device has open orders.
- [x] Add detailed shift summary and reconciliation to POS.
- [x] Add tenant-safe, read-only admin shift history with status/store/date filters.

Acceptance criteria:

- Cashier can open shift.
- Cashier can close shift.
- POS shows current shift.
- Invalid shift state blocks required operations.
- Closing cash difference is calculated from opening cash and cash payments.
- A device with open orders cannot close its shift.
- Owner/Manager can review accessible-store shifts; Cashier can review only own shifts.
- Shift history cannot be edited or deleted.

Tests:

- Open.
- Duplicate open prevention.
- Close.
- Wrong store/device.
- Permission denial.
- Payment association and totals.
- Expected cash and difference.
- Open-order close blocking.
- Admin tenant/store/user isolation.

---

# Phase 15 — POS Bootstrap API

Status: DONE

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

- [x] Create DTO/resource response.
- [x] Cache suitable catalog/config portions.
- [x] Add invalidation after admin changes.
- [x] Avoid N+1 queries.
- [x] Ensure tenant/store scoping.

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

Status: DONE

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

- [x] Implement POS layout.
- [x] Implement category selection.
- [x] Implement product grid.
- [x] Implement local cart in Pinia.
- [x] Product click must not hit API.
- [x] Implement DINE_IN entry.
- [x] Implement TAKEAWAY entry.
- [x] Implement DELIVERY entry.
- [x] Implement open table/order.
- [x] Implement item notes.
- [x] Implement payment UI.
- [x] Implement permission-based actions.
- [x] Implement online status indicator.
- [x] Keep touch targets large.
- [x] Keep keyboard optional.

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

Status: DONE

Depends on:

* Phase 8
* Phase 16

Goal:

Connect the Vue POS application to local thermal printers through QZ Tray and support kitchen ticket and customer receipt printing through configurable printer routes.

Tasks:

* [x] Install the official QZ Tray npm package:

```bash
npm install qz-tray
```

* [x] Import and use `qz-tray` only inside the POS printer integration layer.
* [x] Vue components must not import or call `qz-tray` directly.
* [x] Implement the QZ Tray adapter inside `PrinterService`.
* [x] Implement QZ Tray WebSocket connection handling.
* [x] Implement reconnect logic when QZ Tray is not connected.
* [x] Implement printer discovery using QZ Tray.
* [x] Display available local OS printers in `/pos/device-setup`.
* [x] Allow an authorized user to select and save the physical printer mapping.
* [x] Implement test print.
* [x] Implement kitchen ticket printing.
* [x] Implement customer receipt printing.
* [x] Resolve the physical printer from database-configured `print_routes`.
* [x] Support `CUSTOMER_RECEIPT` and `KITCHEN_TICKET` using the same physical printer.
* [x] Support switching `KITCHEN_TICKET` to another physical printer without application code changes.
* [x] Implement ESC/POS-compatible raw printing where supported by the configured thermal printer.
* [x] Support paper cutting where the printer supports ESC/POS cutter commands.
* [x] Implement print error UX.
* [x] Print failure must not rollback or delete the saved order.
* [x] Print failure must not rollback or delete a saved payment.
* [x] Implement manual retry/reprint.
* [x] Reprinted tickets and receipts must contain a visible `REPRINT` marker.
* [x] For kitchen printing, confirm successful client-side printing before marking related order items as printed.
* [x] Keep QZ Tray-specific implementation isolated so another printer bridge can be introduced later without changing POS business logic.
* [x] Prepare production structure for signed/silent QZ Tray printing.
* [x] Keep QZ private signing keys server-side only.
* [x] Do not expose private signing keys to Vue, browser JavaScript, or public assets.
* [x] Add a backend signing endpoint/service when production silent printing is enabled.

Suggested frontend structure:

```text
resources/js/pos/services/
├── printer.ts
└── printers/
    └── qz-tray.ts
```

Conceptual responsibility:

```text
Vue Component
    ↓
PrinterService
    ↓
QzTrayAdapter
    ↓
qz-tray npm package
    ↓
QZ Tray desktop application
    ↓
Local thermal printer
```

Printer connection flow:

```text
POS
↓
PrinterService.connect()
↓
QZ Tray WebSocket
↓
Local QZ Tray application
↓
Installed OS printers
```

Kitchen printing flow:

```text
Order saved
↓
Request kitchen print payload
↓
Resolve KITCHEN_TICKET print route
↓
PrinterService
↓
QZ Tray
↓
Physical printer
↓
Print success
↓
Confirm print to backend
↓
Set kitchen_printed_at
```

Customer receipt flow:

```text
Payment saved
↓
Request receipt payload
↓
Resolve CUSTOMER_RECEIPT print route
↓
PrinterService
↓
QZ Tray
↓
Physical printer
```

Acceptance criteria:

* `qz-tray` npm package is installed and used only through the printer integration layer.
* POS can detect whether QZ Tray is connected.
* POS can reconnect to QZ Tray after connection loss.
* POS can list printers installed on the local operating system.
* An authorized user can configure printer selection from `/pos/device-setup`.
* Test print works on a real thermal printer.
* Real printer receives kitchen ticket.
* Real printer receives customer receipt.
* Same physical printer may print both `CUSTOMER_RECEIPT` and `KITCHEN_TICKET`.
* Admin can change `KITCHEN_TICKET` routing to another printer without code changes.
* Kitchen ticket includes only items that have not already been successfully printed.
* Failed print keeps the order/payment safely stored.
* User can retry failed printing.
* Reprinted output contains `REPRINT`.
* Vue components contain no direct QZ Tray implementation.
* Production architecture is ready for signed/silent printing without exposing the private key to the client.

Manual tests required:

1. QZ Tray installed and running.
2. POS connects to QZ Tray.
3. POS lists local printers.
4. Test print succeeds.
5. Kitchen ticket prints.
6. Customer receipt prints.
7. Both print types work on one physical printer.
8. Kitchen route is changed to a second printer and works without code changes.
9. QZ Tray is stopped and POS shows a connection error.
10. QZ Tray is restarted and POS reconnects.
11. Printer is unavailable and order remains saved.
12. Reprint works and includes `REPRINT`.
13. Incremental kitchen printing does not reprint previously printed items.

---


# Phase 18 — Organization Admin UX

Status: DONE

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

- [x] Apply policies to every resource/page.
- [x] Hide navigation where access is denied.
- [x] Keep owner/manager flows simple.
- [x] Add organization/store switch where required.
- [x] Add printer route management.
- [x] Add user/store assignment.
- [x] Add role/permission editing.

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

Status: DONE

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

- [x] Organization onboarding UI.
- [x] Subscription extension/activation.
- [x] Plan management.
- [x] Feature assignment.
- [x] Organization status management.
- [x] Basic support visibility.

Acceptance criteria:

- New customer can be provisioned from platform panel.
- Subscription can be managed manually.
- Platform authorization is separate from organization authorization.

Tests:

- Platform-only access.
- Onboarding.
- Subscription updates.

---

# Phase 20 — Removed from MVP

Status: DONE

Audit logging was explicitly removed from the MVP. No audit models, tables,
resources, observers, navigation, or application integrations remain.

---

# Phase 21 — MVP Reports

Status: DONE

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

- [x] Implement optimized report queries.
- [x] Add date filter.
- [x] Add store filter.
- [x] Add authorization.
- [x] Avoid expensive unbounded queries.
- [x] Add indexes if query plan requires them.

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

Status: DONE

Depends on:

- MVP feature phases

Goal:

Ensure POS and admin remain responsive.

Tasks:

- [x] Review query counts.
- [x] Fix N+1 queries.
- [x] Add indexes based on actual query patterns.
- [x] Cache POS catalog/bootstrap configuration where appropriate.
- [x] Add cache invalidation.
- [x] Ensure pagination on admin lists.
- [x] Review Redis usage.
- [x] Review queue boundaries.
- [x] Ensure order/payment writes remain synchronous.

Acceptance criteria:

- POS product clicks are instant/local.
- Bootstrap query count is reasonable.
- Common order/report queries use suitable indexes.

---

# Phase 23 — Security and Tenant Hardening

Status: DONE

Depends on:

- all major modules

Goal:

Prevent tenant and authorization failures before launch.

Tasks:

- [x] Attempt IDOR across organizations.
- [x] Attempt cross-store access.
- [x] Attempt permission bypass through direct API.
- [x] Attempt expired subscription bypass.
- [x] Review mass assignment.
- [x] Review organization/store ID handling.
- [x] Review device access.
- [x] Review printer route access.
- [x] Review rate limiting where useful.
- [x] Review sensitive logs.

Acceptance criteria:

- No tested cross-tenant data leak.
- No direct-request permission bypass.
- No client-provided organization ID can switch tenant context.

Tests:

- Dedicated security feature test suite.

---

# Phase 24 — Offline Compatibility Review

Status: DONE

Depends on:

- core MVP complete

Goal:

Verify current MVP can later gain offline synchronization without redesign.

This phase does NOT implement full offline mode.

Review:

- [x] Transactional ULIDs.
- [x] device_id coverage.
- [x] idempotent order create.
- [x] idempotent order item create.
- [x] idempotent payment create.
- [x] updated_at availability.
- [x] inactive/soft-deactivation behavior.
- [x] POS service abstractions.
- [x] StorageService placeholder.
- [x] SyncService placeholder.
- [x] display number strategy.
- [x] print flow independence from internet after local data exists.

Acceptance criteria:

A future IndexedDB + SyncEngine can be added without replacing core backend domain models.

---

# Phase 25 — Launch Readiness

Status: DONE

Depends on:

- all prior MVP phases

Goal:

Prepare first real fast food/cafe installation.

Tasks:

- [x] Production env configuration.
- [x] Nginx configuration.
- [x] PostgreSQL backup process.
- [x] Redis configuration.
- [x] Supervisor workers.
- [x] Laravel optimization.
- [x] Filament optimization.
- [x] Build Vue production assets.
- [x] QZ Tray installation instructions.
- [x] Printer setup instructions.
- [x] First organization onboarding.
- [x] First store setup.
- [x] First cashier setup.
- [x] Real printer test — physical output deferred by explicit user request because printer hardware is unavailable; code-level routing and document tests pass.
- [x] Dine-in acceptance test.
- [x] Takeaway acceptance test.
- [x] Delivery acceptance test.
- [x] Subscription expiry acceptance test.
- [x] Tenant isolation final test.

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
