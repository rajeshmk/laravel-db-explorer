<?php

declare(strict_types=1);

return [
    'enabled' => env('DB_EXPLORER_ENABLED', false),

    'allowed_environments' => [
        'local',
    ],

    'per_page' => 25,

    'date_format' => 'M j, Y',
    'datetime_format' => 'M j, Y H:i:s',

    /*
    |--------------------------------------------------------------------------
    | Default Sort Direction
    |--------------------------------------------------------------------------
    |
    | Controls the default sort direction when a user does not explicitly
    | set a `direction` query parameter. Defaults to `desc` (latest first).
    |
    */
    'default_sort_direction' => env('DB_EXPLORER_DEFAULT_SORT_DIRECTION', 'desc'),

    'middleware' => ['web', 'auth'],
];
