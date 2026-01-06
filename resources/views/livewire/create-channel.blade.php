<?php

use EchoChat\Models\Workspace;
use EchoChat\Models\Channel;
use Livewire\Volt\Component;
use Illuminate\Support\Str;

new class extends Component {
    public Workspace $workspace;
    public string $name = '';
    public bool $is_private = false;

    public function createChannel()
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $channel = $this->workspace->channels()->create([
            'name' => Str::slug($this->name),
            'is_private' => $this->is_private,
            'creator_id' => auth()->id(),
        ]);

        $this->name = '';
        $this->is_private = false;

        $this->dispatch('close-modal', 'create-channel-modal');
        $this->dispatch('channelCreated');
        $this->dispatch('channelSelected', $channel->id)->to('chat');
    }
}; ?>

<div class="p-4 bg-white dark:bg-zinc-800 rounded-lg shadow">
    <h3 class="text-lg font-bold mb-4 dark:text-white">新しいチャンネルを作成</h3>
    <form wire:submit.prevent="createChannel">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">チャンネル名</label>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex items-center">
                <input type="checkbox" wire:model="is_private" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500">
                <label class="ml-2 block text-sm text-zinc-900 dark:text-zinc-300">プライベートにする</label>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition-colors">作成</button>
        </div>
    </form>
</div>
