<?php

use EchoChat\Models\Channel;
use EchoChat\Models\ChannelUser;
use EchoChat\Models\Message;
use EchoChat\Models\Workspace;
use Livewire\Volt\Component;

new class extends Component
{
    public Workspace $workspace;

    public ?Channel $activeChannel;

    public array $notifications = [];

    protected $listeners = [
        'channelCreated' => '$refresh',
        'workspaceMemberAdded' => '$refresh',
    ];

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
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

    public function getListeners()
    {
        return [
            "echo-private:workspace.{$this->workspace->id},.EchoChat\\Events\\MessageSent" => 'handleIncomingMessage',
            'channelCreated' => '$refresh',
            'workspaceMemberAdded' => '$refresh',
        ];
    }

    public function handleIncomingMessage($event)
    {
        $channelId = $event['channel_id'];

        if ($this->activeChannel && $this->activeChannel->id === $channelId) {
            $this->updateLastRead($channelId);

            return;
        }

        $this->notifications[$channelId] = ($this->notifications[$channelId] ?? 0) + 1;
    }

    public function openDirectMessage($userId)
    {
        $currentUserId = auth()->id();

        // 既存のDMチャンネルを探す
        $channel = Channel::where('workspace_id', $this->workspace->id)
            ->where('is_dm', true)
            ->whereHas('members', function ($query) use ($currentUserId) {
                $query->where('user_id', $currentUserId);
            })
            ->whereHas('members', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();

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

    public function selectChannel($channelId)
    {
        $this->updateLastRead($channelId);
        $this->notifications[$channelId] = 0;
        $this->activeChannel = Channel::find($channelId);
        $this->dispatch('channelSelected', $channelId)->to('chat');
    }

    protected function updateLastRead($channelId)
    {
        ChannelUser::updateOrCreate(
            ['channel_id' => $channelId, 'user_id' => auth()->id()],
            ['last_read_at' => now()]
        );
    }
}; ?>

<div class="flex flex-col h-full">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
        <h1 class="font-bold text-xl dark:text-white truncate">{{ $workspace->name }}</h1>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
        <flux:navlist>
            <flux:navlist.group heading="チャンネル" expandable>
                @can('update', $workspace)
                    <x-slot name="actions">
                        <flux:modal.trigger name="create-channel-modal">
                            <flux:button variant="subtle" size="sm" icon="plus" square class="text-zinc-500 hover:text-blue-600" />
                        </flux:modal.trigger>
                    </x-slot>
                @endcan

                @foreach($workspace->channels()->where('is_dm', false)->get() as $channel)
                    @if($channel->canView(auth()->id()))
                        <flux:navlist.item
                            wire:click="selectChannel({{ $channel->id }})"
                            :current="$activeChannel && $activeChannel->id === $channel->id"
                            :badge="($notifications[$channel->id] ?? 0) > 0 ? $notifications[$channel->id] : null"
                            badge:color="blue"
                            :icon="$channel->displayIcon"
                        >
                            {{ $channel->displayName }}
                        </flux:navlist.item>
                    @endif
                @endforeach

                @can('update', $workspace)
                    <flux:modal.trigger name="create-channel-modal">
                        <flux:navlist.item icon="plus" class="text-zinc-500">
                            チャンネルを追加...
                        </flux:navlist.item>
                    </flux:modal.trigger>
                @endcan
            </flux:navlist.group>

            <flux:navlist.group heading="ダイレクトメッセージ" expandable class="mt-4">
                @php
                    $ownerDmChannel = \EchoChat\Models\Channel::where('workspace_id', $workspace->id)
                        ->where('is_dm', true)
                        ->whereHas('members', fn($q) => $q->where('user_id', auth()->id()))
                        ->whereHas('members', fn($q) => $q->where('user_id', $workspace->owner->id))
                        ->first();
                @endphp
                <flux:navlist.item
                    wire:key="member-owner-{{ $workspace->owner->id }}"
                    wire:click="openDirectMessage({{ $workspace->owner->id }})"
                    :current="$activeChannel && $ownerDmChannel && $activeChannel->id === $ownerDmChannel->id"
                    :badge="($ownerDmChannel && ($notifications[$ownerDmChannel->id] ?? 0) > 0) ? $notifications[$ownerDmChannel->id] : null"
                    badge:color="blue"
                >
                    <x-slot name="icon">
                        <flux:avatar size="xs" :name="$workspace->owner->name" />
                    </x-slot>
                    {{ $workspace->owner->name }} {{ $workspace->owner->id === auth()->id() ? '(自分)' : '' }}
                </flux:navlist.item>

                @foreach($workspace->members as $member)
                    @php
                        $memberDmChannel = \EchoChat\Models\Channel::where('workspace_id', $workspace->id)
                            ->where('is_dm', true)
                            ->whereHas('members', fn($q) => $q->where('user_id', auth()->id()))
                            ->whereHas('members', fn($q) => $q->where('user_id', $member->id))
                            ->first();
                    @endphp
                    <flux:navlist.item
                        wire:key="member-{{ $member->id }}"
                        wire:click="openDirectMessage({{ $member->id }})"
                        :current="$activeChannel && $memberDmChannel && $activeChannel->id === $memberDmChannel->id"
                        :badge="($memberDmChannel && ($notifications[$memberDmChannel->id] ?? 0) > 0) ? $notifications[$memberDmChannel->id] : null"
                        badge:color="blue"
                    >
                        <x-slot name="icon">
                            <flux:avatar size="xs" :name="$member->name" />
                        </x-slot>
                        {{ $member->name }} {{ $member->id === auth()->id() ? '(自分)' : '' }}
                    </flux:navlist.item>
                @endforeach

                @can('invite', $workspace)
                    <flux:modal.trigger name="invite-workspace-member-modal">
                        <flux:navlist.item icon="plus" class="text-zinc-500">
                            チームメンバーを追加...
                        </flux:navlist.item>
                    </flux:modal.trigger>
                @endcan
            </flux:navlist.group>
        </flux:navlist>
    </div>

    <flux:modal name="create-channel-modal" class="md:w-[500px]">
        <livewire:create-channel :workspace="$workspace" />
    </flux:modal>

    <flux:modal name="invite-workspace-member-modal" class="md:w-[500px]">
        <livewire:invite-workspace-member :workspace="$workspace" />
    </flux:modal>
</div>
