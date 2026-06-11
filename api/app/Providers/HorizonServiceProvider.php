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

        $slackChannel = config('services.slack.notifications.channel');
        $slackToken = config('services.slack.notifications.bot_user_oauth_token');

        if ($slackChannel && $slackToken) {
            Horizon::routeSlackNotificationsTo($slackChannel, $slackToken);
        }
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
