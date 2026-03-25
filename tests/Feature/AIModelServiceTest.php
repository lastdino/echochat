<?php

use App\Models\User;
use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use EchoChat\Models\Workspace;
use EchoChat\Services\AIModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('strips html tags from messages before summarizing', function () {
    // Mock Config
    Config::set('echochat.ai.driver', 'gemini');
    Config::set('echochat.ai.gemini_api_key', 'fake-key');

    // Mock HTTP for Gemini
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'これは要約です。'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create(['name' => 'Test User']);

    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    // Create a message with HTML
    Message::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'content' => '<p>こんにちは</p><ul><li>項目1</li><li>項目2</li></ul>',
    ]);

    $service = new AIModelService;
    $service->summarizeChannel($channel);

    // Verify the prompt sent to AI
    Http::assertSent(function ($request) {
        $prompt = $request['contents'][0]['parts'][0]['text'];

        // Check if HTML tags are gone
        $noTags = ! str_contains($prompt, '<p>') &&
               ! str_contains($prompt, '<ul>') &&
               ! str_contains($prompt, '<li>');

        $containsText = str_contains($prompt, 'Test User: こんにちは') &&
                        str_contains($prompt, '項目1') &&
                        str_contains($prompt, '項目2');

        return $noTags && $containsText;
    });
});

it('uses workspace specific prompt if set', function () {
    Config::set('echochat.ai.driver', 'gemini');
    Config::set('echochat.ai.gemini_api_key', 'fake-key');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Custom summary']]]]],
        ], 200),
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Custom Workspace',
        'slug' => 'custom-workspace',
        'owner_id' => $user->id,
        'ai_prompt' => "Custom prompt for :messages",
    ]);

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    Message::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'content' => 'Hello world',
    ]);

    $service = new AIModelService;
    $service->summarizeChannel($channel);

    Http::assertSent(function ($request) {
        $prompt = $request['contents'][0]['parts'][0]['text'];

        return str_contains($prompt, 'Custom prompt for') && str_contains($prompt, 'Hello world');
    });
});

it('uses extract specific prompt from config', function () {
    Config::set('echochat.ai.driver', 'gemini');
    Config::set('echochat.ai.gemini_api_key', 'fake-key');
    Config::set('echochat.ai.extract_prompt', 'Extract for :userName in :messages');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Important info']]]]],
        ], 200),
    ]);

    $user = User::factory()->create(['name' => 'Alice']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    Message::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'content' => 'Please review this',
    ]);

    $service = new AIModelService;
    $service->extractImportantInfo($channel, 'Alice');

    Http::assertSent(function ($request) {
        $prompt = $request['contents'][0]['parts'][0]['text'];

        return str_contains($prompt, 'Extract for Alice') && str_contains($prompt, 'Alice: Please review this');
    });
});

it('uses workspace specific extract prompt if set', function () {
    Config::set('echochat.ai.driver', 'gemini');
    Config::set('echochat.ai.gemini_api_key', 'fake-key');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Custom extract summary']]]]],
        ], 200),
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Custom Workspace',
        'slug' => 'custom-workspace',
        'owner_id' => $user->id,
        'extract_ai_prompt' => "Custom extract for :userName: :messages",
    ]);

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    Message::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'content' => 'Need help here',
    ]);

    $service = new AIModelService;
    $service->extractImportantInfo($channel, 'Bob');

    Http::assertSent(function ($request) {
        $prompt = $request['contents'][0]['parts'][0]['text'];

        return str_contains($prompt, 'Custom extract for Bob:') && str_contains($prompt, 'Need help here');
    });
});
