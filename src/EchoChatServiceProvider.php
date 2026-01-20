<?php

namespace EchoChat;

use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use EchoChat\Policies\ChannelPolicy;
use EchoChat\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'echochat');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (file_exists(__DIR__.'/../routes/channels.php')) {
            require __DIR__.'/../routes/channels.php';
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/echochat.php' => config_path('echochat.php'),
            ], 'echochat-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/echochat'),
            ], 'echochat-views');

            $this->publishes([
                __DIR__.'/../dist/echochat.css' => public_path('vendor/echochat/echochat.css'),
            ], 'echochat-assets');

            $this->publishes([
                __DIR__.'/../resources/views/flux/icon/smile-plus.blade.php' => resource_path('views/flux/icon/smile-plus.blade.php'),
            ], 'echochat-flux-icons');
        }

        // Register Livewire components
        Livewire::addNamespace(
            namespace: 'echochat',
            viewPath: __DIR__.'/../resources/views/pages'
        );

    }
}
