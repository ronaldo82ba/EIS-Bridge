<?php

namespace App\Services\Observability;

use App\Events\QueueUpdated;
use Illuminate\Support\Facades\Cache;

class QueueBroadcaster
{
    private const CACHE_HASH_KEY = 'broadcast:queues:hash';

    private const CACHE_LAST_AT_KEY = 'broadcast:queues:last_at';

    private const MIN_INTERVAL_SECONDS = 30;

    public function broadcastIfChanged(QueueMonitor $monitor): bool
    {
        $queues = $monitor->queueDepthMap();
        $hash = md5(json_encode($queues) ?: '');
        $lastHash = Cache::get(self::CACHE_HASH_KEY);
        $lastAt = Cache::get(self::CACHE_LAST_AT_KEY);

        $intervalElapsed = $lastAt === null
            || now()->diffInSeconds($lastAt) >= self::MIN_INTERVAL_SECONDS;

        if ($hash === $lastHash && ! $intervalElapsed) {
            return false;
        }

        event(new QueueUpdated($queues));

        Cache::put(self::CACHE_HASH_KEY, $hash, 3600);
        Cache::put(self::CACHE_LAST_AT_KEY, now(), 3600);

        return true;
    }
}
