# User Management API

A Laravel REST API implementing user management with authentication, role-based authorization, queued email notifications, and structured error handling.

Built for the Checkproof PHP Code Test.

---

## Quick Start (local, SQLite)

```bash
git clone <repo>
cd kiro-user-management-api
make setup        # installs deps, generates key, runs migrations
make seed         # loads demo users and orders
php artisan serve
```

The API is now available at `http://localhost:8000/api`.

---

## Quick Start (Docker, PostgreSQL + Redis + Mailpit)

```bash
cp .env.example .env
# Set APP_KEY in .env: php artisan key:generate --show
docker compose up -d
docker compose exec app php artisan migrate --seed
```

- API: http://localhost:8000
- Mail viewer (Mailpit): http://localhost:8025

---

## Requirements

- PHP 8.4+
- Composer 2
- SQLite (local dev) or PostgreSQL 16 (Docker)
- Redis (optional, required for queued mail in Docker)

---

## Environment Configuration

Copy `.env.example` to `.env` and adjust:

| Variable | Description | Default |
|---|---|---|
| `APP_KEY` | Laravel encryption key | generate with `php artisan key:generate` |
| `DB_CONNECTION` | `sqlite` or `pgsql` | `sqlite` (local) |
| `QUEUE_CONNECTION` | `sync` or `redis` | `sync` (local), `redis` (Docker) |
| `ADMIN_EMAIL` | Recipient for new-user admin notifications | `admin@example.com` |
| `MAIL_HOST` / `MAIL_PORT` | SMTP host | `127.0.0.1:1025` (Mailpit) |
| `DEPLOY_VERSION` | Included in error logs for correlation | `local` |

---

## Database Setup

```bash
# Fresh migration
php artisan migrate

# Fresh migration with seed data
php artisan migrate:fresh --seed

# Check migration status
php artisan migrate:status
```

### Schema

**users** — `id`, `email` (unique), `password`, `name`, `role` (default: `user`), `active` (default: `true`), `created_at`, `updated_at`

**orders** — `id`, `user_id` (FK → users.id), `created_at`

---

## Seed Data

`make seed` (or `php artisan db:seed`) creates:

| User | Email | Role | Orders |
|---|---|---|---|
| Admin User | admin@example.com | administrator | 3 |
| Manager User | manager@example.com | manager | 5 |
| Alice User | user.a@example.com | user | 10 |
| Bob User | user.b@example.com | user | 0 |
| Inactive User | inactive@example.com | user | 0 (inactive, excluded from API) |
| 20 random users | — | user | 0–8 each |

All seeded passwords are `password123`.

---

## Authentication

`GET /api/users` requires a **Sanctum API token**.

Generate a token for a seeded user:

```bash
php artisan tinker --execute '
$user = \App\Models\User::where("email", "admin@example.com")->first();
echo $user->createToken("dev")->plainTextToken;
'
```

Use the token in requests:

```
Authorization: Bearer <token>
```

`POST /api/users` is **public** — no authentication required (see Authorization Decisions below).

---

## API Reference

### POST /api/users

Create a new user account.

**Request:**

```json
{
  "email": "user@example.com",
  "password": "password123",
  "name": "Jane Doe"
}
```

| Field | Required | Rules |
|---|---|---|
| `email` | yes | valid email, max 255 chars, unique |
| `password` | yes | min 8 characters |
| `name` | yes | 3–50 characters |

**Success Response — 201 Created:**

```json
{
  "id": 1,
  "email": "user@example.com",
  "name": "Jane Doe",
  "created_at": "2024-11-25T12:34:56+00:00"
}
```

**Error Responses:**

| Condition | Status |
|---|---|
| Invalid input | 422 |
| Duplicate email | 422 |
| Server error | 500 |

---

### GET /api/users

List active users. Requires `Authorization: Bearer <token>`.

**Query Parameters:**

| Parameter | Type | Default | Notes |
|---|---|---|---|
| `search` | string | — | Filters by name or email using `LIKE '%term%'` (substring match) |
| `page` | integer ≥ 1 | 1 | Page number |
| `sortBy` | string | `created_at` | `name`, `email`, or `created_at` |

**Sort directions:**
- `name` → ascending
- `email` → ascending
- `created_at` → **descending** (newest first, default)
- Secondary sort is always `id ASC` for deterministic pagination.

**Page size:** 15 per page.

**Success Response — 200 OK:**

```json
{
  "page": 1,
  "users": [
    {
      "id": 1,
      "email": "admin@example.com",
      "name": "Admin User",
      "role": "administrator",
      "created_at": "2024-11-25T12:34:56+00:00",
      "orders_count": 3,
      "can_edit": true
    }
  ]
}
```

**can_edit rules:**

| Authenticated role | Target role | can_edit |
|---|---|---|
| administrator | any | true |
| manager | user | true |
| manager | manager | false |
| manager | administrator | false |
| user | self | true |
| user | any other | false |

**Error Responses:**

| Condition | Status |
|---|---|
| Unauthenticated | 401 |
| Invalid parameter | 422 |
| Server error | 500 |

---

## API Documentation (Scribe)

Interactive API docs are generated via [Scribe](https://scribe.knuckles.wtf).

```bash
# Generate / regenerate docs
make docs
# or
php artisan scribe:generate

# Then start the server and open:
php artisan serve
# → http://localhost:8000/api/docs
```

The docs include:
- Interactive "Try it out" for every endpoint
- Request/response examples with all validation rules
- Postman collection: `storage/app/private/scribe/collection.json`
- OpenAPI spec: `storage/app/private/scribe/openapi.yaml`

---

## Running Tests

```bash
# All tests
make test
# or
php artisan test --compact

# Specific test file
php artisan test --compact --filter=CreateUserApiTest

# With coverage (requires PCOV extension — see below)
make coverage
# or
php artisan test --coverage --min=70 --compact

# HTML coverage report (opens in browser)
php artisan test --coverage-html=coverage-report --compact
# → open coverage-report/index.html
```

Tests use SQLite in-memory and never touch the real database.

### Code Coverage

Coverage is measured with [PCOV](https://github.com/krakjoe/pcov) — a lightweight extension built for coverage only (no debugger overhead).

**Current coverage: 73% (73 tests, 153 assertions)**

| File | Coverage |
|---|---|
| Actions, Controllers, Models, Policy, Middleware, Resources | ~95–100% |
| Listeners (handle + backoff + tries) | ~90% |
| Notifications (toMail content) | ~95% |
| Requests (Scribe metadata helpers) | ~30% |

The 70% minimum threshold excludes Scribe documentation helpers (`bodyParameters`, `queryParameters`) — these are metadata for generated API docs, not business logic. All critical paths — user creation, listing, authorization, email dispatch, error handling — are fully covered.

#### Installing PCOV (one-time setup)

If `php artisan test --coverage` fails with "No code coverage driver available":

```bash
# Build and install PCOV from source (requires phpize)
git clone --depth=1 https://github.com/krakjoe/pcov.git /tmp/pcov
cd /tmp/pcov && phpize && ./configure --enable-pcov && make -j$(nproc) && make install

# Enable the extension (adjust path to match your PHP install)
echo "extension=pcov" >> "$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/pcov.ini"

# Verify
php -m | grep pcov
```

Alternatively, install [Xdebug](https://xdebug.org/docs/install) — it works with the same commands but is slower.

---

## Quality Commands

```bash
make quality
# runs pint (formatter) + phpstan (static analysis)

# Individually:
vendor/bin/pint --format agent         # fix code style
vendor/bin/phpstan analyse             # static analysis
```

---

## Architecture Summary

```
POST /api/users
  StoreUserRequest (validation)
    → UserController::store
      → CreateUser action (DB transaction)
        → User::create
        → UserCreated event (after commit)
          → SendAccountCreatedEmail listener (queued)
          → NotifyAdministrator listener (queued)
      → UserResource (response shape)

GET /api/users
  auth:sanctum middleware
  ListUsersRequest (validation)
    → UserController::index
      → ListUsers action (query: active, search, sort, withCount, paginate)
        → UserResource per item (includes can_edit via UserPolicy::update)
```

Key files:

| File | Responsibility |
|---|---|
| `app/Actions/Users/CreateUser.php` | User persistence + event dispatch |
| `app/Actions/Users/ListUsers.php` | Query composition |
| `app/Http/Controllers/Api/UserController.php` | HTTP coordination only |
| `app/Http/Requests/StoreUserRequest.php` | Create validation |
| `app/Http/Requests/ListUsersRequest.php` | List validation |
| `app/Http/Resources/UserResource.php` | Response shape |
| `app/Policies/UserPolicy.php` | `can_edit` authorization |
| `app/Listeners/SendAccountCreatedEmail.php` | User confirmation email |
| `app/Listeners/NotifyAdministrator.php` | Admin notification email |
| `app/Http/Middleware/AssignRequestId.php` | Request ID correlation |

---

## Assumptions and Design Decisions

### POST /api/users Authorization

`POST /api/users` is **public** (no authentication required). Rationale: the spec defines authentication-dependent behavior for `GET /api/users` but does not restrict who may create accounts. A public registration endpoint is the simplest reasonable interpretation. If this should be admin-only, add `auth:sanctum` and a Gate check to the route.

### Email Delivery

Emails are sent via **queued listeners** (`ShouldQueue`) so the API response is never delayed by SMTP. Each email retries independently (3 attempts, backoff: 10s / 60s / 300s).

In the local dev default (`QUEUE_CONNECTION=sync`), emails are sent synchronously — useful for testing without a worker running.

### Email Failure Semantics

If email delivery fails after the user has been created, the user account remains valid. The failed job is persisted to `failed_jobs` and logged with structured context. Retry manually:

```bash
php artisan queue:retry all
# or by UUID:
php artisan queue:retry <uuid>
```

### Transaction and Queue Atomicity

The user is inserted in a DB transaction. The `UserCreated` event is dispatched **after** the transaction commits, so workers never read uncommitted data. However, DB commit and queue publication are not atomic: if the process crashes between commit and publish, the event is lost and emails are never sent. This is documented and acceptable; a transactional outbox would mitigate it if stricter reliability is needed.

### Duplicate Email

Rejected at the Form Request layer with a 422 validation error. The database unique constraint provides a last-resort guard against concurrent duplicates.

### Pagination

Fixed page size of **15**. Offset pagination matches the required `page` contract. For very deep pagination at scale, cursor pagination should be introduced (requires an intentional API contract change).

### Sort Direction

- `name`, `email`: ascending
- `created_at` (default): descending (newest first)

---

### Search and PostgreSQL

The current search uses `LIKE '%term%'` on both `name` and `email` columns. This has a **driver-dependent case-sensitivity difference**:

| Driver | `LIKE` behaviour | Matches "alice" when searching "Alice"? |
|---|---|---|
| SQLite (local / tests) | case-insensitive for ASCII by default | ✅ yes |
| PostgreSQL (Docker / production) | case-sensitive | ❌ no |

The tests are written against SQLite and pass today. If this project migrates to PostgreSQL as the primary runtime, case-insensitive search will silently break. Two options to discuss as a team before that migration:

**Option A — Use `ILIKE` on PostgreSQL (driver-aware)**
```php
$operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
$query->where('name', $operator, "%{$search}%")
      ->orWhere('email', $operator, "%{$search}%");
```
Pros: minimal change, retains index friendliness parity. Cons: tests still run `LIKE` (SQLite), so the `ILIKE` branch is not exercised by the test suite without a separate PostgreSQL test environment.

**Option B — Normalise with `LOWER()` (portable)**
```php
$query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%'])
      ->orWhereRaw('LOWER(email) LIKE ?', ['%'.strtolower($search).'%']);
```
Pros: identical behaviour on both drivers — tests exercise the exact same code path as production. Cons: requires a functional index (`LOWER(name)`) on PostgreSQL for performance at scale; `LOWER()` on SQLite works fine without one.

**Recommendation:** Option B if you want test-production parity; Option A if you prefer a lighter touch and are comfortable adding a PostgreSQL test environment.

---

## Error Handling

Unexpected errors return a safe 500 response with no internal details:

```json
{
  "message": "An unexpected error occurred.",
  "error_code": "INTERNAL_ERROR",
  "request_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

Every response includes an `X-Request-ID` header. Inbound `X-Request-ID` values are echoed back if valid UUID format, otherwise a new UUID is generated.

---

## Queue and Email Worker

```bash
# Start worker (local)
php artisan queue:work --sleep=1 --tries=3 --timeout=60

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all
```

In Docker, the `queue-worker` service runs automatically with `stop_grace_period: 120s` so in-flight jobs finish before termination.

---

## Graceful Shutdown

**API:** receives `SIGTERM`, finishes in-flight requests, exits. Laravel's built-in shutdown handling applies.

**Worker:** receives `SIGTERM` via `stop_signal: SIGTERM` in Docker Compose. Laravel's `queue:work` stops reserving new jobs and finishes the current job before exiting. The `retry_after` (90s) > worker timeout (60s) prevents concurrent duplicate processing.

---

## Known Limitations and Trade-offs

- No login endpoint — tokens must be generated via `artisan tinker` or seeded. A `/api/auth/login` endpoint is outside scope.
- SQLite is used for local dev/tests; PostgreSQL is used in Docker. Search uses `LIKE '%term%'` — SQLite treats `LIKE` as case-insensitive for ASCII by default, so tests pass without extra setup. See [Search and PostgreSQL](#search-and-postgresql) below for the production consideration.
- No rate limiting — `POST /api/users` is unthrottled. Add `throttle:api` to the route and configure a `RateLimiter` in `AppServiceProvider` if per-IP limiting is required.
- `orders_count` uses `withCount` (a single GROUP BY subquery). For very large datasets, a counter cache would be more efficient.
- Search uses `LIKE '%term%'` which cannot use a standard B-tree index. For large datasets, consider PostgreSQL trigram indexes or full-text search.
