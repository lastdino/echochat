<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use EchoChat\Models\Workspace;
use EchoChat\Support\UserSupport;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ThreadList extends Component
{
    #[Locked]
    public Workspace $workspace;

    #[Reactive]
    public ?Channel $channel = null;

    public function getListeners(): array
    {
        $userId = auth()->id();

        return [
            "echo-private:App.Models.User.{$userId},.EchoChat\\Events\\MessageSent" => 'refreshThreads',
            "echo-private:App.Models.User.{$userId},.NotificationSent" => 'refreshThreads',
            "echo-private:App.Models.User.{$userId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated" => 'refreshThreads',
            'refreshThreads' => 'refreshThreads',
        ];
    }

    public function refreshThreads(): void
    {
        unset($this->threads);
    }

    #[Computed]
    public function threads(): Collection
    {
        // 自分が参加しているスレッドを取得
        // 1. 自分が親メッセージを投稿したスレッド
        // 2. 自分が返信を投稿したスレッド
        $myMessagesQuery = Message::where('user_id', auth()->id());

        if ($this->channel) {
            $myMessagesQuery->where('channel_id', $this->channel->id);
        }

        $myMessageIds = $myMessagesQuery->pluck('id', 'parent_id');

        $parentIds = $myMessageIds->keys()->filter()->unique(); // 返信したスレッドの親ID
        $ownThreadsQuery = Message::where('user_id', auth()->id())
            ->whereNull('parent_id');

        if ($this->channel) {
            $ownThreadsQuery->where('channel_id', $this->channel->id);
        }

        $ownThreadIds = $ownThreadsQuery->pluck('id'); // 自分が親のスレッドID

        $allThreadParentIds = $parentIds->concat($ownThreadIds)->unique();

        if ($allThreadParentIds->isEmpty()) {
            return collect();
        }

        $query = Message::whereIn('id', $allThreadParentIds)
            ->with(['user', 'replies.user', 'channel'])
            ->withCount('replies');

        if ($this->channel) {
            $query->where('channel_id', $this->channel->id);
        }

        return $query->get()
            ->filter(fn ($message) => $message->replies_count > 0)
            ->map(function ($message) {
                $latestReply = $message->replies()->latest()->first();

                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'user_name' => UserSupport::getName($message->user),
                    'user_avatar' => $message->user->getUserAvatar(),
                    'channel_id' => $message->channel_id,
                    'channel_name' => $message->channel->name,
                    'reply_count' => $message->replies_count,
                    'latest_reply_at' => $latestReply?->created_at ?? $message->created_at,
                    'latest_reply_user_name' => $latestReply ? UserSupport::getName($latestReply->user) : null,
                    'created_at' => $message->created_at,
                ];
            })
            ->sortByDesc('latest_reply_at')
            ->values();
    }

    public function selectThread($channelId, $messageId): void
    {
        $this->dispatch('setActivityMessage', messageId: $messageId, channelId: $channelId, clickId: now()->getTimestampMs());
        $this->dispatch('openThread', messageId: $messageId)->to(Chat::class);
        $this->js("Flux.modal('thread-list').close()");
    }

    public function render(): View
    {
        return view('echochat::pages.thread-list');
    }
}
