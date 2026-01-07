<?php

namespace EchoChat\Models;

use App\Models\User;
use EchoChat\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    public function getTable()
    {
        return Tables::name('channels');
    }

    protected $fillable = ['workspace_id', 'name', 'description', 'is_private', 'is_dm', 'creator_id'];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'is_dm' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChannelMember::class);
    }

    public function isMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->members()->where('user_id', $userId)->exists();
    }

    public function canView(User|int $user): bool
    {
        if (! $this->is_private) {
            return true;
        }

        return $this->isMember($user);
    }

    public function canJoin(User|int $user): bool
    {
        if ($this->is_private) {
            return false;
        }

        return ! $this->isMember($user);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_dm) {
            $otherMember = $this->members()
                ->where('user_id', '!=', auth()->id())
                ->with('user')
                ->first();

            if ($otherMember) {
                return $otherMember->user->name;
            }

            return auth()->user()->name.' (自分)';
        }

        return $this->name ?? '';
    }

    public function getDisplayIconAttribute(): string
    {
        if ($this->is_dm) {
            return 'user';
        }

        return $this->is_private ? 'lock-closed' : 'hashtag';
    }
}
