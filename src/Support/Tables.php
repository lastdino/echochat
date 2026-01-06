<?php

declare(strict_types=1);

namespace EchoChat\Support;

final class Tables
{
    public static function prefix(): string
    {
        $configured = (string) (config('echochat.table_prefix') ?? '');

        return $configured !== '' ? $configured : 'echochat_';
    }

    public static function name(string $base): string
    {
        return self::prefix().$base;
    }
}
