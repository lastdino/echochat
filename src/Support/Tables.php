<?php

declare(strict_types=1);

namespace EchoChat\Support;

final class Tables
{
    public static function prefix(): string
    {
        return (string) (config('echochat.table_prefix') ?? 'echochat_');
    }

    public static function name(string $base): string
    {
        $configured = config("echochat.tables.{$base}");

        if ($configured) {
            return $configured;
        }

        return self::prefix().$base;
    }
}
