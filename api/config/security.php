<?php

return [
    'api_key_grace_hours' => (int) env('API_KEY_GRACE_HOURS', 24),
    'vendor_api_rate_limit' => (int) env('VENDOR_API_RATE_LIMIT', 120),
    'vendor_transaction_rate_limit' => (int) env('VENDOR_TRANSACTION_RATE_LIMIT', 60),
    'admin_api_rate_limit' => (int) env('ADMIN_API_RATE_LIMIT', 60),
    'login_rate_limit' => (int) env('LOGIN_RATE_LIMIT', 5),
    'certificate_disk' => env('CERTIFICATE_DISK', 'local'),
];
