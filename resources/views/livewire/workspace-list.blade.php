<?php
declare(strict_types=1);

use EchoChat\Models\Workspace;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $slug = '';

    public function updatedName($value)
    {
        if (empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function createWorkspace()
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
        $workspace->channels()->create([
            'name' => '一般',
            'creator_id' => auth()->id(),
        ]);

        $this->name = '';
        $this->slug = '';

        $this->dispatch('modal-close', name: 'create-workspace');
        $this->dispatch('workspaceCreated');

        return redirect()->route('echochat.chat', ['workspace' => $workspace->slug]);
    }

    #[Computed]
    public function workspaces()
    {
        return Workspace::query()
            ->where('owner_id', auth()->id())
            ->orWhereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->latest()
            ->get();
    }

    public function getListeners()
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

    public function refreshWorkspaces()
    {
        unset($this->workspaces);
    }
}; ?>

<div class="p-6">
    <div class="flex items-center justify-between mb-8">
        <flux:heading size="xl">ワークスペース一覧</flux:heading>

        <flux:modal.trigger name="create-workspace">
            <flux:button variant="primary" icon="plus">ワークスペース作成</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="create-workspace" class="md:w-[24rem]">
        <form wire:submit="createWorkspace" class="space-y-6">
            <div>
                <flux:heading size="lg">ワークスペース作成</flux:heading>
                <flux:text>新しいワークスペースを作成します。</flux:text>
            </div>

            <flux:field>
                <flux:label>ワークスペース名</flux:label>
                <flux:input wire:model.live="name" placeholder="例: 開発チーム" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>スラッグ</flux:label>
                <flux:input wire:model="slug" placeholder="例: dev-team" />
                <flux:description>URLに使用される識別子です。</flux:description>
                <flux:error name="slug" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">作成する</flux:button>
            </div>
        </form>
    </flux:modal>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($this->workspaces as $workspace)
            <a href="{{ route('echochat.chat', ['workspace' => $workspace->slug]) }}" class="group block p-6 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <flux:heading level="3" class="group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            {{ $workspace->name }}
                        </flux:heading>
                        @if ($workspace->unread_count > 0)
                            <flux:badge variant="danger" size="sm" inset="top" class="ml-2" data-testid="unread-badge">
                                {{ $workspace->unread_count }}
                            </flux:badge>
                        @endif
                    </div>
                    <flux:icon name="chevron-right" variant="mini" class="text-zinc-400 group-hover:text-blue-600 transition-colors" />
                </div>
                <div class="mt-6 flex items-center gap-4">
                    <div class="flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="users" variant="mini" class="mr-1" />
                        {{ $workspace->members()->count() + 1 }} メンバー
                    </div>
                    <div class="flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="chat-bubble-left-right" variant="mini" class="mr-1" />
                        {{ $workspace->channels()->count() }} チャンネル
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full py-12 text-center bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700">
                <flux:icon name="building-office-2" class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading level="3" class="mt-4">ワークスペースが見つかりません</flux:heading>
                <flux:text class="mt-2">現在、所属しているワークスペースはありません。</flux:text>
            </div>
        @endforelse
    </div>
</div>
