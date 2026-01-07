<?php

namespace EchoChat\Models;

use App\Models\User;
use EchoChat\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelMember extends Model
{
    public function getTable()
    {
        return Tables::name('channel_members');
    }

    protected $fillable = ['channel_id', 'user_id'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
