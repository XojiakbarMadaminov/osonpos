# Fast Food / Cafe POS SaaS
## Technical Specification — MVP v1.0

Version: 1.0  
Architecture: Modular Monolith  
Backend: Laravel 13  
Admin: Filament 4  
POS: Vue 3 SPA, future PWA  
Database: PostgreSQL 17  
Cache / Queue: Redis  
Deployment: Single server, single domain, single application

---

# 1. Project Goal

Ko‘plab kichik va o‘rta fast food, cafe va kichik restoranlarga subscription asosida sotiladigan web-based POS SaaS yaratish.

Tizim bir vaqtning o‘zida quyidagi biznes modellarini qo‘llashi kerak:

- Dine-in / table service
- Takeaway
- Delivery
- Oldindan to‘lov
- Ovqatlangandan keyin to‘lov
- Bitta yoki bir nechta printer
- Bir organization ichida bir yoki bir nechta store
- Organization-specific roles va permissions
- Subscription va plan limitations

Birinchi versiyaning asosiy maqsadi:

> Kassir yoki ofitsiant mahsulotni tez tanlaydi, order yaratadi, oshxona uchun ticket chiqaradi, payment qabul qiladi va owner savdoni ko‘ra oladi.

MVP ortiqcha restoran boshqaruv funksiyalari bilan murakkablashtirilmasligi kerak.

---

# 2. Product Principles

## 2.1 Simplicity

Kassir uchun POS juda sodda bo‘lishi kerak.

Asosiy flow:

```text
Order tanlash
→ Product tanlash
→ Kitchen'ga yuborish
→ Payment
→ Close
```

Keraksiz input va modal oynalar ishlatilmasin.

## 2.2 SaaS-first

Har bir biznes alohida `Organization`.

Organization ichida bir yoki bir nechta `Store`.

```text
Platform
   │
   ├── Organization A
   │      ├── Store 1
   │      └── Store 2
   │
   └── Organization B
          └── Store 1
```

Bir organization boshqa organization ma'lumotlariga kira olmasligi shart.

## 2.3 Future-ready, not feature-heavy

Kelajakda kerak bo‘lishi aniq bo‘lgan architecture foundation hozir yaratiladi.

Lekin funksionallik MVP'ga kerak bo‘lmasa UI va business flow'ga kiritilmaydi.

Masalan offline POS uchun hozir:

- ULID
- device_id
- idempotency
- API-first

tayyorlanadi.

Lekin IndexedDB sync engine hozir yozilmaydi.

## 2.4 API-first POS

POS Vue frontend Laravel API bilan ishlaydi.

Vue componentlar database yoki Laravel implementation details haqida bilmasligi kerak.

---

# 3. MVP Scope

## Platform

- Organizations
- Plans
- Subscriptions
- Features
- Platform administrators

## Organization management

- Stores
- Users
- Roles
- Permissions
- Products
- Categories
- Tables
- Printers
- Printer routing
- Basic reports

## POS

- Dine-in orders
- Takeaway orders
- Delivery orders
- Open orders
- Add products
- Order item note
- Send order to kitchen printer
- Payment
- Customer receipt
- Order closing
- Basic order cancellation

## Operations

- Cashier shifts
- Devices / POS terminals

---

# 4. Explicitly Out of Scope for MVP

Quyidagi modullar v1'ga kiritilmaydi:

- Audit log — MVP v1.0 scope'idan olib tashlangan; audit storage, UI va event recording implement qilinmaydi
- Ingredients
- Recipes
- Inventory
- Stock movements
- Purchases
- Suppliers
- Waste
- Modifiers
- Kitchen Display System
- Courier management
- Courier GPS tracking
- Loyalty
- Bonus points
- Promo codes
- Complex discounts
- QR menu
- Telegram bot
- Online ordering website
- Accounting
- Payroll
- Advanced analytics
- Full offline sync
- Automatic Click/Payme subscription billing

Architecture keyinchalik ushbu modullarni qo‘shishga to‘sqinlik qilmasligi kerak.

---

# 5. Technology Stack

## Backend

- Laravel 13
- PHP 8.4
- PostgreSQL 17
- Redis

## Admin

- Filament 4
- Spatie Laravel Permission
- Filament Shield
- Laravel Policies

Spatie Permission `teams` mode ishlatiladi.

`organization_id` permission team sifatida ishlatiladi.

Shunday qilib bir user:

```text
Organization A → Manager
Organization B → Cashier
```

bo‘lishi mumkin.

## POS

- Vue 3
- TypeScript
- Pinia
- Tailwind CSS
- Vite

Kelajak:

- PWA
- IndexedDB
- Service Worker
- Sync Engine

## Authentication

- Laravel session authentication
- Laravel Sanctum

Bitta domain ishlatilgani sababli POS va admin bir Laravel authentication tizimidan foydalanadi.

## Printing

- QZ Tray
- ESC/POS
- Thermal printer

## Testing

- Pest
- Laravel feature tests
- Vue unit tests where necessary

## Server

- Ubuntu
- Nginx
- PHP-FPM
- PostgreSQL
- Redis
- Supervisor

---

# 6. Application Architecture

Bitta Laravel monolith ishlatiladi.

```text
example.uz
│
├── /platform
│      Filament Platform Panel
│
├── /admin
│      Filament Organization Panel
│
├── /pos
│      Vue POS
│
└── /api/pos/*
       Laravel POS API
```

Bitta repository, application, domain, server, database va authentication system ishlatiladi.

Microservice ishlatilmaydi.

---

# 7. Laravel Project Structure

```text
app/
├── Actions/
│   ├── Orders/
│   ├── Payments/
│   ├── Printing/
│   ├── Shifts/
│   └── Organizations/
│
├── Domain/
│   ├── Organization/
│   ├── Subscription/
│   ├── Store/
│   ├── Catalog/
│   ├── Order/
│   ├── Payment/
│   ├── Printing/
│   └── Authorization/
│
├── Filament/
│   ├── Platform/
│   └── Admin/
│
├── Http/
│   ├── Controllers/
│   │   └── Api/Pos/
│   ├── Middleware/
│   └── Requests/
│
├── Models/
├── Policies/
└── Support/
    ├── TenantContext.php
    ├── StoreContext.php
    └── DeviceContext.php
```

Controllers business logic saqlamasligi kerak.

Flow:

```text
Controller
   ↓
Request validation
   ↓
Action
   ↓
Domain / Model
```

---

# 8. Frontend Structure

```text
resources/js/pos/
├── components/
├── layouts/
├── pages/
│   ├── PosPage.vue
│   ├── TablesPage.vue
│   ├── OrdersPage.vue
│   ├── DeliveryOrderPage.vue
│   ├── PaymentPage.vue
│   └── DeviceSetupPage.vue
│
├── stores/
│   ├── auth.ts
│   ├── cart.ts
│   ├── context.ts
│   ├── order.ts
│   └── sync.ts
│
├── services/
│   ├── api.ts
│   ├── printer.ts
│   ├── storage.ts
│   └── sync.ts
│
├── types/
└── app.ts
```

Vue component ichida to‘g‘ridan-to‘g‘ri `fetch()` yoki printer implementation yozilmasin.

Buning o‘rniga:

- api service
- printer service
- storage service

ishlatilsin.

Bu keyinchalik offline implementation qo‘shishni osonlashtiradi.

---

# 9. Core Tenant Model

## Organization

Organization SaaS mijoz hisoblanadi.

Misol:

- Maryam Fast Food
- Burger House
- Coffee Time

## Store

Organization filiali.

```text
Maryam Fast Food
├── Chilonzor
├── Sergeli
└── Yunusobod
```

Relation:

```text
Organization
   hasMany
Store
```

---

# 10. Main Database Entities

## organizations

```text
id
name
slug
phone
status
created_at
updated_at
```

## stores

```text
id
organization_id
name
address
phone
timezone
is_active
created_at
updated_at
```

## organization_user

```text
organization_id
user_id
created_at
```

## store_user

```text
store_id
user_id
created_at
```

Owner barcha store'larga accessga ega bo‘lishi mumkin.

---

# 11. Subscription Model

Subscription userga emas, organizationga tegishli.

```text
Organization
   ↓
Subscription
   ↓
Plan
   ↓
Features
```

## plans

```text
id
name
code
price
billing_period
max_stores
max_users
is_active
```

## subscriptions

```text
id
organization_id
plan_id
status
starts_at
ends_at
created_at
updated_at
```

Status:

- TRIAL
- ACTIVE
- EXPIRED
- CANCELLED

## features

```text
id
code
name
```

Kelajakdagi feature misollari:

- pos
- tables
- delivery
- multi_store
- inventory
- loyalty
- advanced_reports

## plan_features

```text
plan_id
feature_id
```

---

# 12. MVP Subscription Rules

MVP'da subscription payment gateway bilan avtomatik ulanmaydi.

Platform administrator quyidagilarni boshqaradi:

- Organization
- Plan
- Start date
- End date
- Status

Keyinchalik Click, Payme yoki boshqa billing provider qo‘shilishi mumkin.

Subscription tugaganda:

- organization data o‘chirilmaydi
- yangi POS transaction bloklanadi
- owner admin panelga kirishi mumkin
- subscription renewal sahifasi ko‘rsatilishi mumkin

---

# 13. Authorization Model

Authorization 4 qatlamdan iborat:

```text
Subscription Feature
        +
Permission
        +
Organization Access
        +
Store Access
        ↓
      ALLOW
```

## Role vs Permission

Role faqat permission collection.

Default rolelar:

- Owner
- Manager
- Cashier
- Waiter

Permission misollari:

```text
products.view
products.manage

orders.view
orders.create
orders.update
orders.cancel

payments.view
payments.create
payments.refund

tables.view
tables.manage

reports.view

printers.view
printers.manage

users.view
users.manage

roles.view
roles.manage

stores.view
stores.manage
```

---

# 14. Organization-specific Roles

Role organization scoped bo‘lishi kerak.

Spatie Teams feature ishlatiladi.

Team foreign key:

```text
organization_id
```

Organization yaratilganda default role templates yaratiladi:

- Owner
- Manager
- Cashier
- Waiter

Owner admin UI orqali permissions'ni o‘zgartira oladi.

---

# 15. Authorization Rules

Frontend'da buttonni yashirish security hisoblanmaydi.

Har bir backend operation Policy orqali tekshirilishi shart.

Masalan Order update:

```text
User has orders.update
AND
User belongs to current organization
AND
Order belongs to current organization
AND
User has access to order.store_id
```

Platform Super Admin alohida global authorization orqali ishlaydi.

---

# 16. Tenant Context

Request'dan kelgan:

- organization_id
- store_id

ga to‘g‘ridan-to‘g‘ri ishonilmaydi.

Current context server tomonidan aniqlanadi.

Ishlatiladi:

- TenantContext
- StoreContext
- DeviceContext

Tenant-owned modellarda `organization_id` mavjud bo‘lishi kerak.

Store-specific modellarda:

```text
organization_id
store_id
```

saqlanadi.

---

# 17. Filament Panels

## /platform

Faqat SaaS operatori uchun.

Sections:

- Organizations
- Plans
- Subscriptions
- Features
- Platform Users
- System Settings

## /admin

Organization owner/manager uchun.

Sections:

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

Menu user permission va organization subscription feature'lariga qarab avtomatik yashiriladi.

---

# 18. POS Device Model

Kelajakdagi offline va printer management uchun terminal boshidan entity sifatida mavjud bo‘ladi.

## devices

```text
id ULID
organization_id
store_id
name
code
is_active
last_seen_at
created_at
updated_at
```

Misollar:

- Main Cashier
- Second Cashier
- Waiter Tablet 1

POS login qilgandan keyin device bir store bilan bog‘lanadi.

---

# 19. Catalog

## categories

```text
id
organization_id
name
sort_order
is_active
created_at
updated_at
```

## products

```text
id
organization_id
category_id
name
price
is_active
sort_order
created_at
updated_at
```

MVP'da quyidagilar bo‘lmaydi:

- variants
- modifiers
- ingredients
- recipes
- stock

Product organizationga tegishli.

MVP'da organizationning barcha store'lari bir xil product katalogdan foydalanadi.

Store-specific product va price keyingi versiyada qo‘shilishi mumkin.

---

# 20. Tables

## tables

```text
id
organization_id
store_id
name
number
capacity nullable
is_active
created_at
updated_at
```

Table holatini alohida `occupied` boolean orqali boshqarish shart emas.

Active order mavjud bo‘lsa `OCCUPIED`, aks holda `FREE` deb hisoblanadi.

---

# 21. Customers

Customer faqat kerak bo‘lgan orderlarda majburiy.

## customers

```text
id ULID
organization_id
name nullable
phone
created_at
updated_at
```

Takeaway va dine-in uchun customer majburiy emas.

Delivery uchun minimum phone va address kerak.

---

# 22. Order Model

Order asosiy transactional entity.

## Order Types

- DINE_IN
- TAKEAWAY
- DELIVERY

## Order Status

- OPEN
- COMPLETED
- CANCELLED

## Payment Status

- UNPAID
- PARTIALLY_PAID
- PAID
- REFUNDED

Order status va payment status bir-biridan mustaqil.

Masalan:

```text
DINE_IN
status = OPEN
payment_status = UNPAID
```

normal holat.

---

# 23. orders Table

```text
id ULID
organization_id
store_id
device_id nullable
display_number
type
table_id nullable
customer_id nullable
status
payment_status
subtotal
delivery_fee
total
note nullable
created_by
closed_by nullable
opened_at
closed_at nullable
created_at
updated_at
```

Money `float` sifatida saqlanmasin.

UZS uchun integer amount ishlatiladi.

---

# 24. Order Number

Database primary key:

```text
ULID
```

User ko‘radigan number esa:

```text
display_number
```

Misol:

```text
#0152
```

MVP'da store-level sequential number server orqali yaratiladi.

`display_number` string sifatida saqlanishi kerak.

Bu kelajakda offline mode'da device-specific numberingga o'tish imkonini beradi.

---

# 25. Order Items

## order_items

```text
id ULID
organization_id
store_id
order_id
product_id nullable
product_name
quantity
unit_price
total
note nullable
kitchen_printed_at nullable
created_by
created_at
updated_at
```

`product_name` va `unit_price` snapshot sifatida saqlanadi.

Product keyinchalik narxi o‘zgarsa ham eski order o‘zgarmasligi kerak.

---

# 26. Kitchen Print Rule

Order item kitchen'ga chiqarilmaguncha o‘zgartirilishi mumkin.

`kitchen_printed_at != null` bo‘lgandan keyin item finalized hisoblanadi.

Keyin aynan shu product yana qo‘shilsa yangi `order_item` yaratiladi.

Keyingi kitchen ticketda faqat yangi itemlar chiqadi.

---

# 27. Dine-in Workflow

```text
Tables
↓
Table 5
↓
Create/Open Order
↓
Add Products
↓
Send Kitchen
↓
Kitchen Ticket Print
↓
Order remains OPEN
```

Mijoz yana mahsulot so‘rasa:

```text
Open Table 5
↓
Add Products
↓
Send Kitchen
```

faqat yangi itemlar print qilinadi.

Oxirida:

```text
Payment
↓
Customer Receipt
↓
Order COMPLETED
↓
Table FREE
```

---

# 28. Takeaway Workflow

```text
New Order
↓
TAKEAWAY
↓
Products
↓
Payment
↓
Customer Receipt
↓
Kitchen Ticket
↓
COMPLETED
```

KDS MVP'da mavjud emas.

---

# 29. Delivery Workflow

```text
New Order
↓
DELIVERY
↓
Customer phone
↓
Customer name optional
↓
Address
↓
Products
↓
Payment now OR later
↓
Kitchen Ticket
```

Delivery order payment qilinmagan holda ham yaratilishi mumkin.

---

# 30. Delivery Details

## delivery_details

```text
id ULID
organization_id
store_id
order_id
address
delivery_fee
note nullable
created_at
updated_at
```

MVP'da quyidagilar kerak emas:

- latitude
- longitude
- courier_id
- delivery tracking

Order vaqtida address snapshot saqlanadi.

---

# 31. Payments

Payment orderdan alohida entity.

## payments

```text
id ULID
organization_id
store_id
order_id
device_id nullable
method
amount
created_by
created_at
updated_at
```

Initial methods:

- CASH
- CARD
- CLICK
- PAYME
- OTHER

Order'da bir nechta payment record bo‘lishi mumkin.

Architecture split/mixed payment'ga tayyor.

---

# 32. Payment Calculation

```text
paid_amount = SUM(payments.amount)
remaining = order.total - paid_amount
```

Payment status:

```text
0 paid
→ UNPAID

0 < paid < total
→ PARTIALLY_PAID

paid >= total
→ PAID
```

Status payment transaction bilan bir DB transaction ichida yangilanishi kerak.

---

# 33. Cashier Shift

Shift kassirning bitta qurilmada ishlagan kassa davrini ifodalaydi. Moliyaviy tarix bo‘lgani uchun shift tahrirlanmaydi va fizik o‘chirilmaydi.

## shifts

```text
id ULID
organization_id
store_id
device_id
user_id
opening_cash
closing_cash nullable
opened_at
closed_at nullable
status
created_at
updated_at
```

Status:

- OPEN
- CLOSED

Bir vaqtda:

- bitta device uchun faqat bitta OPEN shift;
- bitta user uchun organization doirasida faqat bitta OPEN shift bo‘lishi mumkin.

Shift faqat joriy organization, store, device va user kontekstida ochiladi/yopiladi. Ochish va yopish transaction hamda database constraint bilan himoyalanadi.

## Shift payment bog‘lanishi

`payments` jadvalida nullable `shift_id` bo‘ladi.

- CASH payment uchun joriy OPEN shift majburiy.
- Shift ochiq paytda qabul qilingan CARD, CLICK, PAYME va OTHER paymentlar ham shu shiftga bog‘lanadi.
- Shift ochilmagan paytdagi non-cash payment backward-compatible tarzda `shift_id = null` bo‘lishi mumkin.
- Payment va shift organization, store, device va creator bo‘yicha bir xil kontekstga tegishli bo‘lishi shart.

## Shift hisoblari

Qiymatlar integer UZS ko‘rinishida hisoblanadi:

```text
cash_payments_total = shiftga bog‘langan CASH paymentlar yig‘indisi
payments_total = shiftga bog‘langan barcha paymentlar yig‘indisi
expected_cash = opening_cash + cash_payments_total
cash_difference = closing_cash - expected_cash
```

`expected_cash` va `cash_difference` bazada alohida saqlanmaydi, payment tarixidan hisoblanadi. MVPda cash in/out, expense va refund modullari yo‘q; shu sababli ular shift hisobiga kiritilmaydi.

## Shift yopish qoidalari

- `closing_cash` kassadagi real sanalgan pul bo‘lib, manfiy bo‘lishi mumkin emas.
- Joriy device'da OPEN order mavjud bo‘lsa shift yopilmaydi; order avval payment bilan yakunlanishi yoki bekor qilinishi kerak.
- Printer xatosi shift yoki paymentni rollback qilmaydi.
- Yopilgan shift qayta ochilmaydi va uning moliyaviy qiymatlari o‘zgartirilmaydi.

## UI va ko‘rish huquqi

POS `/pos` dagi Shift sahifasi OPEN holati, kassir, device, ochilgan vaqt, opening cash, payment method kesimidagi summalar, expected cash va closing difference'ni ko‘rsatadi.

Organization admin `/admin/shifts` sahifasi read-only tarixdir:

- Owner va Manager o‘ziga ruxsat berilgan store'lardagi shiftlarni ko‘radi.
- Cashier faqat o‘z shiftlarini ko‘radi.
- Boshqa organization yoki ruxsat berilmagan store shiftlari ko‘rinmaydi.
- Status, store va ochilgan sana bo‘yicha filter mavjud.

---

# 34. Printer Architecture

Physical printer kod ichida hardcoded qilinmaydi.

Logical print type ishlatiladi.

Initial print types:

- CUSTOMER_RECEIPT
- KITCHEN_TICKET

Keyinchalik boshqa print type'lar qo‘shilishi mumkin.

---

# 35. Printers

## printers

```text
id
organization_id
store_id
device_id nullable
name
system_name
paper_width
is_active
created_at
updated_at
```

`system_name` QZ Tray ko‘radigan OS printer nomi.

---

# 36. Print Routes

## print_routes

```text
id
organization_id
store_id
print_type
printer_id
created_at
updated_at
```

Initial configuration:

```text
CUSTOMER_RECEIPT → POS Printer
KITCHEN_TICKET   → POS Printer
```

Keyinchalik admin UI'dan alohida printerga yo‘naltirilishi mumkin.

---

# 37. Local Printer Configuration

Laravel server store ichidagi local USB/LAN printerni bevosita ko‘ra olmaydi.

Physical printer detection POS terminal orqali QZ Tray yordamida amalga oshiriladi.

Flow:

```text
POS Device
↓
QZ Tray
↓
Available printers
↓
Select printer
↓
Save binding
```

Admin Filament panel logical printer va routingni boshqaradi.

Actual `Detect Printers` va `Test Print` POS device'da bajariladi.

Masalan:

```text
/pos/device-setup
```

Bu sahifa `printers.manage` permission talab qiladi.

---

# 38. Printing Flow

Kitchen:

```text
Order saved
↓
Get unprinted items
↓
Resolve KITCHEN_TICKET route
↓
PrinterService
↓
QZ Tray
↓
Physical printer
↓
Successful
↓
kitchen_printed_at = now()
```

Customer receipt:

```text
Payment completed
↓
Resolve CUSTOMER_RECEIPT
↓
PrinterService
↓
QZ Tray
↓
Printer
```

Kitchen ticket va customer receipt alohida document bo‘lishi shart.

---

# 39. QZ Tray Isolation

Vue component QZ Tray API'ni to‘g‘ridan-to‘g‘ri chaqirmaydi.

Faqat `PrinterService` ishlatadi.

Conceptual interface:

- printKitchenTicket()
- printCustomerReceipt()
- findPrinters()
- testPrinter()

---

# 40. POS Interface

POS Filament'da yozilmaydi.

Vue SPA bo‘ladi.

Desktop/tablet-oriented UI.

Main layout:

```text
┌────────────────────────────────────────────────────────────┐
│ Organization | Store | User | Device | Online             │
├────────────┬────────────────────────────┬───────────────────┤
│ Categories │ Products                   │ Cart              │
│            │                            │                   │
│ Lavash     │ [Lavash] [Burger] [Fri]   │ 2 Lavash  56 000 │
│ Burger     │ [Cola]   [Water]          │ 1 Cola    10 000 │
│ Drinks     │                            │                   │
│            │                            │ TOTAL      66 000 │
│            │                            │ [ACTION]          │
└────────────┴────────────────────────────┴───────────────────┘
```

Product click server request qilmasligi kerak.

Cart Pinia local state'da ishlaydi.

---

# 41. POS Main Navigation

Minimal:

- POS
- Tables
- Orders
- Shift

Permissions bo‘yicha ayrim menu itemlar yashirilishi mumkin.

---

# 42. POS Bootstrap API

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

Bu data frontend state'ga yuklanadi.

---

# 43. Minimum POS API

```text
GET    /api/pos/bootstrap

GET    /api/pos/orders
GET    /api/pos/orders/{order}

POST   /api/pos/orders
PATCH  /api/pos/orders/{order}

POST   /api/pos/orders/{order}/items
POST   /api/pos/orders/{order}/send-kitchen

POST   /api/pos/orders/{order}/payments

POST   /api/pos/orders/{order}/complete
POST   /api/pos/orders/{order}/cancel

POST   /api/pos/shifts
POST   /api/pos/shifts/{shift}/close

POST   /api/pos/devices/register
```

Exact route naming implementation vaqtida Laravel convention'ga moslashtirilishi mumkin.

---

# 44. API Idempotency

Future offline support uchun create transactionlar duplicate-safe bo‘lishi kerak.

Client ULID yuborishi mumkin.

Aynan shu `id` bilan request qayta yuborilsa yangi record yaratilmasligi kerak.

Bu quyidagilarga qo‘llanadi:

- orders
- order_items
- payments

Payment duplicate bo‘lishi qat'iy oldi olinishi kerak.

---

# 45. Offline-ready Architecture

Full offline mode MVP'da implement qilinmaydi.

Lekin architecture quyidagilarga tayyor bo‘lishi shart:

- ULID primary identifiers
- device_id
- idempotent writes
- updated_at
- soft deactivation
- API-first POS
- PrinterService abstraction
- StorageService abstraction
- SyncService placeholder

Kelajak:

```text
Vue POS
↓
IndexedDB
↓
Local transaction queue
↓
SyncService
↓
Laravel API
```

---

# 46. Deletion Strategy

Transactional data physical delete qilinmaydi.

Masalan:

- orders
- payments
- shifts

delete qilinmasligi kerak.

Order `CANCELLED` qilinadi.

Product/category/table kabi master data uchun `is_active = false` afzal.

---

# 47. Reporting

MVP reports:

## Today

- Revenue
- Order count
- Average check

## Payment breakdown

- Cash
- Card
- Click
- Payme
- Other

## Order types

- Dine-in
- Takeaway
- Delivery

## Top products

- Product
- Quantity
- Revenue

## Store filter

Owner bir nechta store'ga ega bo‘lsa:

- All stores
- Store A
- Store B

filter ishlashi kerak.

---

# 48. Performance Requirements

POS responsiveness birinchi darajali requirement.

Product bosish network request qilmaydi.

Cart update instant local operation.

Server querylarda tenant/store filters indexed bo‘lishi kerak.

Muhim indexes:

- organization_id
- store_id
- created_at
- status
- payment_status
- type
- phone

Composite indexes real query pattern asosida qo‘shiladi.

Masalan:

```text
(store_id, status)
(store_id, created_at)
(organization_id, phone)
```

Large tables pagination ishlatishi shart.

---

# 49. Redis Usage

Redis:

- cache
- queue
- locks
- rate limiting

uchun ishlatiladi.

Order yaratish va payment creation queue'ga yuborilmaydi.

Queue:

- exports
- notifications
- webhooks
- heavy reports
- future integrations

uchun.

---

# 50. Database Transactions

Quyidagi operations `DB::transaction()` ichida bajarilishi kerak:

- Create order
- Create payment
- Update payment status
- Complete order
- Cancel critical transaction
- Close shift

---

# 51. Concurrency

Minimum protection:

- updated_at
- database transaction
- row locking where necessary

Critical operationlarda duplicate processing oldi olinadi.

Keyinchalik optimistic versioning qo‘shish mumkin.

---

# 52. Security

Har bir API request quyidagilarni tekshiradi:

- Authenticated?
- Current organization?
- Active subscription?
- Feature available?
- Permission?
- Store access?
- Resource belongs to tenant?

Frontend yuborgan `organization_id` security source sifatida ishlatilmaydi.

Mass assignment orqali tenant ID o‘zgartirib bo‘lmasligi kerak.

Cross-tenant resource access testlari majburiy.

---

# 53. Multi-tenancy Strategy

MVP:

```text
1 application
1 database
many organizations
```

Database-per-tenant ishlatilmaydi.

Tenant-owned records `organization_id` orqali ajratiladi.

Store-specific records `organization_id` va `store_id` orqali ajratiladi.

---

# 54. User Experience Rules

POS uchun:

- buttons katta bo‘lishi kerak
- mouse va touch bilan ishlashi kerak
- keyboard majburiy bo‘lmasligi kerak
- product add qilish 1 click
- payment imkon qadar kam click
- loading spinner ortiqcha ko‘rinmasligi kerak
- error message kassir tushunadigan tilda bo‘lishi kerak
- technical exception userga ko‘rsatilmasligi kerak

Admin panelning maqsadi management.

POS'ning maqsadi speed.

---

# 55. Error Handling

Print ishlamasa order yo‘qolmasligi kerak.

```text
Order saved
↓
Print failed
```

Natija:

```text
Order remains saved
Print error displayed
[REPRINT]
```

Print failure order transactionni rollback qilmasligi kerak.

Payment saqlangan bo‘lsa printer ishlamagani uchun payment rollback qilinmaydi.

---

# 56. Reprint

Permission:

```text
orders.reprint
```

orqali boshqarilishi mumkin.

Reprint ticketda `REPRINT` belgisi chiqishi kerak.

---

# 57. Default Organization Setup

Yangi organization yaratilganda onboarding action:

```text
Create Organization
↓
Create default subscription
↓
Create first Store
↓
Create default roles
↓
Assign Owner
↓
Create default permissions
↓
Create basic print routes
```

Default roles:

- Owner
- Manager
- Cashier
- Waiter

Default print routes:

- CUSTOMER_RECEIPT
- KITCHEN_TICKET

Printer hali ulanmagan bo‘lishi mumkin.

---

# 58. Default Permissions Concept

Owner:

- full organization access

Manager:

- products
- tables
- orders
- payments
- reports
- printers
- users limited

Cashier:

- pos
- orders
- payments
- tables view
- shift

Waiter:

- tables
- orders

Exact matrix implementation paytida seeder orqali belgilanadi.

Organization owner keyinchalik UI orqali o‘zgartira oladi.

---

# 59. Feature vs Permission Rule

Feature va Permission hech qachon aralashtirilmaydi.

Feature:

> Organization ushbu imkoniyatni sotib olganmi?

Permission:

> Shu user undan foydalanishi mumkinmi?

---

# 60. Code Standards

Business statuslar magic string sifatida tarqoq ishlatilmasin.

PHP Enum ishlatilsin.

Masalan:

- OrderType
- OrderStatus
- PaymentStatus
- PaymentMethod
- SubscriptionStatus
- PrintType

Business operations Action classlarda bo‘lsin.

Validation Form Request orqali.

Authorization Policy orqali.

Response POS API uchun izchil structure'da bo‘lsin.

---

# 61. Testing Requirements

Majburiy backend tests:

## Tenant isolation

Organization A user Organization B order'ni view/update/delete qila olmaydi.

## Store access

Cashier faqat biriktirilgan store orderlarini ishlata oladi.

## Permissions

Permission yo‘q user endpoint orqali action bajara olmaydi.

## Subscription

Expired organization yangi transaction yarata olmaydi.

## Order

Har uch order turi test qilinadi:

- DINE_IN
- TAKEAWAY
- DELIVERY

## Payment

- unpaid
- partial
- paid
- duplicate payment request

test qilinadi.

## Printing

Printer routing service mocked test orqali tekshiriladi.

Actual hardware test manual integration test sifatida bajariladi.

## Kitchen incremental printing

Oldin print qilingan item qayta kitchen ticketga tushmasligi tekshiriladi.

---

# 62. Logging and Monitoring

Production'da minimum:

- Laravel logs
- failed jobs
- authentication failures
- critical transaction failures
- print client errors where reportable

Sensitive payment/customer data unnecessary log qilinmasligi kerak.

---

# 63. Deployment

Initial production:

```text
Single VPS
│
├── Nginx
├── PHP-FPM
├── Laravel 13
├── PostgreSQL 17
├── Redis
└── Supervisor
```

Single domain:

```text
https://example.uz/platform
https://example.uz/admin
https://example.uz/pos
```

POS API:

```text
https://example.uz/api/pos/*
```

---

# 64. MVP Acceptance Criteria

MVP production-ready hisoblanadi agar quyidagi scenario'lar ishlasa.

## Scenario 1 — New organization

Platform admin yangi organization yaratadi.

Owner login qilib store va productlarni boshqara oladi.

## Scenario 2 — Role

Owner cashier yaratadi.

Cashier faqat unga ruxsat berilgan POS funksiyalarni ko‘radi.

## Scenario 3 — Dine-in

Cashier Table 5 uchun order ochadi, product qo‘shadi, kitchen'ga yuboradi.

Keyin yana item qo‘shilsa kitchen ticketda faqat yangi item chiqadi.

Payment qilinadi.

Customer receipt chiqadi.

Table bo‘shaydi.

## Scenario 4 — Takeaway

Cashier takeaway order yaratadi.

Payment oladi.

Receipt va kitchen ticket kerakli print routes bo‘yicha chiqadi.

## Scenario 5 — Delivery

Cashier phone, address, products va delivery fee bilan delivery order yaratadi.

Payment hozir yoki keyin qilinishi mumkin.

## Scenario 6 — Printer routing

Boshlanishida:

```text
Customer → Printer A
Kitchen  → Printer A
```

ishlaydi.

Keyin administrator UI orqali:

```text
Kitchen → Printer B
```

qiladi.

Application code o‘zgarmaydi.

## Scenario 7 — Multi-store

Owner Store A va Store B ni ko‘ra oladi.

Store A cashier Store B data'larini ko‘rmaydi.

## Scenario 8 — Multi-tenant

Organization A foydalanuvchisi hech qanday request orqali Organization B data'lariga kira olmaydi.

## Scenario 9 — Subscription

Expired subscription bilan yangi POS transaction yaratilmaydi.

## Scenario 10 — Speed

Product click va cart calculation local ishlaydi.

Har product click uchun backend request yuborilmaydi.

---

# 65. Architecture Rules for AI Coding Agents

Any AI agent implementing this project MUST follow these rules:

1. Do not add functionality not defined in this specification without explicit instruction.
2. Do not introduce ingredients, inventory, modifiers, loyalty or KDS into MVP.
3. Do not put POS UI inside Filament.
4. Do not create microservices.
5. Do not split the frontend into a separate repository.
6. Do not trust organization_id or store_id directly from user input.
7. Every tenant-owned resource must enforce organization isolation.
8. Store-specific resources must enforce store access.
9. Authorization must exist on backend even when frontend elements are hidden.
10. Subscription features and user permissions must remain separate concepts.
11. Do not hardcode physical printer names into business logic.
12. PrintType must resolve to printer through database configuration.
13. QZ Tray-specific code must remain inside PrinterService.
14. Transactional entities should use ULID where defined.
15. POST operations that may later sync offline must be idempotent.
16. Product clicks and cart changes must remain frontend-local.
17. Controllers must remain thin.
18. Business operations belong in Action/domain classes.
19. Money must not use floating-point storage.
20. Do not physically delete financial/transaction history.
21. Existing architecture must remain compatible with future IndexedDB/offline sync.
22. Prefer the simplest implementation satisfying this specification.

---

# 66. Future Modules

MVP barqaror ishlagandan va real customer feedback olingandan keyingina quyidagilar ko‘rib chiqiladi:

- Offline POS + sync
- Inventory
- Recipes
- Ingredients
- Purchases
- Suppliers
- Waste
- Modifiers
- Discounts
- Loyalty
- Courier
- KDS
- QR ordering
- Telegram orders
- Online ordering
- Advanced analytics
- Automatic subscription billing
- Mobile application

Ularning hech biri MVP launch uchun blocker emas.

---

# 67. Final Architecture

```text
                         POS SaaS

                  Laravel 13 Monolith
                         │
        ┌────────────────┼─────────────────┐
        │                │                 │
        ▼                ▼                 ▼
   /platform          /admin             /pos
   Filament           Filament          Vue SPA
        │                │                 │
        │                │            Pinia state
        │                │                 │
        └────────────────┼─────────────────┘
                         │
                     Laravel API
                         │
        ┌────────────────┼────────────────┐
        │                │                │
        ▼                ▼                ▼
   PostgreSQL          Redis         PrinterService
                                         │
                                      QZ Tray
                                         │
                                  Thermal Printer


Organization
   │
   ├── Subscription
   ├── Users + Roles
   ├── Products
   │
   └── Stores
        │
        ├── Devices
        ├── Tables
        ├── Printers
        ├── Orders
        │    ├── Items
        │    ├── Delivery Details
        │    └── Payments
        │
        └── Shifts
```

MVP success definition:

> Bir real fast food yoki cafe tizimni o‘rnatib, yangi xodimga qisqa tushuntirishdan keyin dine-in, takeaway va delivery orderlarni mustaqil qabul qila olsa, oshxona va mijoz cheklarini chiqara olsa, paymentlarni yopsa va owner admin paneldan savdoni nazorat qila olsa — MVP o‘z vazifasini bajargan hisoblanadi.
