<?php

namespace EchoChat;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class EchoChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/echochat.php',
            'echochat'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'echochat');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/echochat.php' => config_path('echochat.php'),
            ], 'echochat-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/echochat'),
            ], 'echochat-views');
        }

        // Register Volt components
        Volt::mount([
            __DIR__.'/../resources/views/livewire',
        ]);
    }
}
