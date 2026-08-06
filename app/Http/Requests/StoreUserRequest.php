<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // POST /api/users is public: any caller may create an account.
        // See README.md "Authorization Decisions" for rationale.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'name' => ['required', 'string', 'min:3', 'max:50'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The user\'s email address. Must be unique.',
                'example' => 'jane@example.com',
            ],
            'password' => [
                'description' => 'The user\'s password. Minimum 8 characters. Stored hashed; never returned.',
                'example' => 'secret1234',
            ],
            'name' => [
                'description' => 'The user\'s display name. Between 3 and 50 characters.',
                'example' => 'Jane Doe',
            ],
        ];
    }
}
