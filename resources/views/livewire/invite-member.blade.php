<?php

use App\Models\User;
use EchoChat\Models\Channel;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public Channel $channel;

    public array $selectedUserIds = [];

    public string $search = '';

    public string $message = '';

    #[Computed]
    public function workspaceMembers()
    {
        // チャンネルのメンバーではないワークスペースメンバーを取得
        return $this->channel->workspace->members()
            ->whereNotIn('users.id', $this->channel->members()->pluck('user_id'))
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->get();
    }

    public function invite()
    {
        if (empty($this->selectedUserIds)) {
            $this->message = '招待するユーザーを選択してください。';

            return;
        }

        $users = User::whereIn('id', $this->selectedUserIds)->get();

        foreach ($users as $user) {
            // 念のため再度チェック（すでにメンバーか）
            if ($this->channel->isMember($user->id)) {
                continue;
            }

            $this->channel->members()->create([
                'user_id' => $user->id,
            ]);

            $userName = \EchoChat\Support\UserSupport::getName($user);
            $this->channel->messages()->create([
                'user_id' => auth()->id(),
                'content' => "{$userName}を招待しました",
            ]);
        }

        $this->selectedUserIds = [];
        $this->message = count($users).'人のユーザーを招待しました。';
        $this->dispatch('memberAdded');
        $this->dispatch('memberAdded')->to('chat');
        $this->dispatch('messageSent');
    }
}; ?>

<div class="p-4 bg-white dark:bg-zinc-800 rounded-lg shadow">
    <h3 class="text-lg font-bold mb-4 dark:text-white">メンバーを招待</h3>
    <form wire:submit.prevent="invite">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="ユーザー名またはメールアドレスで検索..." icon="magnifying-glass" />

            <div class="max-h-60 overflow-y-auto space-y-2">
                @forelse($this->workspaceMembers as $member)
                    <label wire:key="member-{{ $member->id }}" class="flex items-center gap-2 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 rounded cursor-pointer">
                        <flux:checkbox wire:model.live="selectedUserIds" value="{{ $member->id }}" />
                        <div class="flex flex-col">
                            <span class="text-sm font-medium dark:text-white">{{ \EchoChat\Support\UserSupport::getName($member) }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</span>
                        </div>
                    </label>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 p-2">招待可能なワークスペースメンバーはいません。</p>
                @endforelse
            </div>

            @if($message)
                <p class="text-sm text-blue-600 dark:text-blue-400">{{ $message }}</p>
            @endif

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary" :disabled="empty($selectedUserIds)">招待</flux:button>
            </div>
        </div>
    </form>
</div>
