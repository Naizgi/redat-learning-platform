<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Here you may configure the rate limiting options for your application.
    | These settings are used by the throttle middleware to control the
    | maximum number of requests that can be made in a given time period.
    |
    */

    'limiters' => [

        'api' => [
            'decay_seconds' => 60,
            'max_attempts' => 100, // 60 requests per minute
        ],

        'auth' => [
            'decay_seconds' => 60,
            'max_attempts' => 100, // 10 login attempts per minute
        ],

    ],

];