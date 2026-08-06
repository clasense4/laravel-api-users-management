<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListedUserResource extends JsonResource
{
    /**
     * GET /api/users response shape per user.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'orders_count' => $this->orders_count ?? 0,
            'can_edit' => $request->user()->can('update', $this->resource),
        ];
    }
}
