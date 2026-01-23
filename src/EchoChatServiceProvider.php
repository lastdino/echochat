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
        $this->registerLivewireComponents();

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

    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('echochat-chat', \EchoChat\Livewire\Chat::class);
        Livewire::component('echochat-message-feed', \EchoChat\Livewire\MessageFeed::class);
        Livewire::component('echochat-activity-feed', \EchoChat\Livewire\ActivityFeed::class);
        Livewire::component('echochat-thread-list', \EchoChat\Livewire\ThreadList::class);
        Livewire::component('echochat-channel-list', \EchoChat\Livewire\ChannelList::class);
        Livewire::component('echochat-message-input', \EchoChat\Livewire\MessageInput::class);
        Livewire::component('echochat-message-input-pro', \EchoChat\Livewire\MessageInputPro::class);
        Livewire::component('echochat-create-channel', \EchoChat\Livewire\CreateChannel::class);
        Livewire::component('echochat-edit-channel', \EchoChat\Livewire\EditChannel::class);
        Livewire::component('echochat-invite-member', \EchoChat\Livewire\InviteMember::class);
        Livewire::component('echochat-invite-workspace-member', \EchoChat\Livewire\InviteWorkspaceMember::class);
        Livewire::component('echochat-workspace-list', \EchoChat\Livewire\WorkspaceList::class);
        Livewire::component('echochat-workspace-settings', \EchoChat\Livewire\WorkspaceSettings::class);
        Livewire::component('echochat-nav-item', \EchoChat\Livewire\NavItem::class);

        // Blade components
        \Illuminate\Support\Facades\Blade::component('echochat::components.nav-item-with-badge', 'echochat-nav-item-with-badge');
        \Illuminate\Support\Facades\Blade::component('echochat::components.message-item', 'echochat-message-item');
    }
}
