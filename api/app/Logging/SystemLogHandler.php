<?php

namespace App\Logging;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Schema;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class SystemLogHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Warning)
    {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        try {
            if (! app()->bound('db') || ! Schema::hasTable('system_logs')) {
                return;
            }

            SystemLog::create([
                'level' => strtolower($record->level->getName()),
                'message' => $record->message,
                'context' => $record->context ?: null,
                'channel' => $record->channel,
                'logged_at' => $record->datetime,
            ]);
        } catch (Throwable) {
            // Never break bootstrap or request handling when DB logging fails.
        }
    }
}
