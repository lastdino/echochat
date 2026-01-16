<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
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

    public function getListeners()
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

    public function refreshUnreadCount()
    {
        if (Auth::check()) {
            // Auth::user() がキャッシュされている可能性を考慮し、
            // getTotalUnreadCount 内部で常に最新のクエリが走るように InteractsWithWorkspaces を修正済み。
            // ここではプロパティを最新の計算結果で更新する。
            $this->unreadNotifications = Auth::user()->getTotalUnreadCount();
        }
    }
}; ?>

<div class="w-full">
    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('echochat.workspaces')" :current="request()->routeIs('echochat.workspaces')" :badge="$unreadNotifications ?: null" badgeColor="red">{{ __('Inbox') }}</flux:sidebar.item>
</div>
