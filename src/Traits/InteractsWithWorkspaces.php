<?php

namespace EchoChat\Traits;

use EchoChat\Models\Workspace;
use EchoChat\Support\Tables;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait InteractsWithWorkspaces
{
    /**
     * Get the workspaces owned by the user.
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Get the workspaces where the user is a member.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, Tables::name('workspace_members'))
            ->withTimestamps();
    }

    /**
     * Get all workspaces associated with the user (owned or member).
     */
    public function getAllWorkspaces(): Collection
    {
        return Workspace::query()
            ->where('owner_id', $this->id)
            ->orWhereHas('members', function ($query) {
                $query->where('user_id', $this->id);
            })
            ->get();
    }

    /**
     * Get the total unread messages count across all workspaces.
     */
    public function getTotalUnreadCount(): int
    {
        return $this->getAllWorkspaces()->sum(function (Workspace $workspace) {
            // 各ワークスペースの最新の未読数を計算するために、
            // getUnreadCountAttribute 内で channels()->get() が呼ばれるようになっている。
            return $workspace->unread_count;
        });
    }
}
