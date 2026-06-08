<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BIR EIS Endpoint
    |--------------------------------------------------------------------------
    |
    | Sandbox (default): https://sandbox.eis.bir.gov.ph/api/v1/invoices
    | Production: set EIS_ENDPOINT to the URL provided by BIR upon registration.
    | The production URL is not publicly documented — use the value from your
    | BIR EIS onboarding package.
    |
    */
    'endpoint' => env('EIS_ENDPOINT', 'https://sandbox.eis.bir.gov.ph/api/v1/invoices'),

    'timeout' => (int) env('EIS_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | When true (default for local dev), transmission is simulated and signing
    | falls back to a sandbox signature when no merchant certificate exists.
    | Set EIS_SANDBOX_MODE=false in production.
    |
    */
    'sandbox_mode' => (bool) env('EIS_SANDBOX_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Mutual TLS (optional)
    |--------------------------------------------------------------------------
    |
    | Enable when BIR requires client certificate authentication.
    | Paths are absolute filesystem paths to PEM-encoded cert and key files.
    |
    */
    'mtls' => [
        'enabled' => (bool) env('EIS_MTLS_ENABLED', false),
        'client_cert_path' => env('EIS_CLIENT_CERT_PATH'),
        'client_key_path' => env('EIS_CLIENT_KEY_PATH'),
    ],

    'retry_max_attempts' => (int) env('EIS_RETRY_MAX_ATTEMPTS', 5),

    'retry_backoff' => array_map(
        'intval',
        explode(',', env('EIS_RETRY_BACKOFF', '60,300,900,3600,7200'))
    ),

    'retry' => [
        'max_attempts' => (int) env('EIS_RETRY_MAX_ATTEMPTS', 5),
        'backoff_seconds' => array_map(
            'intval',
            explode(',', env('EIS_RETRY_BACKOFF', '60,300,900,3600,7200'))
        ),
    ],
];
