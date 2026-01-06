<?php

use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use Livewire\Volt\Component;

new class extends Component {
    public Workspace $workspace;
    public ?Channel $activeChannel = null;

    public function mount(Workspace $workspace, ?Channel $channel = null)
    {
        $this->workspace = $workspace;
        $this->activeChannel = $channel ?? $workspace->channels()->first();
    }

    protected $listeners = ['channelSelected' => 'selectChannel'];

    public function selectChannel($channelId)
    {
        $this->activeChannel = Channel::find($channelId);
    }
}; ?>

<div class="flex h-screen bg-white dark:bg-zinc-900 overflow-hidden">
    <!-- Sidebar -->
    <div class="w-64 flex-shrink-0 bg-zinc-100 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700">
        <livewire:channel-list :workspace="$workspace" :activeChannel="$activeChannel" />
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-w-0">
        @if($activeChannel)
            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                    <h2 class="text-lg font-bold dark:text-white"># {{ $activeChannel->name }}</h2>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <livewire:message-feed :channel="$activeChannel" wire:key="feed-{{ $activeChannel->id }}" />
                </div>

                <div class="p-4">
                    <livewire:message-input :channel="$activeChannel" wire:key="input-{{ $activeChannel->id }}" />
                </div>
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <p class="text-zinc-500">チャンネルを選択してください</p>
            </div>
        @endif
    </div>
</div>
