<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        // role, orders_count, and can_edit are list-only fields —
        // only include them when the request is authenticated (GET /api/users).
        // POST /api/users is public and returns only the base fields.
        if ($request->user() !== null && $this->role !== null) {
            $data['role'] = $this->role->value;
            $data['can_edit'] = $request->user()->can('update', $this->resource);
        }

        if ($this->resource->relationLoaded('orders') || array_key_exists('orders_count', $this->resource->getAttributes())) {
            $data['orders_count'] = $this->orders_count ?? 0;
        }

        return $data;
    }
}
