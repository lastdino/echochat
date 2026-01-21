<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class CreateChannel extends Component
{
    public Workspace $workspace;

    public string $name = '';

    public bool $is_private = false;

    public function createChannel(): void
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
        $this->dispatch('channelSelected', $channel->id)->to(Chat::class);
    }

    public function render(): View
    {
        return view('echochat::pages.create-channel');
    }
}
