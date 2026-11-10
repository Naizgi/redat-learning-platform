<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
        'https://redatlearninghub.com',
        'https://www.redatlearninghub.com',
        'https://api.redatlearninghub.com',
    ],

    'allowed_origins_patterns' => [
        '#^https://([a-z0-9-]+\.)?redatlearninghub\.com$#',
    ],

    // ⚠️ MUST list headers explicitly when Authorization is used
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'Origin',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Range',
        'If-None-Match',
        'If-Modified-Since',
    ],

    'exposed_headers' => [
        'Content-Disposition',
        'Content-Length',
        'Content-Range',
        'Accept-Ranges',
        'X-Filename',
    ],

    // Cache preflight for 24h — faster logins
    'max_age' => 86400,

    // ✅ Must be true so browser sends the Authorization header
    'supports_credentials' => true,
];