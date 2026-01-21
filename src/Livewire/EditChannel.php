<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Channel;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class EditChannel extends Component
{
    public Channel $channel;

    public string $name = '';

    public ?string $description = '';

    public function mount(Channel $channel): void
    {
        $this->channel = $channel;
        $this->name = $channel->name ?? '';
        $this->description = $channel->description ?? '';
    }

    public function updateChannel(): void
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
        $this->dispatch('channelSelected', $this->channel->id)->to(Chat::class);
    }

    public function render(): View
    {
        return view('echochat::pages.edit-channel');
    }
}
