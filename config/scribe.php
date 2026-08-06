<?php

use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe\Matching\RouteMatcher;

use function Knuckles\Scribe\Config\configureStrategy;

return [
    'title' => 'User Management API',

    'description' => 'A clean Laravel REST API demonstrating user management with role-based authorization, queued email notifications, and structured error handling.',

    'intro_text' => <<<'INTRO'
        ## Authentication

        `GET /api/users` requires a Sanctum Bearer token. `POST /api/users` is public.

        ### Quick Start — Copy a token

        Run any of these commands, then paste the output into the **Authorization** field above:

        **Administrator** (can edit everyone):
        ```bash
        # Without Docker
        php artisan tinker --execute 'echo \App\Models\User::where("email","admin@example.com")->first()->createToken("docs")->plainTextToken'
        # With Docker
        docker compose exec app php artisan tinker --execute 'echo \App\Models\User::where("email","admin@example.com")->first()->createToken("docs")->plainTextToken'
        ```

        **Manager** (can edit regular users only):
        ```bash
        # Without Docker
        php artisan tinker --execute 'echo \App\Models\User::where("email","manager@example.com")->first()->createToken("docs")->plainTextToken'
        # With Docker
        docker compose exec app php artisan tinker --execute 'echo \App\Models\User::where("email", "manager@example.com")->first()->createToken("docs")->plainTextToken'
        ```

        **Regular User** (can edit self only):
        ```bash
        # Without Docker
        php artisan tinker --execute 'echo \App\Models\User::where("email","user.a@example.com")->first()->createToken("docs")->plainTextToken'
        # With Docker
        docker compose exec app php artisan tinker --execute 'echo \App\Models\User::where("email", "user.a@example.com")->first()->createToken("docs")->plainTextToken'
        ```

        All seed passwords are `password123`.
        INTRO,

    'base_url' => config('app.url'),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [],
        ],
    ],

    'type' => 'laravel',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/api/docs',
        'middleware' => [],
    ],

    'external' => [
        'html_attribute_data' => [],
    ],

    'try_it_out' => [
        'enabled' => true,
        // Explicitly set so try-it-out always hits the correct port.
        // Override with SCRIBE_TRY_IT_OUT_BASE_URL in .env if deploying elsewhere.
        'base_url' => env('SCRIBE_TRY_IT_OUT_BASE_URL', config('app.url')),
        'use_csrf' => false,
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,
        'default' => false,
        'in' => 'bearer',
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{YOUR_API_TOKEN}',
        'extra_info' => 'See the Introduction section for copy-paste ready token commands.',
    ],

    'examples' => [
        'faker_seed' => 1234,
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    // Code example tabs shown for every endpoint.
    // Supported: bash, javascript, php, python
    'example_languages' => [
        'bash',
        'javascript',
        'php',
        'python',
    ],

    // Generate a Postman collection alongside the HTML docs.
    'postman' => [
        'enabled' => true,
        'overrides' => [],
    ],

    // Generate an OpenAPI 3.0 spec alongside the HTML docs.
    'openapi' => [
        'enabled' => true,
        'overrides' => [],
    ],

    'groups' => [
        'default' => 'Endpoints',
        'order' => [
            'Users',
        ],
    ],

    'tags' => [
        'aliases' => [],
        'case' => null,
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    'fractal' => [
        'serializer' => null,
    ],

    'routeMatcher' => RouteMatcher::class,

    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                only: ['GET *'],
                config: ['app.debug' => false]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    'database_connections_to_transact' => [config('database.default')],
];
