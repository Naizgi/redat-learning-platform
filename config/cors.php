<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',   // local dev Vite
        'http://localhost:3000',   // local dev CRA (if used)
        'https://redatlearninghub.com', // production domain
        'https://www.redatlearninghub.com', // optional: www
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
