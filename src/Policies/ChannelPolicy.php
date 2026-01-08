<?php

namespace EchoChat\Policies;

use App\Models\User;
use EchoChat\Models\Channel;

class ChannelPolicy
{
    public function update(User $user, Channel $channel): bool
    {
        // ワークスペースのオーナー、またはチャンネルの作成者が編集可能
        return $channel->workspace->owner_id === $user->id ||
               $channel->creator_id === $user->id;
    }

    public function delete(User $user, Channel $channel): bool
    {
        // ワークスペースのオーナー、またはチャンネルの作成者が削除可能
        if ($channel->workspace->owner_id === $user->id || $channel->creator_id === $user->id) {
            return true;
        }

        // ワークスペースの設定で参加者の削除が許可されている場合
        if ($channel->workspace->allow_member_channel_deletion) {
            return $channel->workspace->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }
}
