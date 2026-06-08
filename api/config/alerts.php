<?php

return [
    'admin_email' => env('ALERTS_ADMIN_EMAIL'),
    'dedupe_hours' => (int) env('ALERTS_DEDUPE_HOURS', 1),
];
