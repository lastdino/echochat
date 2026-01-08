<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workspace.{workspaceId}', function ($user, $workspaceId) {
    return auth()->check();
});

Broadcast::channel('workspace.{workspaceId}.channel.{channelId}', function ($user, $workspaceId, $channelId) {
    $channel = \EchoChat\Models\Channel::find($channelId);

    if (! $channel) {
        return false;
    }

    if (! $channel->is_private) {
        return auth()->check();
    }

    // プライベートチャンネルの場合、作成者またはメンバーのみ許可
    $isMember = $channel->members()->where('user_id', $user->id)->exists();

    return (int) $user->id === (int) $channel->creator_id || $isMember;
});
