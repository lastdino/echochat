<?php

use EchoChat\Models\Workspace;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public Workspace $workspace;
    public string $email = '';
    public string $message = '';

    public function invite()
    {
        $this->authorize('invite', $this->workspace);

        $this->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $this->email)->first();

        // オーナーかメンバーかチェック
        if ($this->workspace->owner_id === $user->id || $this->workspace->members()->where('user_id', $user->id)->exists()) {
            $this->message = 'このユーザーはすでにメンバーです。';
            return;
        }

        $this->workspace->members()->attach($user->id);

        $this->email = '';
        $this->message = 'ユーザーを招待しました。';
        $this->dispatch('workspaceMemberAdded');
    }
}; ?>

<div class="p-4">
    <h3 class="text-lg font-bold mb-4 dark:text-white">ワークスペースにメンバーを招待</h3>
    <form wire:submit.prevent="invite">
        <div class="space-y-4">
            <flux:field>
                <flux:label>メールアドレス</flux:label>
                <flux:input type="email" wire:model="email" placeholder="user@example.com" />
                <flux:error name="email" />
            </flux:field>

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
