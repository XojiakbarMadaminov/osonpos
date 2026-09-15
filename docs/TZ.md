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

## 2.5 Tizim tili va lokalizatsiya

Tizimning foydalanuvchiga ko‘rinadigan asosiy va yagona tili — o‘zbek tili (`uz`).

Quyidagi barcha joylardagi matnlar o‘zbek tilida bo‘lishi shart:

- `/platform` Filament paneli
- `/admin` Filament paneli
- `/pos` Vue ilovasi
- login va boshqa autentifikatsiya sahifalari
- menyular, sahifa sarlavhalari, tugmalar va yordamchi matnlar
- form label, placeholder va validatsiya xabarlari
- notification, confirmation va foydalanuvchiga ko‘rsatiladigan xatolar
- order turi, order holati, payment holati, payment turi, role va permission nomlari
- customer receipt va kitchen ticket

Foydalanuvchiga `DINE_IN`, `TAKEAWAY`, `OPEN`, `PAID` kabi raw enum qiymatlari, translation key yoki technical exception matni chiqarilmasligi kerak. Ular o‘zbekcha tushunarli label bilan ko‘rsatiladi.

Ichki enum qiymatlari, permission keylar, API field nomlari va database qiymatlari architecture barqarorligi uchun o‘zgartirilmaydi. Tarjima faqat presentation layer'da Laravel language fayllari, Filament label'lari, enum label methodlari yoki frontend label maplari orqali bajariladi.

Laravel uchun `APP_LOCALE=uz` va `APP_FALLBACK_LOCALE=uz` ishlatiladi.

Yangi user-facing funksiya o‘zbekcha matnlari va tegishli localization testlarisiz complete hisoblanmaydi.

Organization, store, product, customer kabi foydalanuvchi kiritgan business data qanday kiritilgan bo‘lsa, shunday ko‘rsatiladi va avtomatik tarjima qilinmaydi.

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
- Chiqimlar

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

Joriy organization va faol filial alohida sozlamalar sahifasida emas, admin panelning o‘ng yuqori profil menyusida ko‘rsatiladi. Foydalanuvchi shu menyudagi bitta select orqali faqat o‘zi kirish huquqiga ega faol filialni tanlaydi. Filial tanlanganda uning organization'i backend tomonidan aniqlanadi; requestdan `organization_id` qabul qilinmaydi.

Menu user permission va organization subscription feature'lariga qarab avtomatik yashiriladi.

Admin yon menyusi vazifasiga qarab quyidagi tartibda guruhlanadi:

- Savdo — Hisobotlar, Buyurtmalar, Mijozlar, Chiqimlar, Smenalar
- Katalog — Kategoriyalar, Mahsulotlar
- Filial boshqaruvi — Filiallar, Stollar, Qurilmalar, Printerlar, Chop etish yo‘nalishlari
- Xodimlar va ruxsatlar — Foydalanuvchilar, Rollar

Guruhlash faqat navigatsiya ko‘rinishini tartiblaydi; permission va subscription feature tekshiruvlarini o‘zgartirmaydi.

Organization admin uchun alohida bo‘sh dashboard mavjud emas. `/admin` foydalanuvchini permission va feature tekshiruvlaridan o‘tgan birinchi mavjud admin bo‘limiga, hech qanday admin bo‘limi mavjud bo‘lmasa `/pos` ga yo‘naltiradi.

`/admin/orders`, `/admin/expenses` va `/admin/shifts` jadvallari ustida yagona davr filtri bo‘ladi: Bugun, Hafta, Oy va Oraliq. Standart qiymat Bugun. Hafta joriy kalendar haftasini, Oy joriy kalendar oyini, Oraliq esa foydalanuvchi kiritgan inclusive boshlanish va tugash sanalarini qo‘llaydi. Davr filtri tenant, store access va boshqa resource filterlarini chetlab o‘tmaydi.

`/admin/orders`, `/admin/expenses`, `/admin/shifts`, `/admin/tables`, `/admin/devices` va `/admin/printers` sahifalari faqat profil menyusida tanlangan faol filial ma’lumotlarini ko‘rsatadi. Bu jadvallarda alohida filial filtri bo‘lmaydi. Boshqa filial ma’lumotlarini ko‘rish yoki boshqarish uchun user profil menyusidan faol filialni almashtiradi. Yangi chiqim, stol, qurilma va printer ham backend tomonidan faqat faol filialga biriktiriladi.

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
activation_code_hash nullable
credential_hash nullable
activated_at nullable
created_at
updated_at
```

Misollar:

- Main Cashier
- Second Cashier
- Waiter Tablet 1

`/pos` va `/pos/device-setup` faqat login qilingan user uchun ochiladi. Guest organization panelining `/admin/login` sahifasiga, dastlab so‘ralgan URL saqlangan holda yo‘naltiriladi. Login sahifasida POS uchun avval tizimga kirish kerakligi o‘zbek tilida ko‘rsatiladi.

Device organization admin panelida `printers.manage` ruxsatiga ega owner yoki manager tomonidan yaratiladi. Admin faqat qurilma nomi va har bir device uchun 6 xonali doimiy aktivatsiya kodini belgilaydi; texnik `code` tizim tomonidan avtomatik yaratiladi va interfeysda ko‘rsatilmaydi. Aktivatsiya kodi admin uni almashtirmaguncha amal qiladi va yangi browser profili yoki cookie tozalangandan keyin qayta ishlatilishi mumkin:

```text
Admin device yaratadi
↓
6 xonali doimiy aktivatsiya kodini belgilaydi
↓
POS user login qiladi
↓
/pos/device-setup da kodni kiritadi
↓
Backend membership + pos.access + store access + subscription + pos feature'ni tekshiradi
↓
Browserga uzoq muddatli HttpOnly device credential beriladi
```

Aktivatsiya kodi va device credential bazada ochiq saqlanmaydi. Aktivatsiya kodi har ishlatilganda yangi browser credential beriladi va shu device'ning avvalgi browser credential'i darhol yaroqsiz bo‘ladi. Admin kodni istalgan payt almashtirishi mumkin. Admin ulanishni bekor qilsa credential darhol yaroqsiz bo‘ladi, lekin doimiy kod bilan qurilmani qayta ulash mumkin. Device `is_active = false` bo‘lsa aktivatsiya va barcha device-bound POS so‘rovlari bloklanadi.

Device credential browser profiliga tegishli va login sessiyasidan alohida saqlanadi. Shu browser qayta ochilganda setup takrorlanmaydi; boshqa browser, inkognito profil yoki cookie tozalanganda qayta aktivatsiya talab qilinadi. Credential login qilgan userning organization membership, permission va store access tekshiruvlarini chetlab o‘tmaydi.

Frontend saqlagan oddiy `device_id` kelajakdagi offline metadata uchun ishlatilishi mumkin, ammo authorization credential hisoblanmaydi va `X-POS-Device-ID` kabi request qiymatiga backend ishonmaydi.

---

# 19. Catalog

## categories

```text
id
organization_id
store_id
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
store_id
category_id
name
price
cost_price
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

Kategoriya va product organization ichidagi bitta store'ga tegishli.

Har bir store o‘zining mustaqil kategoriya, product va narx katalogidan foydalanadi. Admin panel va POS faqat tanlangan faol store katalogini ko‘rsatadi. Boshqa store katalogiga to‘g‘ridan-to‘g‘ri kirish backendda bloklanadi.

`cost_price` bir dona mahsulotning integer UZS’dagi tannarxi bo‘lib, faqat admin mahsulot CRUD’ida ko‘rinadi. U POS API, POS UI, mijoz cheki, oshxona chiptasi yoki hisobotda alohida summa sifatida chiqarilmaydi. Hisobot tannarxdan faqat taxminiy yalpi foydani hisoblash uchun ichki foydalanadi.

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
business_date
type
table_id nullable
customer_id nullable
customer_name nullable
customer_phone nullable
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

Barcha user-facing pul qiymatlari kasr qismisiz va mingliklar bo‘sh joy bilan ajratilgan holda ko‘rsatiladi: `40 000`, `1 000 000`. `.00` yoki `,00` chiqarilmaydi. Bu qoida admin, platform, POS, hisobot va chop hujjatlariga bir xil qo‘llanadi.

`customer_name` va `customer_phone` buyurtma yaratilganda yoki ochiq buyurtmaga mijoz biriktirilganda snapshot sifatida saqlanadi. Mijoz profili keyin o‘zgarsa ham tarixiy buyurtma va mijoz cheki o‘zgarmaydi. Mijozni ochiq buyurtmadan olib tashlash customer yozuvini o‘chirmaydi.

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

MVP'da display number server orqali har bir store va store-local `business_date` kesimida ketma-ket yaratiladi. Har yangi mahalliy kunda birinchi buyurtma `#0001` dan boshlanadi. Store vaqt zonasi `stores.timezone` orqali aniqlanadi.

`display_number` string sifatida saqlanishi kerak.

`/api/pos/orders` faqat joriy store-local `business_date` buyurtmalarini qaytaradi. Eski kun buyurtmalari admin tarixida saqlanadi, ammo POS ish oynasida ko‘rsatilmaydi.

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
unit_cost
total
note nullable
kitchen_printed_at nullable
created_by
created_at
updated_at
```

`product_name`, `unit_price` va `unit_cost` snapshot sifatida saqlanadi.

Product keyinchalik narxi o‘zgarsa ham eski order o‘zgarmasligi kerak.

---

# 26. Kitchen Print Rule

Order item kitchen'ga chiqarilmaguncha o‘zgartirilishi mumkin.

`kitchen_printed_at != null` bo‘lgandan keyin item finalized hisoblanadi.

Keyin aynan shu product yana qo‘shilsa yangi `order_item` yaratiladi.

Keyingi kitchen ticketda faqat yangi itemlar chiqadi.

Ochiq buyurtmadagi mahsulotni ayirish refund hisoblanmaydi. Asl `order_item` o‘zgartirilmaydi yoki o‘chirilmaydi; ayirish `order_item_removals` tarixida ULID bilan idempotent saqlanadi. Hali oshxonaga chiqmagan item navbatdagi kitchen ticketda qolgan miqdori bilan chiqadi. `kitchen_printed_at != null` item ayirilsa, ayni `KITCHEN_TICKET` printer yo‘nalishida `MAHSULOT BEKOR QILINDI` hujjati alohida chop etiladi. Print xatosi saqlangan ayirishni rollback qilmaydi.

Ayirish faqat joriy tashkilot va faol filialdagi `OPEN` order, joriy device va ochiq smena bilan bajariladi. Qolgan miqdordan ortiq ayirish va order jami mavjud paymentlar summasidan kamayib ketishi taqiqlanadi. To‘lovsiz orderdagi oxirgi mahsulot ayirilsa order avtomatik `CANCELLED` bo‘ladi; oshxonaga chiqarilgan mahsulot uchun pending bekor qilish cheki `CANCELLED` orderda ham chop etiladi. Payment mavjud bo‘lsa barcha mahsulotlarni ayirish taqiqlanadi. Delivery fee o‘zgarmaydi. Receipt, admin tafsilotlari va reportlar mahsulotning qolgan miqdori hamda summasidan foydalanadi.

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
Optional customer selection/create by phone
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

Takeaway mijozsiz odatdagi tezkor oqimda yaratiladi. Mijoz kerak bo‘lsa, kassir asosiy savat sahifasidagi ixcham `+ Mijoz qo‘shish` amalini ochadi. Telefon bo‘yicha qidirish faqat shu picker ichida va kassir qidirish amalini bosganda bajariladi.

Dine-in buyurtma yaratishda mijoz formasi ko‘rsatilmaydi. Mijozni to‘lov sahifasida ixtiyoriy biriktirish, almashtirish yoki olib tashlash mumkin. Bu amallar faqat joriy filialdagi `OPEN` buyurtmada bajariladi.

Customer organization miqyosidagi entity: bir tashkilotning mijozini uning boshqa ruxsat berilgan filialidagi buyurtmada ishlatish mumkin. Boshqa tashkilot mijozini biriktirish va boshqa faol filial buyurtmasini o‘zgartirish bloklanadi.

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

- CASH, CARD, CLICK, PAYME va OTHER paymentlarning barchasi uchun joriy OPEN shift majburiy.
- Har bir yangi payment joriy OPEN shiftga bog‘lanadi; smena ochilmagan paytda yangi payment qabul qilinmaydi.
- Avvalgi versiyalarda yaratilgan tarixiy paymentlarda backward compatibility uchun `shift_id = null` qolishi mumkin.
- Payment va shift organization, store, device va creator bo‘yicha bir xil kontekstga tegishli bo‘lishi shart.

## Shift va buyurtma olish

- DINE_IN, TAKEAWAY va DELIVERY buyurtmalarini yaratish uchun joriy organization, store, device va user kesimida OPEN shift majburiy.
- Ochiq buyurtmaga yangi mahsulot qo‘shish ham joriy OPEN shiftni talab qiladi.
- Boshqa user yoki device smenasi buyurtma olish huquqini bermaydi.
- Client ULID bilan aynan oldin saqlangan buyurtma yoki item qayta yuborilsa, idempotent replay yangi transaction hisoblanmaydi.
- POS smena bo‘lmaganda order-taking control'larini bloklaydi va kassirni Smena sahifasiga yo‘naltiradi; backend tekshiruvi baribir majburiy qoladi.

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

Organization admin `/admin/shifts` sahifasi smena tarixi va tashlab ketilgan ochiq smenani xavfsiz yakunlash uchun ishlatiladi:

- Owner va Manager o‘ziga ruxsat berilgan store'lardagi shiftlarni ko‘radi.
- Cashier faqat o‘z shiftlarini ko‘radi.
- Boshqa organization yoki ruxsat berilmagan store shiftlari ko‘rinmaydi.
- Status, store va ochilgan sana bo‘yicha filter mavjud.
- Owner yoki Manager `shifts.manage` ruxsati va store access mavjud bo‘lsa, kassir tashlab ketgan OPEN smenani admin paneldan yopishi mumkin.
- Admin orqali yopishda `closing_cash` majburiy kiritiladi va shu device'da OPEN order bo‘lsa smena yopilmaydi.
- Admin yopishi smenani tahrirlash emas, ruxsat bilan bajariladigan yakuniy status transition hisoblanadi; CLOSED smena o‘zgartirilmaydi.

---

# 33.1. Chiqimlar

Chiqimlar moduli organization egasiga filial xarajatlarini sodda tarzda qayd etish va kuzatish imkonini beradi. Bu modul buxgalteriya, ombor, ingredient, supplier yoki purchase tizimi emas.

## expenses

```text
id ULID
organization_id
store_id
type
amount
description nullable
incurred_on
status
created_by
cancelled_by nullable
cancelled_at nullable
cancellation_reason nullable
created_at
updated_at
```

Chiqim turi:

- PRODUCT_COST — Mahsulotlar xarajati
- RENT — Ijara
- SALARY — Oylik maosh
- OTHER — Boshqa

Status:

- ACTIVE — Faol
- CANCELLED — Bekor qilingan

Qoidalar:

- `amount` integer UZS bo‘lib, noldan katta bo‘lishi shart.
- `description` ixtiyoriy; foydalanuvchi kerak bo‘lsa chiqim nima uchun qilinganini yozadi.
- `incurred_on` xarajat amalga oshirilgan sanani saqlaydi.
- Organization va store request qiymatidan ko‘r-ko‘rona olinmaydi; joriy tenant hamda userning store access'i backendda tekshiriladi.
- Chiqim moliyaviy tarix bo‘lgani uchun tahrirlanmaydi va fizik o‘chirilmaydi.
- Xato kiritilgan chiqim faqat majburiy sabab bilan CANCELLED qilinadi.
- Umumiy summa faqat ACTIVE chiqimlardan hisoblanadi.

Ruxsatlar:

- `expenses.view`
- `expenses.manage`
- Owner ikkala permissionni default oladi.
- Manager, Cashier va Waiter bu permissionlarni default olmaydi; owner role sozlamasi orqali keyinchalik alohida berishi mumkin.
- User faqat o‘ziga ruxsat berilgan store chiqimlarini ko‘radi va yaratadi.

`/admin/expenses` sahifasida chiqimlar ro‘yxati, yangi chiqim yaratish, read-only tafsilot, bekor qilish, sana/store/type/status filterlari va joriy filterga mos ACTIVE chiqimlar jami ko‘rsatiladi. Yangi chiqim formasida admin profil menyusida tanlangan faol store default tanlanadi. Barcha ko‘rinadigan matnlar o‘zbek tilida bo‘ladi.

`/admin/reports` sahifasi tanlangan sana va ruxsat etilgan store filtrlari bo‘yicha faqat ACTIVE chiqimlar jami va chiqim turi bo‘yicha taqsimotni ko‘rsatadi. CANCELLED chiqimlar, boshqa tenant va ruxsat berilmagan store ma’lumotlari hisobotga kiritilmaydi.

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

Bu sahifaning terminal aktivatsiya qismi login, `pos.access` va admin qurilma uchun belgilagan haqiqiy doimiy kodni talab qiladi. Physical printerlarni aniqlash, test qilish va binding saqlash qismi esa alohida `printers.manage` permission talab qiladi.

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

POST   /api/pos/devices/activate
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
- Estimated gross profit (`revenue - sold product cost`)

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
[QAYTA CHOP]
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

Qayta chiqarilgan ticketda `QAYTA CHOP` belgisi chiqishi kerak.

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
