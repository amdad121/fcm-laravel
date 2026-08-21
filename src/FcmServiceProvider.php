<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm;

use AmdadulHaq\Fcm\Notifications\FcmChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

class FcmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fcm.php', 'fcm');

        $this->app->singleton(FcmService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fcm.php' => config_path('fcm.php'),
            ], 'fcm-config');
        }

        $this->app->make(ChannelManager::class)->extend('fcm', fn ($app) => $app->make(FcmChannel::class));
    }
}
