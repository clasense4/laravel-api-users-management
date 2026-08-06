<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public $collects = UserResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'page' => $this->currentPage(),
            'users' => $this->collection,
        ];
    }

    /**
     * Customize the outgoing response — remove the default "data" wrapper
     * so the response root contains "page" and "users" directly.
     *
     * @param  JsonResponse  $response
     */
    public function withResponse(Request $request, $response): void
    {
        $data = $response->getData(true);

        // Laravel wraps ResourceCollection in {"data": {...}} — unwrap it
        if (isset($data['data'])) {
            $response->setData($data['data']);
        }
    }
}
