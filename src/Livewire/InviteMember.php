<?php

namespace EchoChat\Livewire;

use App\Models\User;
use EchoChat\Models\Channel;
use EchoChat\Support\UserSupport;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InviteMember extends Component
{
    public Channel $channel;

    public array $selectedUserIds = [];

    public string $search = '';

    public string $message = '';

    #[Computed]
    public function workspaceMembers(): Collection
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

    public function invite(): void
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

            $userName = UserSupport::getName($user);
            $this->channel->messages()->create([
                'user_id' => auth()->id(),
                'content' => "{$userName}を招待しました",
            ]);
        }

        $this->selectedUserIds = [];
        $this->message = count($users).'人のユーザーを招待しました。';
        $this->dispatch('memberAdded');
        $this->dispatch('memberAdded')->to(Chat::class);
        $this->dispatch('messageSent');
    }

    public function render(): View
    {
        return view('echochat::pages.invite-member');
    }
}
