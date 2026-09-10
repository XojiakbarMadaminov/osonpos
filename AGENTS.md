# AGENTS.md

## Source of Truth

`docs/TZ.md` is the primary source of truth for this repository.

Before implementing or changing architecture, read the relevant sections of `docs/TZ.md`.

`docs/IMPLEMENTATION_PLAN.md` defines the implementation order and completion status.

If these files conflict:

1. `docs/TZ.md` wins for product scope and architecture.
2. `docs/IMPLEMENTATION_PLAN.md` wins for task order.
3. Existing code wins only when it is compatible with both documents.

Do not silently expand product scope.

---

## Core Stack

- Laravel 13
- PHP 8.4
- PostgreSQL 17
- Redis
- Filament 4
- Vue 3
- TypeScript
- Pinia
- Tailwind CSS
- Vite
- Laravel Sanctum
- Spatie Laravel Permission with teams
- Filament Shield
- QZ Tray
- Pest
- Laravel Pint

---

## Architecture

This project is a Laravel modular monolith.

Use:

```text
/platform → SaaS Platform Filament panel
/admin    → Organization Filament panel
/pos      → Vue POS application
/api/pos  → POS API
```

Keep everything in one repository and one Laravel application.

Do not introduce:

- microservices
- separate frontend repository
- separate tenant databases
- event-driven infrastructure that is not required
- unnecessary abstractions

---

## Scope Rules

MVP does NOT include:

- ingredients
- recipes
- inventory
- stock
- purchases
- suppliers
- waste
- modifiers
- loyalty
- discounts unless explicitly added later
- KDS
- courier tracking
- QR ordering
- Telegram ordering
- online ordering
- full offline synchronization

Do not implement these unless explicitly requested.

Future compatibility is required where documented.

---

## Laravel Rules

Keep controllers thin.

Controllers should:

- accept validated input
- call an Action/service
- return a response

Business logic belongs in:

- Actions
- domain services
- models only for small domain behaviors

Use:

- Form Requests for validation
- Policies for authorization
- PHP Enums for business statuses
- DB transactions for critical writes
- ULIDs for transactional entities defined in TZ

Do not spread magic strings for statuses across the project.

---

## Multi-tenancy Rules

Organization is the tenant.

Store belongs to Organization.

Never trust `organization_id` from request input.

Never trust `store_id` without validating it against current organization and user access.

Use:

- TenantContext
- StoreContext
- DeviceContext

Every tenant-owned resource must enforce organization isolation.

Every store-owned resource must enforce store access.

Cross-tenant access must be covered by tests.

---

## Authorization Rules

Authorization is:

```text
active subscription
+ enabled feature
+ organization membership
+ permission
+ store access
+ resource ownership
```

Feature and permission are separate concepts.

Feature answers:

> Does the organization have this capability?

Permission answers:

> May this user use it?

Frontend hiding is not authorization.

All protected actions require backend authorization.

---

## Role Rules

Use Spatie Laravel Permission teams mode.

Team key:

```text
organization_id
```

Roles are organization-scoped.

Default roles:

- Owner
- Manager
- Cashier
- Waiter

Organization owner may change permissions from the admin UI.

---

## POS Rules

POS is Vue, not Filament.

Product clicks and cart calculations must be local frontend operations.

Do not send a backend request for every product click.

Use Pinia for POS state.

Use services for integration boundaries:

- ApiService
- PrinterService
- StorageService
- SyncService

Vue components must not directly contain QZ Tray implementation details.

---

## Offline-readiness Rules

Full offline sync is not part of MVP.

However, new code must preserve compatibility with future offline POS.

Required foundations:

- ULID identifiers
- device_id where defined
- idempotent create operations
- updated_at
- inactive/soft-deactivation strategy
- API-first POS
- PrinterService abstraction
- StorageService abstraction
- SyncService placeholder

Do not make architecture choices that require a redesign to add IndexedDB later.

---

## Printing Rules

Never hardcode physical printer names in business logic.

Use logical print types.

Initial print types:

- CUSTOMER_RECEIPT
- KITCHEN_TICKET

Printer routing is database-configured.

QZ Tray implementation belongs inside PrinterService.

Print failures must not rollback a saved order or saved payment.

Kitchen ticket and customer receipt are separate logical documents even when they use the same physical printer.

Reprints must be clearly marked `REPRINT`.

---

## Money Rules

Do not use floating-point storage for money.

For UZS, use integer amounts.

Examples:

```text
65000
120000
```

Order totals, payments and delivery fees must use the same money representation.

---

## Transaction Data Rules

Do not physically delete:

- orders
- payments
- shifts
- other financial history

Use status transitions such as:

- CANCELLED
- REFUNDED
- CLOSED

Master data such as products/categories/tables should normally use `is_active`.

---

## Order Rules

Order types:

- DINE_IN
- TAKEAWAY
- DELIVERY

Order status and payment status are independent.

Kitchen printing is incremental.

After an item has been printed to kitchen, later additions must create new printable items rather than reprinting all prior items.

---

## API Rules

POS API must be consistent and simple.

Create operations that may later be replayed during sync must be idempotent.

Duplicate payment creation must be prevented.

Use transactions for:

- order creation where required
- payment creation
- payment status calculation
- completing orders
- closing shifts
- critical cancellation flows

---

## Performance Rules

POS responsiveness is a product requirement.

Avoid unnecessary DB queries and network calls.

Use indexes for common tenant/store/status/date access patterns.

Use Redis for:

- cache
- queues
- locks
- rate limiting

Do not queue critical interactive writes such as order creation or payment creation.

---

## Testing Rules

Every completed module must include relevant tests.

Critical mandatory areas:

- tenant isolation
- store access
- permissions
- expired subscription blocking
- order flows
- payment totals
- duplicate payment protection
- incremental kitchen printing
- printer routing

Run:

```bash
php artisan test
vendor/bin/pint --test
```

Before marking a module complete.

Fix failures before continuing.

---

## Implementation Workflow

Follow `docs/IMPLEMENTATION_PLAN.md`.

Work in dependency order.

For each task:

1. Read relevant TZ sections.
2. Implement only the required scope.
3. Add/update tests.
4. Run relevant tests.
5. Run Pint.
6. Update task status in `docs/IMPLEMENTATION_PLAN.md`.
7. Continue to the next unblocked task.

Do not skip foundational phases.

---

## Completion Standard

A task is complete only when:

- implementation exists
- relevant tests pass
- formatting passes
- tenant isolation is verified where relevant
- authorization is verified where relevant
- migrations are valid
- implementation plan status is updated

Prefer simple maintainable Laravel solutions over speculative architecture.
