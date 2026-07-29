# Photo Studio SaaS

A multi-tenant SaaS platform for photography and photo-editing studios
(wedding, graduation, passport, family, product, event photography, ...).
Built as an enterprise-grade application with Clean Architecture, strict
tenant data isolation, and a modern Material Design 3 UI.

This repository is being built **incrementally, phase by phase**. This
README always reflects what's actually implemented — not the full
long-term vision (see [Roadmap](#roadmap) for what's next).

## Tech Stack

**Backend** — Laravel 12, PostgreSQL, JWT auth (`tymon/jwt-auth`), RBAC
(`spatie/laravel-permission`, tenant-scoped via its Teams feature),
`spatie/laravel-activitylog`, `maatwebsite/excel` (import/export), Redis
(cache/queue/session), Laravel Horizon, Repository + Service layer
pattern, API Resources, Form Requests, Policies.

**Frontend** — Vue 3, Vite, Vuetify 3 (Material Design 3), Vue Router,
Pinia, Vue I18n (English + Khmer), Axios, VueUse, Chart.js, VeeValidate +
Yup, date-fns.

**Infra** — Docker, Docker Compose, Nginx, PostgreSQL, Redis, Horizon.

## Architecture

### Multi-tenancy

Single database, shared schema. Every tenant-owned table carries a
`tenant_id` column. Isolation is enforced at the **query layer**, not by
convention:

- `App\Traits\BelongsToTenant` (applied to every tenant-owned model)
  registers `App\Models\Scopes\TenantScope`, a global scope that adds
  `WHERE tenant_id = :current_tenant` to *every* query automatically, and
  auto-fills `tenant_id` on create.
- `App\Services\TenantContext` is a per-request singleton holding "which
  tenant is this request operating as". It's populated by
  `App\Http\Middleware\IdentifyTenant` after the JWT resolves the user.
- RBAC roles are also tenant-scoped: `spatie/laravel-permission`'s Teams
  feature is keyed on `tenant_id`, so two tenants can each have their own
  "Manager" role with different permissions, fully isolated.

See `tests/Feature/TenantIsolationTest.php` for the isolation guarantee
proven directly against PostgreSQL.

### Backend layers (`backend/app/`)

```
Actions/         Single-purpose use-case classes (e.g. ProvisionTenantRolesAction)
DTO/             Typed data carriers between requests and services
Enums/           SubscriptionStatus, BillingCycle, TenantRole, ...
Http/
  Controllers/Api/V1/   Thin controllers — validate, delegate, respond
  Requests/             Form Request validation
  Resources/            API Resources (response shaping)
  Middleware/           IdentifyTenant, EnsureSubscriptionActive
Models/          Eloquent models + Scopes/TenantScope
Policies/        Authorization policies
Repositories/    Contracts/ (interfaces) + Eloquent/ (implementations)
Services/        Business logic, orchestrates repositories
Traits/          BelongsToTenant, ApiResponse
```

Every API response follows one envelope:

```json
{ "success": true, "message": "...", "data": {}, "meta": {} }
```

### Frontend layers (`frontend/src/`)

```
apis/                 axios instance (api.js) + one <resource>.api.js per domain
components/common/   Reusable Vuetify components (AppTable, AppDialog, ...)
layouts/              AuthLayout, DefaultLayout
pages/                Route-level views (lazy-loaded, one chunk each)
router/               Routes + auth/guest navigation guards
stores/               Pinia stores (auth, app, customerTags)
plugins/              Vuetify, Vue I18n setup
locales/              en.json, km.json
utils/                Yup validation schemas
```

Every data table in the app is server-driven (search/sort/pagination all
round-trip to the API) via the `AppTable` component wrapping Vuetify's
`v-data-table-server`, matching `BaseRepository::paginateServer()` on the
backend.

API calls live in `apis/`, one file per resource, each a set of plain
named functions — not a class or a grouping object, and no separate
endpoint-constants file:

```js
// apis/customer.api.js
import http from './api'

export const getCustomersApi = params => http.get('/v1/customers', { params })
export const createCustomerApi = payload => http.post('/v1/customers', payload)
```

`apis/api.js` is the shared axios instance (token attach/refresh
interceptors) plus `getToken`/`setToken`. Stores and components import the
specific `*Api` functions they need directly — e.g.
`import { loginApi } from '@/apis/auth.api'`.

## What's implemented (Phase 1 — Foundation)

- Multi-tenant schema: `tenants`, `plans`, `subscriptions`, `users` (all
  UUID PKs, `tenant_id`, timestamps, soft deletes).
- JWT authentication: register (creates tenant + Owner user + 14-day
  trial subscription + tenant's own RBAC roles), login, logout, refresh,
  forgot/reset password, email verification, "remember me".
- RBAC: 7 baseline roles (Owner, Manager, Photographer, Editor, Cashier,
  Receptionist, Viewer) provisioned per tenant on registration, each with
  a default permission grant from `config/permissions.php`. Fully
  customizable per tenant afterwards.
- Subscription plans seeded: Free Trial (14 days), Starter, Professional,
  Enterprise, with per-plan feature limits (users, storage, monthly
  orders, watermark/online gallery, reports, API access).
  `EnsureSubscriptionActive` middleware gates tenant routes behind an
  in-effect subscription.
- Platform Super Admin bootstrap user (tenant_id = null,
  `is_super_admin = true`) for the future Super Admin Panel.
- Vue SPA shell: login/register/forgot/reset/verify-email pages, app
  shell with nav drawer + app bar (theme toggle, locale switch, user
  menu), dashboard placeholder cards + a Chart.js revenue chart wired
  and ready for real data.
- 23 passing backend feature tests (registration, login, password reset,
  email verification, tenant isolation) run against real PostgreSQL —
  not SQLite — to catch dialect-specific bugs.

## What's implemented (Phase 2 — Customer Management)

- Customer profiles: name, email, phone, address, birthday, gender,
  avatar, favorite flag, blacklist flag + reason, `created_by`.
- Tenant-scoped tags (many-to-many), with their own uniqueness per
  tenant — two studios can each have a "VIP" tag with different colors.
- Customer notes (threaded, authored, soft-deleted).
- Customer History: automatic activity log via `spatie/laravel-activitylog`
  (`Customer::getActivitylogOptions()` + `tapActivity()` stamps `tenant_id`
  onto every log entry) — no separate history table needed.
- Search (name/email/phone), filter (tag, favorite, blacklisted, gender),
  full server-side pagination/sorting — the exact contract `AppTable`
  expects.
- Export to CSV or XLSX (`maatwebsite/excel`), scoped to the current
  tenant and current filters.
- Import from CSV/XLSX with per-row validation — bad rows are skipped
  and reported (row number + error), good rows still import.
- New permissions (`customers.view/create/update/delete/export/import/
  blacklist`) added to the RBAC catalog. `SyncTenantRolePermissionsAction`
  + `php artisan permissions:sync-tenants` additively grants these to
  every already-registered tenant's roles without touching any
  customization a tenant made to their own roles.
- Vue: full customers list page (`AppTable` + filter row + export/import
  buttons), create/edit dialog, detail dialog (notes, tags, favorite
  toggle), blacklist dialog, tag manager dialog, import dialog with
  per-row failure reporting.
- 53 total passing backend tests (30 new for this module): CRUD, search/
  filter/pagination, favorite/blacklist, notes, tags, import/export,
  permission gating per role, and cross-tenant isolation (view/update/
  list/tag-attachment all proven to fail closed).

Two bugs found and fixed while building this phase (both real, not
speculative — see inline comments at each fix):

- `Tenant::activeSubscription()` used Eloquent's `latestOfMany()`, which
  aggregates `MAX(subscriptions.id)` as a tie-breaker — Postgres has no
  `MAX()` for `uuid` columns. Switched to a plain `->latest()` single-row
  relation (this relation is only ever accessed for one tenant at a time,
  so the eager-load-optimized `latestOfMany` machinery wasn't needed).
- Route-model-bound params (e.g. `{customer}`) could resolve *before*
  `IdentifyTenant` set the tenant scope, because Laravel's built-in `api`
  middleware group appends `SubstituteBindings`, which wasn't ordered
  relative to our custom `tenant` middleware. Fixed via
  `$middleware->prependToPriorityList()` in `bootstrap/app.php` — without
  it, cross-tenant access was still correctly denied (by the policy
  layer), but only as the *second* line of defense, not because it was
  structurally impossible at the query level as the README claims.

## What's implemented (Phase 3 — Booking Management + Calendar)

- Bookings: linked to a Customer, optionally assigned to a staff member,
  typed (wedding/portrait/family/product/passport/event/other), studio or
  on-location (with required address), a status lifecycle (pending →
  confirmed → in_progress → completed, or cancelled/no_show with a
  required reason), free-text title/notes.
- Double-booking prevention: creating or rescheduling a booking checks
  the assigned staff member's existing (non-cancelled) bookings for a
  time-range overlap and rejects it with a clear validation error.
  Cancelling/no-showing a booking frees its slot immediately.
- Row-level authorization nuance: schedulers (`bookings.assign` — Owner/
  Manager/Receptionist) can update any booking; a Photographer with only
  `bookings.update` can update just the bookings assigned to them (e.g.
  mark their own shoot complete), enforced in `BookingPolicy`.
- Booking History via the same `spatie/laravel-activitylog` pattern as
  Customer History — status/reschedule changes logged automatically.
- Calendar range endpoint (`GET /v1/bookings/calendar?start=&end=`) —
  interval-overlap query against Postgres, tenant-scoped, filterable by
  status/type/assigned user.
- New permissions (`bookings.view/create/update/delete/assign/cancel`)
  added to the RBAC catalog — picked up by existing tenants via the same
  `permissions:sync-tenants` command from Phase 2, no code changes needed
  since that command is fully config-driven.
- Minimal `GET /v1/users` endpoint added (tenant-scoped staff list) to
  back the "assign photographer" picker — full user management (invite/
  deactivate/roles UI) is intentionally deferred to a later phase; this
  is read-only and permission-gated behind `users.view`.
- Vue: bookings list page (`AppTable` + status/type/assigned filters +
  inline status-transition buttons), booking form dialog (customer
  autocomplete, staff picker, date/time pickers, conditional address
  field), cancel-with-reason dialog, and a **custom month-view calendar**
  component (`BookingCalendar.vue`) — Vuetify 3 dropped its old
  `v-calendar`, so this is a small self-built grid rather than a new
  dependency — with day-agenda drill-down and click-to-edit.
- 86 total passing backend tests (33 new for this module): CRUD,
  conflict/double-booking rejection (create and update paths, including
  a same-booking false-positive check), status transitions, calendar
  range correctness (including tenant isolation), search/filter/
  pagination, permission gating per role, and cross-tenant isolation.

## What's implemented (Phase 4 — Photography Services & Pricing)

- The pricing catalog Order Workflow (Phase 5) will consume: Service
  Categories (simple named groups, e.g. "Wedding Packages"), Services
  (name, category, price, pricing unit — fixed/per-hour/per-person/
  per-photo, duration, description, deliverables text, active flag), and
  standalone Add-ons (name, price) not tied to a specific service.
  Deliberately self-contained — Bookings aren't retrofitted with a
  service link yet, since choosing a package + add-ons is really an
  Order-Workflow concern.
- Service price/availability history via the same
  `spatie/laravel-activitylog` pattern as Customer/Booking History —
  price changes are auditable for quoting disputes.
- New permissions (`services.view/create/update/delete`) added to the
  RBAC catalog, picked up by existing tenants via the same
  `permissions:sync-tenants` command — config-driven, no code changes
  needed for a third module in a row.
- Vue: services list page (`AppTable` + category/status filters),
  service form dialog (category select, pricing unit, duration,
  deliverables), and lightweight category/add-on manager dialogs (same
  inline-create-and-list pattern as Phase 2's tag manager).
- 111 total passing backend tests (25 new for this module): CRUD for
  services/categories/add-ons, search/filter/pagination, price-change
  activity log, permission gating per role, and cross-tenant isolation.

## What's implemented (Phase 5 — Order Workflow + Editing Queue)

- Orders tie a Customer (and optionally a Booking) to line items pulled
  from Phase 4's Service/Add-on catalog — or fully custom, ad-hoc lines.
  Catalog references snapshot their name and price onto the order item
  at creation time, so a later price change (or catalog deletion) never
  rewrites a past order's total. Subtotal/discount/total are computed
  server-side; a discount larger than the subtotal floors the total at
  zero rather than going negative.
- Order status lifecycle enforced server-side, not just in the UI:
  `pending → confirmed → in_production → ready_for_delivery → delivered`,
  or `cancelled` (blocked once delivered/already cancelled, reason
  required). Line items can only be edited while an order is still
  `pending`/`confirmed` — once production starts they're locked.
  `start_production` is the hinge: it requires `confirmed` and creates
  the order's Editing Task in one step.
- Editing Queue: each order gets at most one Editing Task once
  production starts, independently tracked through
  `pending → in_progress → in_review → (revision_requested ↔
  in_progress) → completed`. An order can't be marked ready for
  delivery until its editing task is `completed` — verified directly
  against the DB in tests, not assumed.
- Row-level authorization mirrors Phase 3's Photographer pattern: an
  Editor (`editing.update` only) can only transition tasks assigned to
  them; reassigning who owns a task is a separate `assign` ability
  gated behind `orders.update` (a manager-level action, not something
  editors do to themselves).
- Order/Editing Task History via the same activity-log pattern as every
  prior module.
- New permissions (`orders.*`, `editing.*`) added to the RBAC catalog,
  picked up by existing tenants via the same `permissions:sync-tenants`
  command — config-driven, no code changes, fourth module running.
- Found and fixed a real bug while building this: `whenLoaded($rel, $cb)`'s
  two-argument form returns plain `null` when the relation isn't
  eager-loaded (not Laravel's `MissingValue` sentinel, which only the
  one-argument form returns) — so `EditingTaskResource`'s `assigned_user`
  silently serialized as `null` right after a successful reassignment.
  Fixed by eager-loading the relation before serializing in every
  controller action that returns it, with a regression test asserting
  the assigned editor's id actually appears in the response.
- Vue: orders list (`AppTable` + status filter), an order form with a
  line-item builder (add from catalog with live price snapshot preview,
  or add a fully custom line, running subtotal/total), an order detail
  view with inline status-transition actions, and an Editing Queue list
  with its own status actions.
- 137 total passing backend tests (26 new for this module): line-item
  snapshotting and total computation, the full status lifecycle
  (including the production-gate and delivery-gate checks), editing
  task transitions and row-level permission enforcement, search/filter/
  pagination, permission gating per role, and cross-tenant isolation.

## What's implemented (Phase 7 — Album Management, Invoicing & Payments)

Phase 6 (Gallery: upload, watermark, customer download, QR) is
deliberately skipped for now — Albums below are metadata-only (no photo
storage yet); the photo pipeline is deferred to when Phase 6 ships.

- Albums are a lightweight tracking record — name, optional Customer/
  Order link, description, expected photo count — moving through
  `draft → in_progress → ready → delivered`, or `archived` from any
  non-archived state. No file storage is attached yet.
- Invoices bill a Customer either from scratch (its own catalog/custom
  line items, same snapshotting pattern as Orders) or generated from an
  existing Order by copying that order's line items verbatim. Totals
  (`subtotal`, `discount_amount`, `tax_rate` → `tax_amount` → `total`)
  are always computed server-side.
- Invoice status lifecycle: `draft → sent → (partially_paid | paid)`,
  or `overdue` (past due date, swept daily by the
  `invoices:mark-overdue` scheduled command across every tenant), or
  `void` (blocked once `paid`, requires a reason). Line items,
  discount, and tax can only change while still `draft`; only `draft`
  invoices can be deleted outright — anything further along is voided
  instead, preserving the record.
- Payments are recorded manually against a `sent`/`partially_paid`/
  `overdue` invoice (cash, bank transfer, card, or other) — never
  exceeding the remaining balance — and recompute the invoice's
  `amount_paid`/status on every record or delete, self-healing the
  status (including re-deriving `overdue` vs `sent` if a payment is
  removed). No payment gateway integration — manual bookkeeping only.
- New permissions (`albums.*`, `invoices.*`, `payments.record`,
  `payments.delete`) added to the RBAC catalog. The existing `Cashier`
  role (defined since Phase 1 but unused until now) is the first role
  to get full invoice/payment rights without order deletion or
  catalog-management access; Editor/Photographer get album view/update
  rights fitting their production role.
- Album/Invoice/Payment History via the same activity-log pattern as
  every prior module.
- Vue: an Albums list with inline lifecycle actions, an Invoices list
  with a create-from-order-or-from-scratch form (order selection
  previews and copies its line items live), an invoice detail view with
  send/void actions and an inline payment ledger + record-payment form.
- 177 total passing backend tests (38 new for this module): invoice
  total computation (discount + tax), order-to-invoice item
  snapshotting, the full status lifecycle including the
  draft-only-editing and paid/void guards, payment recording
  (full/partial/over-the-balance-rejected) and status recalculation on
  payment deletion, the overdue sweep command, permission gating per
  role (including the new Cashier checks), and cross-tenant isolation.

## What's implemented (Phase 8 — Package Management)

Not on the original roadmap — inserted ahead of it. Bundles Services/
Add-ons from Phase 4 into a fixed-price "Package" (e.g. "Wedding
Package = Photography + Album + optional Makeup"), selectable in
Orders and Invoices alongside individual services.

- A Package's price is never manually typed as a flat number by
  default — it's live-computed from its components every time it's
  read: `component_total` (sum of each INCLUDED component's current
  Service/Add-on catalog price × quantity) minus an optional
  `discount_type`/`discount_value` (percent or fixed amount, floored at
  zero) = `final_price`. An `override_price` can still be set to skip
  the computed price entirely for a one-off custom quote. Nothing is
  cached — editing a component's catalog price updates every package
  that includes it immediately, with no re-save needed.
- Each component is flagged `is_optional`: `false` means it's baked
  into `component_total`/`final_price`; `true` means it's offered
  alongside the package but excluded from its price — e.g. a Wedding
  Package can include Photography + Album in its price while offering
  Makeup as an optional extra.
- Packages plug into the exact same line-item builder Orders and
  Invoices already had for Services/Add-ons: `order_items`/
  `invoice_items` gained a nullable `package_id` column, and
  `OrderService`/`InvoiceService` snapshot a package's live
  `final_price` at the moment it's added to a line — same
  never-rewrites-history guarantee as Service/Add-on references. A
  package's optional components surface as checkboxes once it's added
  to an order/invoice; checking one just appends it as its own normal
  Add-on/Service line alongside the package's line.
- New `packages.*` permissions added to the RBAC catalog — full CRUD
  for Manager, view-only for every role that already builds Orders/
  Invoices (Photographer, Editor, Cashier, Receptionist, Viewer), since
  they all need to see the catalog when adding a package to a line.
- Package History via the same activity-log pattern as every prior
  module.
- Vue: a Packages list with a component builder (add Services/Add-ons
  with quantity + an optional-extra toggle, live component-total/
  final-price preview), and both `OrderFormDialog`/`InvoiceFormDialog`
  catalog pickers now list Packages alongside Services/Add-ons, with
  optional add-on checkboxes appearing once a package is picked.
- 200 total passing backend tests (23 new for this module): component
  validation (exactly one of service_id/addon_id per component),
  pricing (percent/fixed discount, override precedence, live
  recomputation when a component's catalog price changes, floored at
  zero), permission gating per role, cross-tenant isolation, and
  Order/Invoice line-item snapshotting of a package's name/final_price
  (including combining a package with its own optional add-on as a
  separate line).

## What's implemented (Phase 9 — Expense & Inventory)

- Expenses are a standalone ledger — category (tenant-manageable, same
  inline-create-and-list pattern as Service Categories), amount,
  expense date, vendor, payment method (reusing Phase 7's
  `PaymentMethod` enum), notes. No link to Inventory purchases —
  restocking and logging the cost are two independent manual actions.
- Inventory tracks consumable stock (paper, ink, albums, ...), not
  individually-serialized equipment assets. Each item's
  `quantity_on_hand` is never hand-edited or incremented/decremented
  directly — it's always *recomputed* from its full movement history
  (`sum(stock_in) − sum(stock_out)`) every time a movement is recorded
  or removed, so it can never drift from its own audit trail. A
  `reorder_threshold` (optional) drives an `is_low_stock` flag and a
  low-stock list filter.
- Stock-out is guarded against overdrawing: recording more `stock_out`
  than the current `quantity_on_hand` is rejected with a 422 rather
  than allowed to go negative.
- New `expenses.*` and `inventory.*` (including a distinct
  `inventory.adjust-stock` ability, separate from full item CRUD)
  permissions added to the RBAC catalog. Photographer/Editor get
  `inventory.view` + `inventory.adjust-stock` (they consume supplies
  day-to-day but don't manage the catalog); Cashier gets `expenses.*`
  (view/create/update, matching their existing invoicing role) plus
  `inventory.view`.
- Expense/InventoryItem History via the same activity-log pattern as
  every prior module.
- Vue: an Expenses list with date-range filtering and an inline
  category manager (mirrors `ServiceCategoryManagerDialog`), and an
  Inventory list with a low-stock filter and an item detail view
  showing the full movement ledger plus an inline record-movement form.
- 228 total passing backend tests (28 new for this module): expense
  CRUD/date-range filtering, inventory CRUD/low-stock filtering, stock
  in/out recording and the resulting quantity recomputation (including
  after a movement is deleted), the overdraw-rejection guard, the
  low-stock flag against the reorder threshold, permission gating per
  role, and cross-tenant isolation.

## Getting Started

### Prerequisites

- PHP 8.4+, Composer 2
- Node 20+, npm
- PostgreSQL 14+, Redis 7+ (or Docker, see below)

### Option A — Local dev (no Docker)

```bash
# Backend
cd backend
cp .env.example .env
composer install
# point DB_* / REDIS_* in .env at your local Postgres/Redis, then:
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve   # http://localhost:8000

# Frontend (separate terminal)
cd frontend
cp .env.example .env
npm install
npm run dev          # http://localhost:5173, proxies /api -> backend
```

### Option B — Docker Compose

```bash
cd backend && cp .env.example .env && cd ..
# generate APP_KEY / JWT_SECRET once, locally, then paste into backend/.env
# (or run these two commands against the built image after first `up`)

docker compose up -d --build
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
docker compose exec backend php artisan migrate --seed
```

Services: `nginx` (backend API, :8000), `frontend` (:5173), `postgres`
(:5432), `redis` (:6379), `horizon` (queue dashboard at `/horizon`,
gated to super admins), `scheduler` (runs `schedule:run` every minute).

### Default login

Seeded by `SuperAdminSeeder` (configurable via `SUPER_ADMIN_EMAIL` /
`SUPER_ADMIN_PASSWORD` in `.env`):

```
admin@platform.test / password   (Super Admin — no tenant, platform-wide)
```

Regular tenant accounts are created via the **Register** page — it
creates the tenant, its Owner user, and a 14-day trial subscription in
one transaction.

### Running tests

```bash
cd backend
php artisan test
```

Tests run against a real PostgreSQL database (`photo_studio_saas_test`,
see `phpunit.xml`) — create it once with
`createdb photo_studio_saas_test` if not using Docker.

## Roadmap

Built in phases; each phase ships with migrations, models, API,
controllers/services/repositories/policies, Vue pages/components,
Pinia stores, and tests, same as Phase 1.

1. ~~Foundation: multi-tenancy, JWT auth, RBAC, subscription plans, shell~~ ✅
2. ~~Customer Management~~ ✅
3. ~~Booking Management + Calendar~~ ✅
4. ~~Photography Services & Pricing~~ ✅
5. ~~Order Workflow + Editing Queue~~ ✅
6. Gallery (upload, watermark, customer download, QR) — skipped for now, see Phase 7 note
7. ~~Album Management, Invoicing & Payments~~ ✅ (Albums are metadata-only, pending Phase 6's photo storage)
8. ~~Package Management~~ ✅ (inserted ahead of the original roadmap — bundles Services/Add-ons from Phase 4 into fixed-price Packages, selectable in Orders/Invoices alongside individual services)
9. ~~Expense & Inventory~~ ✅ (Inventory tracks consumable stock only, not serialized equipment assets)
10. Employee Management (attendance, salary, commission)
11. Customer Portal
12. Notifications (email, in-app, Telegram)
13. Reports & Exports
14. Settings (company, invoice, watermark, theme, backup)
15. Super Admin Panel (tenants, plans, platform analytics, support tickets)
