<?php

namespace App\Console\Commands;

use App\Services\Observability\AlertDetector;
use Illuminate\Console\Command;

class RunObservabilityChecks extends Command
{
    protected $signature = 'observability:check';

    protected $description = 'Run observability checks and create alerts when thresholds are exceeded';

    public function handle(AlertDetector $detector): int
    {
        $created = $detector->run();

        $this->info("Observability checks complete. {$created} new alert(s) created.");

        return self::SUCCESS;
    }
}
