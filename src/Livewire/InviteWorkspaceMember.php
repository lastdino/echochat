<?php

namespace EchoChat\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InviteWorkspaceMember extends Component
{
    public Workspace $workspace;

    public array $selectedUserIds = [];

    public string $search = '';

    public string $message = '';

    #[Computed]
    public function eligibleUsers(): Collection
    {
        // まだワークスペースのメンバーでもオーナーでもないユーザーを取得
        $memberIds = $this->workspace->members()->pluck('users.id')->toArray();
        $memberIds[] = $this->workspace->owner_id;

        return User::whereNotIn('id', $memberIds)
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
        Gate::authorize('invite', $this->workspace);

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
        $this->message = count($users).'人のユーザーを招待しました。';
        $this->dispatch('workspaceMemberAdded');
    }

    public function render(): View
    {
        return view('echochat::pages.invite-workspace-member');
    }
}
