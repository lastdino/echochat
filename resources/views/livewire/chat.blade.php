<?php

use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use Livewire\Volt\Component;

new class extends Component
{
    public Workspace $workspace;

    public ?Channel $activeChannel = null;

    public function mount(Workspace $workspace, ?Channel $channel = null)
    {
        $this->workspace = $workspace;
        $this->activeChannel = $channel ?? $workspace->channels()->first();

        if ($this->activeChannel) {
            $this->activeChannel->load('members.user');
        }
    }

    protected $listeners = [
        'channelSelected' => 'selectChannel',
        'memberAdded' => '$refresh',
    ];

    public function selectChannel($channelId)
    {
        $this->activeChannel = Channel::with('members.user')->find($channelId);
    }

    public function joinChannel()
    {
        if ($this->activeChannel && $this->activeChannel->canJoin(auth()->id())) {
            $this->activeChannel->members()->create([
                'user_id' => auth()->id(),
            ]);

            $this->activeChannel->messages()->create([
                'user_id' => auth()->id(),
                'content' => "# {$this->activeChannel->name} に参加しました",
            ]);

            $this->activeChannel = Channel::find($this->activeChannel->id);
            $this->dispatch('channelCreated'); // サイドバーを更新するため
            $this->dispatch('messageSent'); // メッセージフィードを更新するため
        }
    }
}; ?>

<div class="flex h-[calc(100vh-theme(spacing.16))] lg:h-screen bg-white dark:bg-zinc-900 overflow-hidden -m-6 lg:-m-8">
    <!-- Sidebar -->
    <div class="w-64 flex-shrink-0 bg-zinc-100 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700">
        <livewire:channel-list :workspace="$workspace" :activeChannel="$activeChannel" />
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-w-0">
        @if($activeChannel)
            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                    <h2 class="text-lg font-bold dark:text-white">
                        <flux:icon :icon="$activeChannel->displayIcon" class="inline-block mr-1 w-4 h-4" />
                        {{ $activeChannel->displayName }}
                    </h2>

                    @if(! $activeChannel->is_dm)
                        <div class="flex items-center gap-2" title="{{ $activeChannel->members->map(fn($m) => $m->user->name)->implode(', ') }}">
                            <flux:avatar.group>
                                @foreach($activeChannel->members->take(5) as $member)
                                    <flux:avatar size="xs" :name="$member->user->name" />
                                @endforeach

                                @if($activeChannel->members->count() > 5)
                                    <flux:avatar size="xs">+{{ $activeChannel->members->count() - 5 }}</flux:avatar>
                                @endif
                            </flux:avatar.group>

                            @if($activeChannel->is_private)
                                <flux:modal.trigger name="invite-member-modal">
                                    <flux:button variant="subtle" size="sm" icon="user-plus" square />
                                </flux:modal.trigger>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex-1 overflow-y-auto flex flex-col-reverse">
                    <div>
                        <livewire:message-feed :channel="$activeChannel" wire:key="feed-{{ $activeChannel->id }}" />
                    </div>
                </div>

                <div class="p-4">
                    @if($activeChannel->isMember(auth()->id()))
                        <livewire:message-input :channel="$activeChannel" wire:key="input-{{ $activeChannel->id }}" />
                    @elseif($activeChannel->canJoin(auth()->id()))
                        <div class="bg-zinc-50 dark:bg-zinc-800 p-8 rounded-lg border border-zinc-200 dark:border-zinc-700 text-center">
                            <h3 class="text-zinc-900 dark:text-white font-bold mb-2"># {{ $activeChannel->name }} に参加しますか？</h3>
                            <p class="text-zinc-500 dark:text-zinc-400 mb-6">参加すると、このチャンネルでメッセージを送信できるようになります。</p>
                            <flux:button wire:click="joinChannel" variant="primary">チャンネルに参加する</flux:button>
                        </div>
                    @else
                        <div class="bg-zinc-50 dark:bg-zinc-800 p-8 rounded-lg border border-zinc-200 dark:border-zinc-700 text-center text-zinc-500">
                            このプライベートチャンネルを閲覧する権限がありません。
                        </div>
                    @endif
                </div>

                @if($activeChannel->is_private)
                    <flux:modal name="invite-member-modal" class="md:w-[500px]">
                        <livewire:invite-member :channel="$activeChannel" />
                    </flux:modal>
                @endif
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <p class="text-zinc-500">チャンネルを選択してください</p>
            </div>
        @endif
    </div>
</div>
