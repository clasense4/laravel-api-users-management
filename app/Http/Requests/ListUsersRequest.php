<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware; gate open for any authenticated user
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sortBy' => ['sometimes', 'nullable', 'string', 'in:name,email,created_at'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function queryParameters(): array
    {
        return [
            'search' => [
                'description' => 'Filter users by name or email (case-insensitive partial match).',
                'required' => false,
                'example' => 'jane',
            ],
            'page' => [
                'description' => 'Page number. Defaults to 1. Page size is fixed at 15.',
                'required' => false,
                'example' => 1,
            ],
            'sortBy' => [
                'description' => 'Sort field. `name` and `email` sort ascending; `created_at` sorts descending. Defaults to `created_at`.',
                'required' => false,
                'example' => 'created_at',
            ],
        ];
    }
}
