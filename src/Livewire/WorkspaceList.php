<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class WorkspaceList extends Component
{
    public string $name = '';

    public string $slug = '';

    public function updatedName($value): void
    {
        if (empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function createWorkspace(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:echochat_workspaces,slug',
        ], [], [
            'name' => 'ワークスペース名',
            'slug' => 'スラッグ',
        ]);

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'owner_id' => auth()->id(),
        ]);

        // 初期チャンネルの作成
        $channel = $workspace->channels()->create([
            'name' => '一般',
            'creator_id' => auth()->id(),
        ]);

        $channel->members()->create([
            'user_id' => auth()->id(),
        ]);

        $this->name = '';
        $this->slug = '';

        $this->dispatch('modal-close', name: 'create-workspace');
        $this->dispatch('workspaceCreated');

        $this->redirectRoute('echochat.chat', ['workspace' => $workspace->slug]);
    }

    #[Computed]
    public function workspaces(): Collection
    {
        return Workspace::query()
            ->where('owner_id', auth()->id())
            ->orWhereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->latest()
            ->get();
    }

    public function getListeners(): array
    {
        $userId = auth()->id();
        $listeners = [
            "echo-private:App.Models.User.{$userId},.EchoChat\\Events\\MessageSent" => 'refreshWorkspaces',
            "echo-private:App.Models.User.{$userId},.EchoChat\\Events\\ChannelRead" => 'refreshWorkspaces',
            'channelRead' => 'refreshWorkspaces',
        ];

        if (auth()->check()) {
            foreach (auth()->user()->getAllWorkspaces() as $workspace) {
                $listeners["echo-private:workspace.{$workspace->id},.EchoChat\\Events\\MessageSent"] = 'refreshWorkspaces';
            }
        }

        return $listeners;
    }

    public function refreshWorkspaces(): void
    {
        unset($this->workspaces);
    }

    public function render(): View
    {
        return view('echochat::pages.workspace-list');
    }
}
