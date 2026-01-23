<?php

namespace EchoChat\Support;

use Illuminate\Database\Eloquent\Model;

class UserSupport
{
    /**
     * Get the display name for a user.
     */
    public static function getName(?Model $user): string
    {
        if (! $user) {
            return '';
        }

        $column = config('echochat.user_name_column', 'name');
        $fallback = config('echochat.user_name_column_fallback', 'name');

        return $user->{$column} ?? $user->{$fallback} ?? '';
    }
}
