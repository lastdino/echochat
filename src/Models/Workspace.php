<?php

namespace EchoChat\Models;

use App\Models\User;
use EchoChat\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    public function getTable()
    {
        return Tables::name('workspaces');
    }

    protected $fillable = ['name', 'slug', 'owner_id', 'allow_member_channel_creation', 'allow_member_channel_deletion'];

    protected $casts = [
        'allow_member_channel_creation' => 'boolean',
        'allow_member_channel_deletion' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, Tables::name('workspace_members'))
            ->withTimestamps();
    }

    public function getUnreadCountAttribute(): int
    {
        $userId = auth()->id();
        if (! $userId) {
            return 0;
        }

        // キャッシュされたリレーションではなく、常にクエリを発行して最新の状態を取得する
        // DMは未読カウントから除外する
        $channels = $this->channels()->where('is_dm', false)->get();
        $totalUnread = 0;

        foreach ($channels as $channel) {
            // プライベートチャンネルの場合はメンバーである必要がある
            if ($channel->is_private && ! $channel->isMember($userId)) {
                continue;
            }

            $lastRead = ChannelUser::where('channel_id', $channel->id)
                ->where('user_id', $userId)
                ->first()?->last_read_at;

            $query = $channel->messages()->where('user_id', '!=', $userId);

            if ($lastRead) {
                $query->where('created_at', '>', $lastRead);
            }

            $totalUnread += $query->count();
        }

        return $totalUnread;
    }
}
