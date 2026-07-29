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
6. Gallery (upload, watermark, customer download, QR)
7. Album Management, Invoicing & Payments
8. Expense & Inventory
9. Employee Management (attendance, salary, commission)
10. Customer Portal
11. Notifications (email, in-app, Telegram)
12. Reports & Exports
13. Settings (company, invoice, watermark, theme, backup)
14. Super Admin Panel (tenants, plans, platform analytics, support tickets)
