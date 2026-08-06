<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\CreateUser;
use App\Actions\Users\ListUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Users
 *
 * Endpoints for creating and listing users.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly ListUsers $listUsers,
    ) {}

    /**
     * Create a user
     *
     * Creates a new user account. Hashes the password, stores the user, and
     * asynchronously dispatches two emails: a confirmation to the new user and
     * a notification to the system administrator.
     *
     * This endpoint is public — no authentication required.
     *
     * @unauthenticated
     *
     * @response 201 scenario="Created" {
     *   "id": 1,
     *   "email": "jane@example.com",
     *   "name": "Jane Doe",
     *   "created_at": "2024-11-25T12:34:56+00:00"
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The email field must be a valid email address.",
     *   "errors": {
     *     "email": ["The email field must be a valid email address."]
     *   }
     * }
     * @response 422 scenario="Duplicate email" {
     *   "message": "The email has already been taken.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     * @response 500 scenario="Unexpected error" {
     *   "message": "An unexpected error occurred.",
     *   "error_code": "INTERNAL_ERROR",
     *   "request_id": "550e8400-e29b-41d4-a716-446655440000"
     * }
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->createUser->execute($request->validated());

        UserResource::withoutWrapping();

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * List active users
     *
     * Returns a paginated list of active users. Each user includes their order
     * count and a `can_edit` flag calculated against the authenticated user's role.
     *
     * **can_edit rules:**
     * | Authenticated role | Target role | can_edit |
     * |---|---|---|
     * | administrator | any | true |
     * | manager | user | true |
     * | manager | manager or administrator | false |
     * | user | self | true |
     * | user | any other | false |
     *
     * **Sorting:**
     * - `name` and `email` sort ascending.
     * - `created_at` (default) sorts descending (newest first).
     * - A secondary sort on `id` ASC ensures deterministic pagination.
     *
     * @authenticated
     *
     * @response 200 scenario="Success" {
     *   "page": 1,
     *   "users": [
     *     {
     *       "id": 1,
     *       "email": "admin@example.com",
     *       "name": "Admin User",
     *       "role": "administrator",
     *       "created_at": "2024-11-25T12:34:56+00:00",
     *       "orders_count": 3,
     *       "can_edit": true
     *     },
     *     {
     *       "id": 2,
     *       "email": "jane@example.com",
     *       "name": "Jane Doe",
     *       "role": "user",
     *       "created_at": "2024-11-24T09:00:00+00:00",
     *       "orders_count": 0,
     *       "can_edit": false
     *     }
     *   ]
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Invalid sort field" {
     *   "message": "The selected sort by is invalid.",
     *   "errors": {
     *     "sortBy": ["The selected sort by is invalid."]
     *   }
     * }
     */
    public function index(ListUsersRequest $request): JsonResponse
    {
        $paginator = $this->listUsers->execute(
            search: $request->validated('search'),
            sortBy: $request->validated('sortBy', 'created_at') ?? 'created_at',
        );

        // Resolve each resource with the current request so that
        // UserResource::toArray() has access to $request->user() for can_edit.
        $users = $paginator->getCollection()
            ->map(fn ($user) => (new UserResource($user))->resolve($request));

        return response()->json([
            'page' => $paginator->currentPage(),
            'users' => $users->values(),
        ]);
    }
}
