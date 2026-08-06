<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    private const PAGE_SIZE = 15;

    /**
     * Allowlist mapping public sort keys to database columns.
     * Values are [column, direction].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORT_MAP = [
        'name' => ['name', 'asc'],
        'email' => ['email', 'asc'],
        'created_at' => ['created_at', 'desc'],
    ];

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(?string $search, string $sortBy = 'created_at'): LengthAwarePaginator
    {
        [$sortColumn, $sortDirection] = self::SORT_MAP[$sortBy] ?? self::SORT_MAP['created_at'];

        return User::query()
            ->select(['id', 'email', 'name', 'role', 'created_at'])
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
            ->orderBy('id') // deterministic secondary sort
            ->paginate(self::PAGE_SIZE);
    }
}
