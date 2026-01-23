<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use EchoChat\Models\Workspace;
use EchoChat\Notifications\MentionedInMessage;
use EchoChat\Notifications\ReplyInThread;
use EchoChat\Support\UserSupport;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ActivityFeed extends Component
{
    public Workspace $workspace;

    public bool $showAllWorkspaces = false;

    public bool $onlyUnread = false;

    public function mount(Workspace $workspace): void
    {
        $this->workspace = $workspace;

        $settings = auth()->user()->echochat_settings ?? [];
        $this->showAllWorkspaces = $settings['activity_show_all_workspaces'] ?? false;
        $this->onlyUnread = $settings['activity_only_unread'] ?? false;
    }

    public function updatedShowAllWorkspaces($value): void
    {
        $this->updateSetting('activity_show_all_workspaces', $value);
    }

    public function updatedOnlyUnread($value): void
    {
        $this->updateSetting('activity_only_unread', $value);
    }

    protected function updateSetting(string $key, $value): void
    {
        $user = auth()->user();
        $settings = $user->echochat_settings ?? [];
        $settings[$key] = $value;
        $user->echochat_settings = $settings;
        $user->save();
    }

    public function getListeners(): array
    {
        $userId = auth()->id();

        return [
            "echo-private:App.Models.User.{$userId},.EchoChat\\Events\\MessageSent" => 'refreshActivities',
            "echo-private:App.Models.User.{$userId},.NotificationSent" => 'refreshActivities',
            "echo-private:App.Models.User.{$userId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated" => 'refreshActivities',
            'refreshActivities' => 'refreshActivities',
        ];
    }

    public function refreshActivities(): void
    {
        unset($this->activities);
        $this->dispatch('activity-updated');
    }

    #[Computed]
    public function activities(): Collection
    {
        $userId = auth()->id();

        // 1. メンション（通知から取得するのが確実）
        $notificationsQuery = auth()->user()->notifications()
            ->whereIn('type', [MentionedInMessage::class, ReplyInThread::class])
            ->latest();

        $mentions = $notificationsQuery->get()
            ->filter(function ($notification) {
                if ($this->showAllWorkspaces) {
                    return true;
                }

                return (int) ($notification->data['workspace_id'] ?? 0) === (int) $this->workspace->id;
            })
            ->take(40)
            ->map(function ($notification) {
                $message = Message::with(['user', 'channel.workspace'])->find($notification->data['message_id']);
                if (! $message) {
                    return null;
                }

                $type = $notification->type === MentionedInMessage::class ? 'mention' : 'reply';

                return [
                    'type' => $type,
                    'id' => $message->id,
                    'user_name' => UserSupport::getName($message->user),
                    'user_avatar' => $message->user->getUserAvatar(),
                    'content' => $message->content,
                    'channel_name' => $message->channel->displayName,
                    'channel_id' => $message->channel_id,
                    'workspace_id' => $message->channel->workspace_id,
                    'workspace_name' => $message->channel->workspace->name,
                    'workspace_slug' => $message->channel->workspace->slug,
                    'created_at' => $message->created_at,
                    'notification_id' => $notification->id,
                    'is_read' => ! is_null($notification->read_at),
                ];
            })
            ->filter();

        // 2. 自分のメッセージへの返信
        $myMessageIdsQuery = Message::where('user_id', $userId);
        if (! $this->showAllWorkspaces) {
            $myMessageIdsQuery->whereHas('channel', fn ($q) => $q->where('workspace_id', $this->workspace->id));
        }
        $myMessageIds = $myMessageIdsQuery->pluck('id');

        $replies = collect();
        if ($myMessageIds->isNotEmpty()) {
            $replies = Message::with(['user', 'channel.workspace', 'parent'])
                ->whereIn('parent_id', $myMessageIds)
                ->where('user_id', '!=', $userId) // 自分の返信は除外
                ->latest()
                ->take(20)
                ->get()
                ->map(function ($message) {
                    return [
                        'type' => 'reply',
                        'id' => $message->id,
                        'user_name' => UserSupport::getName($message->user),
                        'user_avatar' => $message->user->getUserAvatar(),
                        'content' => $message->content,
                        'parent_content' => $message->parent->content,
                        'channel_name' => $message->channel->displayName,
                        'channel_id' => $message->channel_id,
                        'workspace_id' => $message->channel->workspace_id,
                        'workspace_name' => $message->channel->workspace->name,
                        'workspace_slug' => $message->channel->workspace->slug,
                        'created_at' => $message->created_at,
                        'is_read' => $message->created_at <= (auth()->user()->channelUsers()->where('channel_id', $message->channel_id)->first()?->last_read_at),
                    ];
                });
        }

        $dmChannelsQuery = Channel::where('is_dm', true)
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId));

        if (! $this->showAllWorkspaces) {
            $dmChannelsQuery->where('workspace_id', $this->workspace->id);
        }

        $dmChannels = $dmChannelsQuery->pluck('id');

        $dms = collect();
        if ($dmChannels->isNotEmpty()) {
            $dms = Message::with(['user', 'channel.workspace'])
                ->whereIn('channel_id', $dmChannels)
                ->where('user_id', '!=', $userId)
                ->latest()
                ->take(20)
                ->get()
                ->map(function ($message) {
                    return [
                        'type' => 'dm',
                        'id' => $message->id,
                        'user_name' => UserSupport::getName($message->user),
                        'user_avatar' => $message->user->getUserAvatar(),
                        'content' => $message->content,
                        'channel_name' => 'ダイレクトメッセージ',
                        'channel_id' => $message->channel_id,
                        'workspace_id' => $message->channel->workspace_id,
                        'workspace_name' => $message->channel->workspace->name,
                        'workspace_slug' => $message->channel->workspace->slug,
                        'created_at' => $message->created_at,
                        'is_read' => $message->created_at <= (auth()->user()->channelUsers()->where('channel_id', $message->channel_id)->first()?->last_read_at),
                    ];
                });
        }

        $allActivities = collect($mentions)
            ->concat($replies)
            ->concat($dms)
            ->sortByDesc('created_at')
            ->values();

        if ($this->onlyUnread) {
            return $allActivities->filter(fn ($activity) => ! ($activity['is_read'] ?? false))->take(60);
        }

        return $allActivities->take(60);
    }

    public function selectActivity($channelId, $messageId = null, $notificationId = null, $workspaceSlug = null)
    {
        if ($notificationId) {
            $this->markAsRead($notificationId);
        }

        if ($workspaceSlug && $workspaceSlug !== $this->workspace->slug) {
            // 別ワークスペースの場合はリダイレクト
            return redirect()->route('echochat.chat', [
                'workspace' => $workspaceSlug,
                'channel' => $channelId,
                'message' => $messageId,
            ]);
        }

        $this->dispatch('setActivityMessage', messageId: $messageId, channelId: $channelId, clickId: now()->getTimestampMs());

        // スレッド（返信）の場合はスレッドサイドバーも開く
        if ($messageId) {
            $message = \EchoChat\Models\Message::find($messageId);
            if ($message && $message->parent_id) {
                $this->dispatch('openThread', messageId: $message->parent_id)->to(Chat::class);
            }
        }

        $this->js("Flux.modal('activity-feed').close()");
    }

    public function markAsRead($notificationId): void
    {
        auth()->user()->notifications()->where('id', $notificationId)->update(['read_at' => now()]);
    }

    public function render(): View
    {
        return view('echochat::pages.activity-feed');
    }
}
