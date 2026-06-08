<?php

return [
    'cert_expiry_warning_days' => (int) env('OBS_CERT_EXPIRY_WARNING_DAYS', 30),
    'cert_expiry_critical_days' => (int) env('OBS_CERT_EXPIRY_CRITICAL_DAYS', 7),
    'error_rate_threshold' => (float) env('OBS_ERROR_RATE_THRESHOLD', 10),
    'queue_backlog_threshold' => (int) env('OBS_QUEUE_BACKLOG_THRESHOLD', 100),
    'alert_dedupe_hours' => 24,
    'observability_check_minutes' => 10,
    'monitored_queues' => ['mapping', 'signing', 'transmission', 'retry', 'webhooks', 'default'],
    'horizon_path' => env('HORIZON_PATH', 'horizon'),
];
