<?php

use EchoChat\Models\Workspace;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public Workspace $workspace;
    public array $selectedUserIds = [];
    public string $message = '';

    #[Computed]
    public function eligibleUsers()
    {
        // まだワークスペースのメンバーでもオーナーでもないユーザーを取得
        $memberIds = $this->workspace->members()->pluck('users.id')->toArray();
        $memberIds[] = $this->workspace->owner_id;

        return User::whereNotIn('id', $memberIds)->get();
    }

    public function invite()
    {
        $this->authorize('invite', $this->workspace);

        if (empty($this->selectedUserIds)) {
            $this->message = '招待するユーザーを選択してください。';
            return;
        }

        $users = User::whereIn('id', $this->selectedUserIds)->get();

        foreach ($users as $user) {
            // 念のため再度チェック
            if ($this->workspace->owner_id === $user->id || $this->workspace->members()->where('user_id', $user->id)->exists()) {
                continue;
            }

            $this->workspace->members()->attach($user->id);
        }

        $this->selectedUserIds = [];
        $this->message = count($users) . '人のユーザーを招待しました。';
        $this->dispatch('workspaceMemberAdded');
    }
}; ?>

<div class="p-4 bg-white dark:bg-zinc-800 rounded-lg">
    <h3 class="text-lg font-bold mb-4 dark:text-white">ワークスペースにメンバーを招待</h3>
    <form wire:submit.prevent="invite">
        <div class="space-y-4">
            <div class="max-h-60 overflow-y-auto space-y-2">
                @forelse($this->eligibleUsers as $user)
                    <label class="flex items-center gap-2 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 rounded cursor-pointer">
                        <flux:checkbox wire:model.live="selectedUserIds" value="{{ $user->id }}" />
                        <div class="flex flex-col">
                            <span class="text-sm font-medium dark:text-white">{{ \EchoChat\Support\UserSupport::getName($user) }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</span>
                        </div>
                    </label>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 p-2">招待可能なユーザーはいません。</p>
                @endforelse
            </div>

            @if($message)
                <p class="text-sm text-blue-600 dark:text-blue-400">{{ $message }}</p>
            @endif

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">招待</flux:button>
            </div>
        </div>
    </form>
</div>
