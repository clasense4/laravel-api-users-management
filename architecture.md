# Architecture

## Architectural Goals

The solution should demonstrate:

- Clear responsibility boundaries.
- Framework-native Laravel design.
- Explicit authorization.
- Reliable asynchronous side effects.
- Safe and observable errors.
- Deployment-aware process lifecycle.
- Efficient database access.
- Easy extension without speculative abstraction.

The implementation remains intentionally small. Two endpoints do not justify a distributed architecture or a forest of interfaces wearing tiny business suits.

---

## System Context

```text
API Consumer
    |
    v
Laravel API
    |
    +--> PostgreSQL
    |
    +--> Redis Queue
             |
             v
        Queue Worker
          /      \
         v        v
 User Email   Administrator Email
```

Operational flow:

```text
Laravel API / Worker
    |
    +--> Structured Logs
    |
    +--> Exception Monitoring (optional)
    |
    +--> Health Checks
```

---

## Recommended Structure

```text
app/
├── Actions/
│   └── Users/
│       ├── CreateUser.php
│       └── ListUsers.php
├── Enums/
│   └── UserRole.php
├── Events/
│   └── UserCreated.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── UserController.php
│   ├── Middleware/
│   │   └── AssignRequestId.php
│   ├── Requests/
│   │   ├── StoreUserRequest.php
│   │   └── ListUsersRequest.php
│   └── Resources/
│       ├── CreatedUserResource.php
│       └── ListedUserResource.php
├── Listeners/
│   ├── SendAccountCreatedEmail.php
│   └── NotifyAdministrator.php
├── Models/
│   ├── User.php
│   └── Order.php
├── Notifications/
│   ├── AccountCreated.php
│   └── NewUserRegistered.php
└── Policies/
    └── UserPolicy.php
```

Supporting structure:

```text
database/
├── factories/
├── migrations/
└── seeders/

tests/
├── Feature/
└── Unit/
```

---

## Request Architecture

## Create User Flow

```text
POST /api/users
    |
    v
StoreUserRequest
    |
    v
UserController::store
    |
    v
CreateUser Action
    |
    +--> Database Transaction
    |       |
    |       +--> Hash password
    |       +--> Insert user
    |
    +--> UserCreated event after commit
            |
            +--> SendAccountCreatedEmail
            +--> NotifyAdministrator
    |
    v
CreatedUserResource
    |
    v
201 Created
```

### Responsibilities

#### StoreUserRequest

- Validate input.
- Keep HTTP validation outside the controller.
- Return only validated values.

#### UserController

- Coordinate the use case.
- Return the HTTP response.
- Contain no business rules.

#### CreateUser Action

- Hash the password.
- Persist the user.
- Define the transaction boundary.
- Dispatch the post-commit event.

#### Event and Listeners

- Decouple persistence from email delivery.
- Allow each email to retry independently.
- Support future side effects without changing the action.

#### CreatedUserResource / ListedUserResource

- Define explicit API contracts per endpoint.
- `CreatedUserResource` — POST response (id, email, name, created_at).
- `ListedUserResource` — GET response (adds role, orders_count, can_edit).
- No conditional logic based on authentication state.

---

## List Users Flow

```text
GET /api/users
    |
    v
Authentication Middleware
    |
    v
ListUsersRequest
    |
    v
UserController::index
    |
    v
ListUsers Action
    |
    +--> active users only
    +--> grouped search
    +--> allowlisted sorting
    +--> order count
    +--> pagination
    |
    v
ListedUserResource per user
    |
    +--> UserPolicy::update (can_edit)
    |
    v
200 OK
```

Conceptual query:

```php
User::query()
    ->select([
        'id',
        'email',
        'name',
        'role',
        'created_at',
    ])
    ->where('active', true)
    ->withCount('orders')
    ->when($search, function (Builder $query, string $search): void {
        $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    })
    ->orderBy($sortColumn, $sortDirection)
    ->orderBy('id')
    ->paginate(15);
```

The query should remain readable as the business requirement rather than being hidden behind a generic repository.

---

## Domain Model

## User

Responsibilities:

- Store identity and role.
- Own many orders.
- Hide password from serialization as defense in depth.
- Cast role to `UserRole`.
- Cast active status to boolean.

## Order

Responsibilities:

- Belong to one user.
- Support efficient counting through an indexed `user_id`.

Relationship:

```text
User 1 ---- * Order
```

No order-management endpoint is required.

---

## Authorization Architecture

Authorization belongs in `UserPolicy`.

```php
public function update(User $actor, User $target): bool
{
    return match ($actor->role) {
        UserRole::Administrator => true,
        UserRole::Manager => $target->role === UserRole::User,
        UserRole::User => $actor->is($target),
    };
}
```

The API resource uses the same policy:

```php
'can_edit' => $request->user()->can('update', $this->resource),
```

Benefits:

- One authorization source.
- Reusable by future update endpoints.
- No role conditionals in controllers.
- Easy table-driven testing.
- Reduced risk of permission drift.

---

## Error Handling Architecture

## Expected Errors

Use Laravel's normal mechanisms:

- Form Request validation.
- Authentication middleware.
- Authorization exceptions.
- Model-not-found exceptions where relevant.

## Unexpected Errors

Unhandled exceptions flow through Laravel's global exception handler.

```text
Unexpected Exception
    |
    +--> Report
    |      |
    |      +--> Structured log
    |      +--> External monitoring when configured
    |
    +--> Render
           |
           +--> Safe JSON response
           +--> Request ID
```

Broad controller-level `try/catch` blocks should be avoided.

Local catches are appropriate only when the application can:

- Recover.
- Translate an exception.
- Add important business context.
- Intentionally retry or suppress the operation.

---

## Request ID Middleware

`AssignRequestId` should:

1. Read `X-Request-ID`.
2. Validate or generate a UUID.
3. Add it to the log context.
4. Attach it to the response header.
5. Make it available to error rendering.

```text
Incoming Request
    |
    +--> Existing valid ID? ---- yes --> reuse
    |                         no
    |                          |
    |                          v
    |                     generate UUID
    |
    +--> Add logging context
    |
    +--> Process request
    |
    +--> Return X-Request-ID header
```

---

## Logging Architecture

Production logs should be structured JSON written to stdout or stderr.

Example:

```json
{
  "level": "error",
  "message": "Unable to process request",
  "request_id": "01989c70-6fcb-7fc2-b2e8-33fe915c17b7",
  "route": "api.users.store",
  "method": "POST",
  "user_id": 42,
  "deployment_version": "abc1234",
  "exception_class": "Illuminate\\Database\\QueryException"
}
```

Never log:

- Passwords.
- Authorization headers.
- API tokens.
- SMTP credentials.
- Database credentials.
- Full sensitive request bodies.

---

## Queue and Email Architecture

## Event Design

```text
CreateUser
    |
    v
UserCreated
    |
    +--> SendAccountCreatedEmail
    |
    +--> NotifyAdministrator
```

Each listener should implement `ShouldQueue`.

Benefits:

- HTTP latency is not coupled to SMTP.
- Each email retries independently.
- Workers can scale separately.
- New side effects can be added without changing `CreateUser`.

## Transaction Boundary

```text
Begin Transaction
    |
    +--> Insert User
    |
Commit
    |
    +--> Publish UserCreated
```

Dispatching after commit prevents a worker from reading uncommitted data.

It does not make database commit and queue publication atomic.

Possible failure window:

```text
Database commit succeeds
    |
Application fails before queue publication
```

For this challenge:

- Dispatch after commit.
- Report failures.
- Document the limitation.

For stricter reliability, introduce a transactional outbox later.

## Retry Policy

Example:

```php
public int $tries = 3;

public function backoff(): array
{
    return [10, 60, 300];
}
```

Permanent failure behavior:

- Persist to `failed_jobs`.
- Log job UUID, user ID, and notification type.
- Report externally when configured.
- Support documented retry commands.

## Delivery Semantics

The queue provides at-least-once processing.

A provider may accept an email before a worker crashes, causing a retry and possible duplicate message.

Possible future protections:

- Provider idempotency key.
- Delivery ledger.
- Unique notification key.
- Transactional outbox.

These are documented, not implemented prematurely.

---

## Container Architecture

Docker Compose services:

```text
app
postgres
redis
queue-worker
mailpit
```

The API and worker should use the same immutable image with different commands.

```text
Application image
├── API process
└── Queue worker process
```

This reduces runtime drift.

### Correct Worker Command

Use exec form:

```dockerfile
CMD ["php", "artisan", "queue:work", "redis", "--sleep=1", "--tries=3", "--timeout=60"]
```

Or:

```sh
exec php artisan queue:work redis --sleep=1 --tries=3 --timeout=60
```

Avoid:

```dockerfile
CMD sh -c "php artisan queue:work"
```

The shell may remain PID 1 and prevent reliable signal forwarding.

### Docker Compose Worker Example

```yaml
queue-worker:
  build:
    context: .
  command:
    - php
    - artisan
    - queue:work
    - redis
    - --sleep=1
    - --tries=3
    - --timeout=60
  stop_signal: SIGTERM
  stop_grace_period: 120s
  restart: unless-stopped
```

---

## Graceful Shutdown Architecture

## API Lifecycle

```text
SIGTERM
    |
    v
Readiness becomes false
    |
    v
Load balancer stops new traffic
    |
    v
In-flight requests finish
    |
    v
Process exits
```

## Worker Lifecycle

```text
SIGTERM
    |
    v
Stop reserving jobs
    |
    v
Finish current job
    |
    v
Acknowledge job
    |
    v
Exit
```

If the worker cannot finish, the unacknowledged job becomes visible again after `retry_after`.

## Timeout Relationship

```text
maximum job duration
    < worker timeout
    < retry_after
    < termination grace period
```

Recommended values:

| Setting | Value |
|---|---:|
| Expected email processing | `< 30 seconds` |
| Worker timeout | `60 seconds` |
| Redis `retry_after` | `90 seconds` |
| Container grace period | `120 seconds` |

This prevents:

- Concurrent duplicate processing caused by an early retry.
- Kubernetes killing a job before Laravel's timeout.
- Unbounded job execution.

---

## Kubernetes Deployment Model

Kubernetes is an optional production example, not required for local setup.

Use separate deployments:

```text
user-api
user-api-worker
```

### API Deployment

- Serves HTTP traffic.
- Exposes liveness and readiness checks.
- Supports rolling deployment.
- Becomes unready before termination.

### Worker Deployment

- Consumes Redis jobs.
- Receives `SIGTERM` directly.
- Completes current work where possible.
- Scales independently.

Example:

```yaml
spec:
  terminationGracePeriodSeconds: 120
  containers:
    - name: worker
      image: user-api:<commit-sha>
      command:
        - php
        - artisan
        - queue:work
        - redis
        - --sleep=1
        - --tries=3
        - --timeout=60
```

Use immutable image tags such as commit SHAs rather than `latest`.

---

## Health Architecture

## Liveness

Answers:

> Is the process alive?

Liveness should remain lightweight.

It should not fail solely because PostgreSQL or Redis is temporarily unavailable, which could cause restart storms.

## Readiness

Answers:

> Should this instance receive traffic?

Readiness may validate access to dependencies required to serve requests.

During termination, readiness should become false before the process exits.

---

## Database Architecture

## Initial Indexes

```text
users.email UNIQUE
users(active, created_at)
orders.user_id
```

Indexes should later be validated through query plans and realistic data.

## Role Constraint

`role` is a string column with a CHECK constraint enforcing the three valid values:

```sql
CHECK (role IN ('administrator', 'manager', 'user'))
```

The constraint is applied via a migration on PostgreSQL. SQLite does not support
`ALTER TABLE ... ADD CONSTRAINT` after creation, so on SQLite the PHP `UserRole`
enum cast is the guard. Both environments reject invalid roles through Eloquent.

## Search Evolution

Initial:

```text
LIKE '%search%'
```

Note: SQLite (local/tests) treats `LIKE` as case-insensitive for ASCII by default.
PostgreSQL treats `LIKE` as case-sensitive; use `ILIKE` or `LOWER()` when migrating.
See README "Search and PostgreSQL" for the full discussion.

Evolution:

1. PostgreSQL trigram indexes.
2. Full-text search.
3. Dedicated search engine when justified.

## Pagination Evolution

Initial:

- Offset pagination.
- Preserves the required `page` contract.

Future:

- Cursor pagination for deep traversal.
- Requires stable unique ordering.
- Requires an intentional API contract change.

## Order Count Evolution

Initial:

```php
withCount('orders')
```

Future options:

- Counter cache.
- Aggregate table.
- Event-driven projection.
- Explicitly invalidated cache.

---

## Deployment-Safe Migrations

Rolling deployments may run old and new application versions simultaneously.

Use expand-contract:

```text
1. Add a new nullable column or table
2. Deploy code compatible with both schemas
3. Backfill data
4. Switch reads and writes
5. Remove the old structure later
```

Avoid destructive schema changes in the same rollout as code that requires them.

---

## Testing Architecture

```text
Feature Tests
├── API contract
├── validation
├── authentication
├── query behavior
├── notification dispatch
├── safe failures
└── request correlation

Unit Tests
├── UserPolicy
├── focused action behavior
└── small pure components
```

Feature tests provide the highest value because the challenge centers on API behavior.

Policy tests should be data-driven so the permission matrix is explicit.

---

## CI Architecture

Recommended pipeline:

```text
Install dependencies
    |
    +--> Pint
    |
    +--> PHPStan / Larastan
    |
    +--> Test suite
    |
    +--> Coverage threshold
    |
    +--> Build container
```

A local command should mirror CI:

```bash
composer quality
```

or:

```bash
make quality
```

---

## Key Decisions

| Decision | Reason |
|---|---|
| Form Requests | Framework-native validation |
| Thin controllers | Separate HTTP concerns from use cases |
| Actions | Clear endpoint-specific operations |
| Direct Eloquent usage | Avoid unnecessary repository indirection |
| API Resources | Explicit response contract |
| Policy for `can_edit` | Single reusable authorization source |
| Event plus two listeners | Independent asynchronous side effects |
| Redis queue | Practical retries and worker scaling |
| After-commit dispatch | Avoid reading uncommitted data |
| Request IDs | Correlate consumer failures with diagnostics |
| Structured stdout logs | Suitable for containers |
| Separate API and worker processes | Independent lifecycle and scaling |
| Exec-form commands | Correct signal propagation |
| Offset pagination initially | Matches required API contract |
| Document future scale paths | Show awareness without over-engineering |

---

## Deliberate Non-Decisions

The architecture does not add:

- Repository interfaces with one implementation.
- Generic base services.
- CQRS infrastructure.
- Event sourcing.
- Microservices.
- Mandatory Kubernetes.
- Dedicated search infrastructure.
- Transactional outbox.
- Distributed tracing infrastructure.
- Premature caching.

Their absence is intentional.

---

## Architecture Quality Statement

> The implementation is intentionally simple, while its authorization boundaries, failure modes, deployment lifecycle, and scaling paths are explicitly understood.
