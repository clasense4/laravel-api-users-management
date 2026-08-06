# User Management API

A Laravel REST API implementing user management with authentication, role-based authorization, and email notifications.

## Demo

TBA

---

## Quick Start

Pick the mode that fits your setup:

### Mode 1 — Docker (zero dependencies)

> [!TIP]
> Install docker from https://get.docker.com/

```bash
curl -fsSL https://get.docker.com -o install-docker.sh
cat install-docker.sh
sh install-docker.sh --dry-run
sudo sh install-docker.sh
```

No PHP, Composer, or database required. Everything runs in Docker:

```bash
git clone https://github.com/clasense4/laravel-api-users-management.git
cd laravel-api-users-management
make docker-setup      # Build, start services, migrate + seed
make docker-test       # Run tests
make docker-quality    # Run pint + phpstan
```

**Services:** API at `http://localhost:8000/api/docs`, mail UI (Mailpit) at `http://localhost:8025`.

### Mode 2 — Local PHP + Docker Mailpit

Requires PHP 8.3+, Composer 2, and the `sqlite3` extension.

> [!TIP]
> Install the dependency using Mise https://mise.jdx.dev/

```bash
git clone https://github.com/clasense4/laravel-api-users-management.git
cd laravel-api-users-management

make setup                    # Copy .env, install deps, generate key, migrate
docker compose up -d mailpit  # Start mailpit
php artisan serve             # Then open http://localhost:8000/api/docs and http://localhost:8025
make test                     # Run all tests
make quality                  # pint + phpstan
make docs                     # Generate scribe documentation

# Run coverage
make coverage          # Coverage (requires PCOV)

# See: https://github.com/krakjoe/pcov/blob/develop/INSTALL.md
# Build from scratch
git clone https://github.com/krakjoe/pcov.git
cd pcov
phpize
./configure --enable-pcov
make
make test
make install
# Via PECL
pecl install pcov
# Check installation
php -m | grep pcov
```

The API is available at `http://localhost:8000/api/docs`. Mailpit UI at `http://localhost:8025`.

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

## Endpoints, quick start

`GET /api/users` requires a **Sanctum API token** (Bearer). Generate one:

```bash
# From Docker
docker compose exec app php artisan tinker --execute 'echo \App\Models\User::where("email","admin@example.com")->first()->createToken("docs")->plainTextToken'

# From Local
php artisan tinker --execute 'echo \App\Models\User::where("email","admin@example.com")->first()->createToken("docs")->plainTextToken'
```

Use it in requests:
```
Authorization: Bearer <token>
```

Example:
```bash
curl --request GET \
    --get "http://localhost:8000/api/users?search=jane&page=1&sortBy=created_at" \
    --header "Authorization: Bearer 1|Fag4xKoTbE204Lk5Fim9P1hDnQO9JYkYGKQ1VB8X0c4860ff" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"
```

To make the output pretty, can utilize jq to make it easy to read:
```bash
curl --request GET \
    --get "http://localhost:8000/api/users" \
    --header "Authorization: Bearer 1|Fag4xKoTbE204Lk5Fim9P1hDnQO9JYkYGKQ1VB8X0c4860ff" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" | jq -r .
```

`POST /api/users` is **public** — no authentication required.

Example:
```bash
curl --request POST \
    "http://localhost:8000/api/users" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"jane@example.com\",
    \"password\": \"password123\",
    \"name\": \"Jane Doe\"
}"
```

---

## API Reference

### POST /api/users

Create a new user account. Public endpoint.

**Request:**

```json
{
  "email": "jane@example.com",
  "password": "password123",
  "name": "Jane Doe"
}
```

| Field | Required | Rules |
|---|---|---|
| `email` | yes | valid email, max 255 chars, unique |
| `password` | yes | min 8 characters |
| `name` | yes | 3–50 characters |

**Response: Success — 201 Created:**

```json
{
  "id": 1,
  "email": "user@example.com",
  "name": "Jane Doe",
  "created_at": "2024-11-25T12:34:56+00:00"
}
```

**Errors:** 422 (validation / duplicate email), 500 (unexpected — safe envelope with `request_id`).

---

### GET /api/users

List active users. Requires `Authorization: Bearer <token>`.

**Query Parameters:**

| Parameter | Type | Default | Notes |
|---|---|---|---|
| `search` | string | — | Substring match on name and email |
| `page` | integer ≥ 1 | 1 | Page number (15 per page) |
| `sortBy` | string | `created_at` | `name`, `email`, or `created_at` |

**Sort directions:** `name` and `email` are ascending. `created_at` is descending (newest first). Secondary sort is always `id ASC` for deterministic pagination.

**Response: Success — 200 OK:**

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
| manager | manager / administrator | false |
| user | self | true |
| user | any other | false |

**Errors:** 401 (unauthenticated), 422 (invalid parameters), 500 (unexpected).

---

## Architecture

> [!IMPORTANT]
> Read more at [architecture.md](architecture.md)

```
POST /api/users
  StoreUserRequest (validation)
    → UserController::store
      → CreateUser action (DB transaction)
        → User::create
        → UserCreated event (dispatched after commit)
          → SendAccountCreatedEmail listener (queued, 3 retries)
          → NotifyAdministrator listener (queued, 3 retries)
      → CreatedUserResource (response shape)

GET /api/users
  auth:sanctum middleware
  ListUsersRequest (validation)
    → UserController::index
      → ListUsers action (query: active=true, search, sort, withCount, paginate)
        → ListedUserResource per item (can_edit via UserPolicy::update)
```

Key files:

| File | Responsibility |
|---|---|
| `app/Actions/Users/CreateUser.php` | User persistence + event dispatch |
| `app/Actions/Users/ListUsers.php` | Query composition with sort allowlisting |
| `app/Http/Controllers/Api/UserController.php` | HTTP coordination only |
| `app/Http/Requests/StoreUserRequest.php` | Create validation |
| `app/Http/Requests/ListUsersRequest.php` | List validation |
| `app/Http/Resources/CreatedUserResource.php` | POST response shape |
| `app/Http/Resources/ListedUserResource.php` | GET response shape |
| `app/Policies/UserPolicy.php` | `can_edit` authorization (role-based) |
| `app/Listeners/SendAccountCreatedEmail.php` | Welcome email to new user |
| `app/Listeners/NotifyAdministrator.php` | Admin notification email |
| `app/Http/Middleware/AssignRequestId.php` | Request ID generation / correlation |
| `bootstrap/app.php` | Global exception handling (safe 500 responses) |

---

## Design Decisions

### POST /api/users is public

The spec defines authentication-dependent behavior for `GET /api/users` but does not restrict who may create accounts. A public registration endpoint is the simplest reasonable interpretation. If this should be admin-only, add `auth:sanctum` and a Gate check.

### Email delivery

Emails are sent via **queued listeners** (`ShouldQueue`) so the API response is never delayed by SMTP. In the default configuration (`QUEUE_CONNECTION=sync`), emails are sent synchronously — no queue worker required.

Each email retries independently: 3 attempts with progressive backoff (10s / 60s / 300s). Failed jobs are logged with structured context.

### Transaction and queue atomicity

The user is inserted in a DB transaction. The `UserCreated` event is dispatched **after** the transaction commits, so listeners never read uncommitted data. However, DB commit and event dispatch are not atomic: if the process crashes between commit and dispatch, the event is lost. This is a known trade-off; a transactional outbox would mitigate it if stricter reliability is needed.

### Pagination

Fixed page size of **15**. Offset pagination with a secondary `ORDER BY id` prevents duplicates across pages.

### Error handling

Unexpected errors return a safe 500 envelope:

```json
{
  "message": "An unexpected error occurred.",
  "error_code": "INTERNAL_ERROR",
  "request_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

Every response includes an `X-Request-ID` header. Inbound `X-Request-ID` values are echoed back if valid UUID v4; otherwise a new UUID is generated and attached to all log entries for the request.

### Search and PostgreSQL

Search uses `LIKE '%term%'` on name and email. `LIKE` is case-insensitive on SQLite (test/dev) but case-sensitive on PostgreSQL. If migrating to PostgreSQL, either switch to `ILIKE` (driver-aware) or normalise with `LOWER()` (portable, test-production parity).

---

## Running Tests

```bash
# All tests
make test
# → clears bootstrap cache first, then runs php artisan test --compact

# Specific file
php artisan test --compact --filter=CreateUserApiTest

# Coverage (requires PCOV)
make coverage
```

Tests use SQLite in-memory (`:memory:`) and never touch the real database. The `test` and `coverage` Makefile targets clear `bootstrap/cache/*.php` before running to ensure `phpunit.xml` env settings are not overridden by a stale cache.

---

## API Documentation (Scribe)

```bash
make docs
# → http://localhost:8000/api/docs
```

Includes interactive "Try it out", Postman collection, and OpenAPI spec.

---

## Known Limitations

- No login endpoint — tokens are generated via `artisan tinker` (out of scope).
- No rate limiting — add `throttle:api` middleware if needed.
- Search uses `LIKE '%term%'` which cannot use a standard B-tree index at scale.
- `orders_count` uses `withCount`; a counter cache would be more efficient for large datasets.
