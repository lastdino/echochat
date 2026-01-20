<?php

use EchoChat\Models\Channel;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Channel $channel;

    public string $name = '';

    public ?string $description = '';

    public function mount(Channel $channel)
    {
        $this->channel = $channel;
        $this->name = $channel->name ?? '';
        $this->description = $channel->description ?? '';
    }

    public function updateChannel()
    {
        Gate::authorize('update', $this->channel);

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->channel->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->dispatch('close-modal', 'edit-channel-modal');
        $this->dispatch('channelUpdated');
        $this->dispatch('channelSelected', $this->channel->id)->to('echochat::chat');
    }
}; ?>

<div class="p-4 bg-white dark:bg-zinc-800 rounded-lg shadow">
    <h3 class="text-lg font-bold mb-4 dark:text-white">チャンネルを編集</h3>
    <form wire:submit.prevent="updateChannel">
        <div class="space-y-4">
            <flux:field>
                <flux:label>チャンネル名</flux:label>
                <flux:input wire:model="name" placeholder="例: プロジェクトA" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>説明（任意）</flux:label>
                <flux:textarea wire:model="description" placeholder="チャンネルの目的などを入力してください" />
                <flux:error name="description" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">保存</flux:button>
            </div>
        </div>
    </form>
</div>
