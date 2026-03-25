<?php

use App\Models\User;
use EchoChat\Models\Workspace;
use EchoChat\Services\AIModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('summarizeChannel strips HTML tags from messages', function () {
    Config::set('echochat.ai.driver', 'gemini');
    Config::set('echochat.ai.gemini_api_key', 'test-key');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'This is a summary.'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create(['name' => 'John']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    $channel->messages()->create([
        'user_id' => $user->id,
        'content' => '<p>Hello <strong>world</strong>!</p><ul><li>Item 1</li></ul>',
    ]);

    $service = new AIModelService;
    $service->summarizeChannel($channel);

    Http::assertSent(function ($request) {
        $text = $request['contents'][0]['parts'][0]['text'];
        $cleanText = preg_replace('/\s+/', ' ', $text);

        // Check that HTML tags are NOT present in the prompt
        return ! str_contains($text, '<p>') &&
               ! str_contains($text, '<strong>') &&
               ! str_contains($text, '<ul>') &&
               str_contains($cleanText, 'John: Hello world ! Item 1');
    });
});
