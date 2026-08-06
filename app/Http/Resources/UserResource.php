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

        // Include list-specific fields only when they are available
        // (role is always present; orders_count and can_edit are list-only)
        if ($this->role !== null) {
            $data['role'] = $this->role->value;
        }

        if ($this->resource->relationLoaded('orders') || array_key_exists('orders_count', $this->resource->getAttributes())) {
            $data['orders_count'] = $this->orders_count ?? 0;
        }

        if ($request->user() !== null && $this->role !== null) {
            $data['can_edit'] = $request->user()->can('update', $this->resource);
        }

        return $data;
    }
}
