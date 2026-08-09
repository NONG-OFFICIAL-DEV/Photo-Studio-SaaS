# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Photo Studio SaaS — a multi-tenant SaaS platform for photography/photo-editing studios. Laravel 12 API backend (`backend/`) + Vue 3/Vuetify 3 SPA frontend (`frontend/`), PostgreSQL, Redis, JWT auth, built in incremental phases (see `README.md` for the phase-by-phase feature log — it can lag behind the actual code, so verify against the code itself for anything load-bearing).

## Commands

### Backend (`backend/`)

```bash
composer dev              # runs php artisan serve + queue:listen + pail + vite concurrently
php artisan test           # full suite (real PostgreSQL — see below, not sqlite)
php artisan test --filter=TestClassName
php artisan test --filter=test_method_name
php artisan test tests/Feature/Path/To/FileTest.php
php artisan migrate
php artisan tinker         # scratch-test service/model behavior directly
```

Tests run against a **real PostgreSQL** database (`photo_studio_saas_test`, config in `phpunit.xml`, not `.env.testing`), specifically to catch Postgres-dialect bugs (e.g. `uuid` columns have no `MAX()`, breaking Eloquent's `latestOfMany()`). Create it once with `createdb photo_studio_saas_test`. `CACHE_STORE=array` and `QUEUE_CONNECTION=sync` in tests, unlike dev (`redis`).

### Frontend (`frontend/`)

```bash
npm run dev       # http://localhost:5173, proxies /api -> backend
npm run build
npm run lint      # eslint --fix
npm run format    # prettier --write src/
```

No frontend test runner is configured — verification is done by running the app and driving it directly (Puppeteer via `backend/node_modules/puppeteer` has been used for this in past sessions; there's no project-level test harness for it).

### Docker

`docker compose up -d --build` (see root `docker-compose.yml`) — services: `nginx` (backend :8000), `frontend` (:5173), `postgres`, `redis`, `horizon` (queue dashboard at `/horizon`, super-admin gated), `scheduler` (`schedule:run` every minute). First run needs `php artisan key:generate` / `jwt:secret` / `migrate --seed` inside the `backend` container.

## Architecture

### Multi-tenancy — the one thing to get right

Single database, shared schema. Every tenant-owned table has a `tenant_id` column, and isolation is enforced at the query layer via `App\Traits\BelongsToTenant` (adds `App\Models\Scopes\TenantScope`, a global scope) — not by convention or by remembering to filter.

**The gotcha that causes real bugs repeatedly:** `TenantScope` only filters when `App\Services\TenantContext::check()` is true, i.e. only inside a request that went through `IdentifyTenant` middleware (aliased `tenant`). Console commands, scheduled jobs, and any query on a model whose rows can have `tenant_id = null` (super admins on `User`) run with **no scope active** — a query for `User::where('is_super_admin', true)` executed *while a tenant request context is active* (e.g. called from inside a tenant-scoped service) silently gets `WHERE tenant_id = '<that tenant>'` appended, matching zero rows, since every super admin has `tenant_id = null`. Fix/pattern: use `User::withoutGlobalScopes()->where('is_super_admin', true)` for any super-admin lookup that might run from a tenant request context. This has bitten multiple features independently (`SubscriptionService::superAdmins()`, `PaymentConfirmationService::submit()`) — check every new "look up all super admins" or "sweep every tenant" query for it. Conversely, bulk sweep methods called only from console commands (no tenant context ever active) don't need `withoutGlobalScopes()` — see `InvoiceService::markOverdue()` for the established convention of relying on that instead of defensive-coding every call site.

`IdentifyTenant` is intentionally prepended *before* `SubstituteBindings` in `bootstrap/app.php` (`prependToPriorityList`) so route-model-bound params (`{customer}`) resolve after the tenant scope is active, not before — otherwise cross-tenant access is only caught by the policy layer instead of being structurally impossible at the query level.

RBAC roles are also tenant-scoped: `spatie/laravel-permission`'s Teams feature is keyed on `tenant_id` (`app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId)` before any role/permission check or assignment) — two tenants can each have a "Manager" role with different permissions, fully isolated. The permission catalog itself (flat, dot-notation, e.g. `customers.view`) lives in `config/permissions.php`; per-tenant role→permission grants are provisioned on registration by `ProvisionTenantRolesAction` and kept in sync for existing tenants via `php artisan permissions:sync-tenants` (additive only — never touches a tenant's own customizations).

Super admins (`is_super_admin = true`, `tenant_id = null`) operate only under `/admin/*` routes+`super-admin` middleware; regular tenant users operate under `/*`. A router-level guard (`frontend/src/router/index.js`) redirects each away from the other's routes — see "Dual-audience pages" below for what this means for any page both should reach.

### API response envelope

Every response: `{ "success": bool, "message": string, "code": string|null, "params": array, "data": mixed, "meta": array }` (`App\Traits\ApiResponse`). `message` is always English; `code` is a stable machine-readable string the frontend maps to a translated string via `frontend/src/utils/apiMessages.js`'s `apiErrors.<code>` lookup — add a new `apiErrors.<CODE>` entry (both `en.json` and `km.json`) for any new `App\Exceptions\ApiException(status, message, code, params)` a user might actually see. `params` carries values baked into the English message (a plan name, an amount) for the translated string to interpolate. Validation errors land under `meta.errors.<field>`, not `data`.

### Backend layers (`backend/app/`)

```
Actions/         Single-purpose use-case classes
DTO/             Typed data carriers between requests and services
Enums/           Status/type enums (backed, with a ->label() method)
Http/Controllers/Api/V1/   Thin — validate via Form Request, delegate to a Service, respond via ApiResponse
Http/Requests/    Form Request validation (authorize() + rules(), custom cross-field checks via withValidator()->after())
Http/Resources/   API Resources (response shaping)
Models/           Eloquent + Scopes/TenantScope
Notifications/    See "Notification system" below
Policies/         Authorization
Repositories/     Contracts/ (interfaces) + Eloquent/ (implementations)
Services/         Business logic — the only layer that should touch multiple models/repos
Traits/           BelongsToTenant, ApiResponse
```

Routes are versioned: `routes/api.php` just includes `routes/api/v1.php` under an `api/v1` prefix. Within `v1.php`, route groups are organized by middleware stack (`auth:api` only, `auth:api + tenant + subscription.active`, `auth:api + super-admin`, etc.) — find the right group by what a route needs to bypass (e.g. billing/notifications routes are deliberately reachable even with a lapsed subscription, so a blocked tenant can still see why and fix it).

### Dual-audience pages (a page both tenant users and super admins can reach)

Since the two audiences live under separate top-level route groups (`/` vs `/admin`) with a guard that redirects each away from the other's routes, any single feature both should reach (notifications, account settings) needs **two route registrations pointing at the same component** — one per group, with distinct names (e.g. `notifications` / `admin-notifications`). Whatever links to it picks the name based on `auth.isSuperAdmin`. Forgetting the second registration means the super-admin variant 404s or bounces back to `/admin` via the redirect guard.

### Notification system (`app/Notifications/`)

Every notification follows the same shape: `via()` returns channels, `toDatabase()`/`toArray()` return **only structured data** (an `event` key + params — e.g. `{'event': 'invoice.overdue', 'severity': 'danger', 'invoice_number': ..., 'balance': ...}`), never hardcoded English — the frontend renders the actual message from its own i18n templates keyed by `event` (`frontend/src/composables/useNotificationDisplay.js` → `notifications.events.<event>` in `en.json`/`km.json`). `toMail()`/`toTelegram()` *do* carry real English copy, since those channels have no frontend to render anything.

Two distinct recipient patterns:
- **`User` recipients** (staff/owners/super admins) use the `NotifiesViaPreferredChannels` trait, which reads `User::wantsChannel('system'|'mail'|'telegram')` (per-user notification preferences) to decide `via()`. Telegram for this audience goes through `TelegramAdminChannel`, which always uses the **platform-wide** bot (`config('services.platform_telegram')`).
- **`Customer` recipients** have no preference system and no in-app bell (no customer portal exists yet) — `via()` is written by hand per-notification (send on whatever channel they've actually got: `mail` if `email` is set, `TelegramTenantBotChannel::class` if `telegram_chat_id` is set), and there's no `toDatabase()`. Critically, customer Telegram goes through the **tenant's own** bot (`Customer::tenant->telegram_bot_token`), never the platform bot — customers only ever link Telegram via their studio's bot. Dispatch either way is always `Notification::send($recipients, $notification)`, never `$model->notify()`.

Idempotent scheduled reminders (subscription-expiring, booking-upcoming, invoice-due-soon/overdue) all follow the same shape: a dedicated `*_notified_at`/`*_reminder_sent_at` timestamp column used as a one-shot guard, set only after a successful send, checked with `whereNull(...)` in the sweep query — not a recurring daily nag. Commands are registered in `routes/console.php` via `Schedule::command(...)` (Laravel 12 has no `Kernel.php`).

### Frontend layers (`frontend/src/`)

```
apis/            axios instance (api.js) + one <resource>.api.js per domain — plain named functions, e.g.
                 `export const getCustomersApi = params => http.get('/v1/customers', { params })`
components/common/  Reusable Vuetify wrappers: AppTable (server-driven v-data-table-server), AppDialog,
                 AppForm (vee-validate + yup, `form="id"` + external submit button for use inside dialogs),
                 AppConfirmDialog, AppToolbar, AppDatePicker
layouts/         AuthLayout, DefaultLayout (the shared authenticated shell for both tenant and admin routes)
pages/           Route-level views, lazy-loaded
router/          routes.js (route table) + index.js (auth/guest/plan-feature/super-admin navigation guards)
stores/          Pinia — auth.js (user/token/roles/permissions/plan), app.js (toasts/global UI state)
locales/         en.json, km.json — see i18n rules below
utils/           Yup validation schemas, apiMessages.js, currencyFormat.js
```

Every data table round-trips search/sort/pagination to the API through `AppTable`, matching `BaseRepository::paginateServer()` on the backend — don't build a page-level table that fetches everything and paginates client-side.

`apis/api.js`'s response interceptor auto-refreshes the JWT on a 401 and retries the original request — except for endpoints that legitimately 401 on bad input with no valid token yet (`/v1/auth/login`, `/v1/auth/two-factor/verify`), which are explicitly excluded from that retry-with-refresh logic. Any new "enter a secret, get 401 on failure, no token involved yet" endpoint needs the same exclusion, or a bad password/code shows a generic "Unauthenticated" toast instead of the real error.

### i18n (`frontend/src/locales/en.json`, `km.json`)

Both files must have **exact flattened-key parity** — every key path in one exists in the other. Khmer text must use plain Arabic numerals (`0-9`), never Khmer-script digits (`០-៩`). When adding keys, write a throwaway Node script that loads both JSON files, adds the same key paths to both, and verifies parity + scans the Khmer file for `[០-៩]` before considering the change done.

### Testing conventions (backend)

- `Tests\Concerns\CreatesTenantUsers` (`createTenantWithUser($role)`, `addUserToTenant($tenant, $role)`, `actingAsUser($user)`) is the standard fixture builder — provisions a tenant with real RBAC roles and an active subscription, not mocks.
- Real DB assertions throughout; `Storage::fake()` for uploads, `Http::fake()` for outbound Telegram calls, `Notification::fake()` + `Notification::assertSentTo()` when you need to assert a notification was dispatched to a recipient that has no queryable side effect (e.g. a `Customer`, which has no database channel to check a row count against).
- **JWT guard caching gotcha, test-only:** `JWTGuard::user()` caches whichever user it first resolves for the lifetime of the guard instance, and Laravel's HTTP test harness reuses the same container (and thus the same guard instance) across every `$this->postJson(...)` call within one test method. If a test logs in once, then mutates a user's DB row via a **direct** Eloquent/service call (not through another HTTP request), a later authenticated request in the same test can still see the stale pre-mutation state via `$request->user()`. Fix: call `auth()->forgetGuards()` before the next request that needs fresh state, or refresh the DB state via another real HTTP request (e.g. re-login) instead of a direct model call. This never affects production (each real request gets a fresh process/guard).
