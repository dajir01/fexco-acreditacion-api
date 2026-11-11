<?php

return [
    'credential_pin' => [
        'per_minute' => (int) env('PIN_ATTEMPTS_PER_MINUTE', 5),
        'window_attempts' => (int) env('PIN_ATTEMPTS_WINDOW', 20),
        'window_minutes' => (int) env('PIN_ATTEMPTS_WINDOW_MINUTES', 10),
    ],
    'credential_registration' => [
        'per_minute' => (int) env('REGISTRATION_ATTEMPTS_PER_MINUTE', 3),
        'window_attempts' => (int) env('REGISTRATION_ATTEMPTS_WINDOW', 10),
        'window_minutes' => (int) env('REGISTRATION_ATTEMPTS_WINDOW_MINUTES', 10),
    ],
    'credential_download' => [
        'per_minute' => (int) env('DOWNLOAD_ATTEMPTS_PER_MINUTE', 6),
        'window_attempts' => (int) env('DOWNLOAD_ATTEMPTS_WINDOW', 20),
        'window_minutes' => (int) env('DOWNLOAD_ATTEMPTS_WINDOW_MINUTES', 10),
    ],
    'credential_access_token' => [
        'ttl_minutes' => (int) env('CREDENTIAL_ACCESS_TOKEN_TTL_MINUTES', 10),
    ],
];
