<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::routeSlackNotificationsTo(
            config('services.slack.notifications.channel'),
            config('services.slack.notifications.bot_user_oauth_token')
        );
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (! $user) {
                return false;
            }

            return in_array($user->role, ['super_admin', 'support'], true);
        });
    }
}
