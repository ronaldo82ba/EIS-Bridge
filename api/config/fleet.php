<?php

return [
    'commander_token' => env('FLEET_COMMANDER_TOKEN', ''),
    'operator_key' => env('FLEET_OPERATOR_KEY', ''),
    'agent_token_header' => 'X-Agent-Token',
    'commander_token_header' => 'X-Commander-Token',
    'operator_key_header' => 'X-Operator-Key',
    'task_timeout_sec' => (int) env('FLEET_TASK_TIMEOUT_SEC', 60),
    'poll_interval_sec' => (int) env('FLEET_POLL_INTERVAL_SEC', 15),
    'allowed_commands' => [
        'execute-shell',
        'reboot',
        'install-apk',
        'clear-cache',
        'launch-app',
        'stop-app',
        'pull-logs',
        'device-status',
    ],
];
