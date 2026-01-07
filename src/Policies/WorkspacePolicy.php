<?php

namespace EchoChat\Policies;

use App\Models\User;
use EchoChat\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id ||
               $workspace->members()->where('user_id', $user->id)->exists();
    }

    public function invite(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function removeMember(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
