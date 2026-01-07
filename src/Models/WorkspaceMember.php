<?php

namespace EchoChat\Models;

use App\Models\User;
use EchoChat\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    public function getTable()
    {
        return Tables::name('workspace_members');
    }

    protected $fillable = ['workspace_id', 'user_id'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
