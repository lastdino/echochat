<?php

use EchoChat\Models\Workspace;
use EchoChat\Models\Channel;
use Livewire\Volt\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public Workspace $workspace;
    public string $name = '';
    public bool $is_private = false;

    public function createChannel()
    {
        Gate::authorize('createChannel', $this->workspace);

        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $channel = $this->workspace->channels()->create([
            'name' => $this->name,
            'is_private' => $this->is_private,
            'creator_id' => auth()->id(),
        ]);

        $channel->members()->create([
            'user_id' => auth()->id(),
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
            <flux:field>
                <flux:label>チャンネル名</flux:label>
                <flux:input wire:model="name" placeholder="例: プロジェクトA" />
                <flux:error name="name" />
            </flux:field>

            <flux:checkbox wire:model="is_private" label="プライベートにする" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">作成</flux:button>
            </div>
        </div>
    </form>
</div>
