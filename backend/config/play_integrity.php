<?php

$serviceAccountPath = env(
    'PLAY_INTEGRITY_SERVICE_ACCOUNT_PATH',
    'storage/app/private/play-integrity-service-account.json',
);

return [
    'enabled' => (bool) env('PLAY_INTEGRITY_ENABLED', false),
    'package_name' => env('PLAY_INTEGRITY_PACKAGE_NAME', 'id.co.attendly.mobile'),
    'service_account_path' => str_starts_with($serviceAccountPath, DIRECTORY_SEPARATOR)
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $serviceAccountPath)
        ? $serviceAccountPath
        : base_path($serviceAccountPath),
    'max_token_age_seconds' => (int) env('PLAY_INTEGRITY_MAX_TOKEN_AGE_SECONDS', 120),
];
