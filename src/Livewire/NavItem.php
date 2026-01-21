<?php

namespace EchoChat\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class NavItem extends Component
{
    public $user;

    public int $unreadNotifications = 0;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->user = Auth::user();
            $this->unreadNotifications = Auth::user()->getTotalUnreadCount();
        }
    }

    public function getListeners(): array
    {
        $userId = auth()->id();
        $listeners = [
            "echo-private:App.Models.User.{$userId},.EchoChat\\Events\\MessageSent" => 'refreshUnreadCount',
            "echo-private:App.Models.User.{$userId},.EchoChat\\Events\\ChannelRead" => 'refreshUnreadCount',
            'channelRead' => 'refreshUnreadCount',
        ];

        if (Auth::check()) {
            foreach (Auth::user()->getAllWorkspaces() as $workspace) {
                $listeners["echo-private:workspace.{$workspace->id},.EchoChat\\Events\\MessageSent"] = 'refreshUnreadCount';
            }
        }

        return $listeners;
    }

    public function refreshUnreadCount(): void
    {
        if (Auth::check()) {
            $this->unreadNotifications = Auth::user()->getTotalUnreadCount();
        }
    }

    public function render(): View
    {
        return view('echochat::pages.nav-item');
    }
}
