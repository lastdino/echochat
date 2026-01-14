<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EchoChat Table Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be prepended to all EchoChat database tables.
    |
    */
    'table_prefix' => 'echochat_',

    /*
    |--------------------------------------------------------------------------
    | EchoChat Route Path
    |--------------------------------------------------------------------------
    |
    | This is the path where EchoChat will be accessible.
    |
    */
    'path' => 'echochat',

    /*
    |--------------------------------------------------------------------------
    | User Name Column
    |--------------------------------------------------------------------------
    |
    | This is the column name in your users table that should be used
    | as the display name for users.
    |
    */
    'user_name_column' => 'name',

    /*
    |--------------------------------------------------------------------------
    | User Name Fallback Column
    |--------------------------------------------------------------------------
    |
    | This is the column name in your users table that should be used
    | as a fallback if the primary display name column is empty.
    |
    */
    'user_name_column_fallback' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Date Format
    |--------------------------------------------------------------------------
    |
    | This format will be used to display dates in the message feed.
    | It should be a format string compatible with Carbon's translatedFormat().
    |
    */
    'date_format' => 'n月j日 (D)',

    /*
    |--------------------------------------------------------------------------
    | Flux Pro Configuration
    |--------------------------------------------------------------------------
    |
    | If you have a Flux Pro subscription, you can set this to true
    | to enable Pro components like flux:composer and flux:editor.
    |
    */
    'flux_pro' => env('ECHOCHAT_FLUX_PRO', false),

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI features like channel summarization.
    |
    */
    'ai' => [
        'driver' => env('ECHOCHAT_AI_DRIVER', 'gemini'), // 'gemini' or 'ollama'
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'ollama_endpoint' => env('OLLAMA_ENDPOINT', 'http://localhost:11434/api/generate'),
        'ollama_model' => env('OLLAMA_MODEL', 'llama3'),
        'message_limit' => env('ECHOCHAT_AI_MESSAGE_LIMIT', 50),
        'prompt' => env('ECHOCHAT_AI_PROMPT', "以下のチャット履歴を簡潔に日本語で要約してください。\n\n:messages"),
    ],
];
