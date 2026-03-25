<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;


test('it receives setReplyTo event in standard input', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);
    $message = $channel->messages()->create([
        'user_id' => $user->id,
        'content' => 'Test message',
    ]);

    Livewire::actingAs($user)
        ->test('message-input', ['channel' => $channel])
        ->dispatch('setReplyTo', messageId: $message->id)
        ->assertSet('replyToId', $message->id);
});

test('it receives setReplyTo event in Pro input', function () {
    config(['echochat.flux_pro' => true]);

    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);
    $message = $channel->messages()->create([
        'user_id' => $user->id,
        'content' => 'Test message',
    ]);

    Livewire::actingAs($user)
        ->test('message-input-pro', ['channel' => $channel])
        ->dispatch('setReplyTo', messageId: $message->id)
        ->assertSet('replyToId', $message->id);
});
