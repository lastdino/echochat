<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;


test('html messages are rendered as html', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create(['name' => 'general', 'creator_id' => $user->id]);
    $channel->messages()->create([
        'user_id' => $user->id,
        'content' => '<b>Bold Text</b>',
    ]);

    Livewire::actingAs($user)
        ->test('message-feed', ['channel' => $channel])
        ->assertSee('<b>Bold Text</b>', false)
        ->assertDontSee('&lt;b&gt;Bold Text&lt;/b&gt;', false);
});

test('plain text messages are still escaped and nl2br applied', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create(['name' => 'general', 'creator_id' => $user->id]);
    $channel->messages()->create([
        'user_id' => $user->id,
        'content' => "Line 1\nLine 2 & Special characters",
    ]);

    Livewire::actingAs($user)
        ->test('message-feed', ['channel' => $channel])
        ->assertSee('Line 1<br />', false)
        ->assertSee('Line 2 &amp; Special characters', false);
});

test('xss payloads are sanitized', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create(['name' => 'general', 'creator_id' => $user->id]);

    $channel->messages()->create([
        'user_id' => $user->id,
        'content' => '<script>alert("XSS")</script><b onmouseover="alert(1)">Safe</b><a href="javascript:alert(1)">Link</a>',
    ]);

    $res = Livewire::actingAs($user)
        ->test('message-feed', ['channel' => $channel]);

    $res->assertDontSee('<script>', false)
        ->assertSee('Safe', false)
        ->assertSee('href="#"', false);
});
