<?php

namespace EchoChat\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

interface HasWorkspaces
{
    /**
     * Get the workspaces owned by the user.
     */
    public function ownedWorkspaces(): HasMany;

    /**
     * Get the workspaces where the user is a member.
     */
    public function workspaces(): BelongsToMany;

    /**
     * Get all workspaces associated with the user (owned or member).
     */
    public function getAllWorkspaces(): Collection;

    /**
     * Get the total unread messages count across all workspaces.
     */
    public function getTotalUnreadCount(): int;
}
