<?php

namespace EchoChat\Livewire;

use EchoChat\Events\ChannelRead;
use EchoChat\Models\Channel;
use EchoChat\Models\ChannelMember;
use EchoChat\Models\ChannelUser;
use EchoChat\Models\Message;
use EchoChat\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class ChannelList extends Component
{
    public Workspace $workspace;

    public ?Channel $activeChannel = null;

    public array $notifications = [];

    public function mount(): void
    {
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        $userId = auth()->id();

        foreach ($this->workspace->channels as $channel) {
            $lastRead = ChannelUser::where('channel_id', $channel->id)
                ->where('user_id', $userId)
                ->first();

            $query = Message::where('channel_id', $channel->id);

            if ($lastRead && $lastRead->last_read_at) {
                $query->where('created_at', '>', $lastRead->last_read_at);
            }

            $count = $query->count();

            if ($count > 0) {
                $this->notifications[$channel->id] = $count;
            }
        }
    }

    public function getListeners(): array
    {
        return [
            "echo-private:workspace.{$this->workspace->id},.EchoChat\\Events\\MessageSent" => 'handleIncomingMessage',
            'channelCreated' => '$refresh',
            'channelUpdated' => '$refresh',
            'workspaceMemberAdded' => '$refresh',
            'channelSelected' => 'handleChannelSelected',
        ];
    }

    public function handleChannelSelected($channelId): void
    {
        $this->activeChannel = Channel::find($channelId);
    }

    public function handleIncomingMessage($event): void
    {
        if (! is_array($event) || ! isset($event['channel_id'])) {
            return;
        }

        $channelId = $event['channel_id'];

        if ($this->activeChannel && $this->activeChannel->id === $channelId) {
            $this->updateLastRead($channelId);

            return;
        }

        $this->notifications[$channelId] = ($this->notifications[$channelId] ?? 0) + 1;
    }

    public function openDirectMessage($userId): void
    {
        $currentUserId = auth()->id();

        // 既存のDMチャンネルを探す
        $query = Channel::where('workspace_id', $this->workspace->id)
            ->where('is_dm', true)
            ->whereHas('members', function ($query) use ($currentUserId) {
                $query->where('user_id', $currentUserId);
            });

        if ($userId === $currentUserId) {
            // 自分自身とのDMの場合は、メンバー数が1であることを確認
            $query->has('members', 1);
        } else {
            // 他者とのDMの場合は、相手も含まれていることを確認
            $query->whereHas('members', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            });
        }

        $channel = $query->first();

        if (! $channel) {
            // 新規作成
            $channel = Channel::create([
                'workspace_id' => $this->workspace->id,
                'is_dm' => true,
                'is_private' => true,
                'creator_id' => $currentUserId,
            ]);

            $channel->members()->create(['user_id' => $currentUserId]);

            if ($userId !== $currentUserId) {
                $channel->members()->create(['user_id' => $userId]);
            }
        }

        $this->selectChannel($channel->id);
    }

    public function selectChannel($channelId): void
    {
        $this->updateLastRead($channelId);
        $this->notifications[$channelId] = 0;
        $this->activeChannel = Channel::find($channelId);
        $this->dispatch('channelSelected', $channelId)->to(Chat::class);
    }

    protected function updateLastRead($channelId): void
    {
        ChannelUser::updateOrCreate(
            ['channel_id' => $channelId, 'user_id' => auth()->id()],
            ['last_read_at' => now()]
        );

        $channel = Channel::find($channelId);
        if ($channel) {
            broadcast(new ChannelRead($channel, auth()->id()))->toOthers();
            $this->dispatch('channelRead', channelId: $channelId);
        }
    }

    public function removeMember($userId): void
    {
        Gate::authorize('removeMember', $this->workspace);

        // チャンネルからも削除
        $channelIds = $this->workspace->channels()->pluck('id');
        ChannelMember::whereIn('channel_id', $channelIds)
            ->where('user_id', $userId)
            ->delete();

        $this->workspace->members()->detach($userId);

        $this->dispatch('workspaceMemberAdded'); // メンバーリスト更新のために同じイベントを使う
    }

    public function transferOwnership($userId): void
    {
        Gate::authorize('transferOwnership', $this->workspace);

        $oldOwnerId = $this->workspace->owner_id;

        // オーナーを変更
        $this->workspace->update([
            'owner_id' => $userId,
        ]);

        // 元のオーナーをメンバーとして追加
        if (! $this->workspace->members()->where('user_id', $oldOwnerId)->exists()) {
            $this->workspace->members()->attach($oldOwnerId);
        }

        $this->workspace->members()->detach($userId);

        $this->dispatch('workspaceMemberAdded');
    }

    public function deleteChannel($channelId): void
    {
        $channel = Channel::findOrFail($channelId);

        Gate::authorize('delete', $channel);

        $channel->delete();

        if ($this->activeChannel && $this->activeChannel->id === (int) $channelId) {
            $this->activeChannel = null;
            $this->dispatch('channelSelected', null)->to(Chat::class);
        }

        $this->dispatch('channelCreated'); // リスト再描画のために使用
    }

    public function updateOrder(array $items): void
    {
        $userId = auth()->id();

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['value']) || ! isset($item['order'])) {
                continue;
            }

            ChannelUser::updateOrCreate(
                ['channel_id' => $item['value'], 'user_id' => $userId],
                ['sort_order' => $item['order']]
            );
        }

        $this->dispatch('channelUpdated');
    }

    public function render(): View
    {
        return view('echochat::pages.channel-list');
    }
}
