<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class WorkspaceSettings extends Component
{
    public Workspace $workspace;

    public string $name;

    public bool $allow_member_channel_creation;

    public bool $allow_member_channel_deletion;

    public ?string $ai_prompt = null;

    public ?string $extract_ai_prompt = null;

    public function mount(Workspace $workspace): void
    {
        Gate::authorize('update', $workspace);

        $this->workspace = $workspace;
        $this->name = $workspace->name;
        $this->allow_member_channel_creation = $workspace->allow_member_channel_creation;
        $this->allow_member_channel_deletion = $workspace->allow_member_channel_deletion;
        $this->ai_prompt = $workspace->ai_prompt;
        $this->extract_ai_prompt = $workspace->extract_ai_prompt;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->workspace);

        $this->validate([
            'name' => 'required|string|max:255',
            'ai_prompt' => 'nullable|string',
            'extract_ai_prompt' => 'nullable|string',
        ]);

        $this->workspace->update([
            'name' => $this->name,
            'allow_member_channel_creation' => $this->allow_member_channel_creation,
            'allow_member_channel_deletion' => $this->allow_member_channel_deletion,
            'ai_prompt' => $this->ai_prompt,
            'extract_ai_prompt' => $this->extract_ai_prompt,
        ]);

        if (class_exists(\Flux\Flux::class)) {
            \Flux\Flux::toast('設定を保存しました。');
        } elseif (class_exists(\Livewire\Flux\Flux::class)) {
            \Livewire\Flux\Flux::toast('設定を保存しました。');
        }
    }

    public function render(): View
    {
        return view('echochat::pages.workspace-settings');
    }
}
