<?php

return [
    'encryption' => [
        'key' => env('HMS_ENCRYPTION_KEY'),
        'key_id' => env('HMS_ENCRYPTION_KEY_ID', 'k1'),
        'previous_keys' => env('HMS_ENCRYPTION_PREVIOUS_KEYS'),
        'search_key' => env('HMS_SEARCH_KEY'),
        'require_dedicated_keys' => env('APP_ENV') === 'production',
    ],
    'token_minutes' => (int) env('SANCTUM_EXPIRATION', 480),
    'retention_years' => (int) env('HMS_RETENTION_YEARS', 7),
    'max_upload_kb' => (int) env('HMS_MAX_UPLOAD_KB', 10240),
    'upload_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'doc', 'docx'],
];
