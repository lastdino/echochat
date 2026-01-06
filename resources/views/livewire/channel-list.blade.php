<?php

use EchoChat\Models\Workspace;
use EchoChat\Models\Channel;
use Livewire\Volt\Component;

new class extends Component {
    public Workspace $workspace;
    public ?Channel $activeChannel;

    protected $listeners = ['channelCreated' => '$refresh'];

    public function selectChannel($channelId)
    {
        $this->dispatch('channelSelected', $channelId)->to('chat');
    }
}; ?>

<div class="flex flex-col h-full">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
        <h1 class="font-bold text-xl dark:text-white truncate">{{ $workspace->name }}</h1>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
        <div class="mb-4">
            <div class="flex justify-between items-center px-2 mb-2">
                <h3 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">チャンネル</h3>
                <button x-on:click="$dispatch('open-modal', 'create-channel-modal')" class="text-zinc-500 hover:text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
            </div>
            <div class="space-y-1">
                @foreach($workspace->channels as $channel)
                    <button
                        wire:click="selectChannel({{ $channel->id }})"
                        class="w-full text-left px-2 py-1 rounded transition-colors @if($activeChannel && $activeChannel->id === $channel->id) bg-blue-600 text-white @else text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700 @endif"
                    >
                        # {{ $channel->name }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <flux:modal name="create-channel-modal" class="md:w-[500px]">
        <livewire:create-channel :workspace="$workspace" />
    </flux:modal>
</div>
