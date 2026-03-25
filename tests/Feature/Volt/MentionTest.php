<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;


test('it loads mentions for channel members', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);

    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    $channel->members()->create(['user_id' => $user->id]);
    $channel->members()->create(['user_id' => $user2->id]);

    Livewire::actingAs($user)
        ->test('message-input', ['channel' => $channel])
        ->set('mentionSearch', 'Bo')
        ->call('loadMentions')
        ->assertSet('mentionResults', [
            ['id' => $user2->id, 'name' => 'Bob'],
        ]);
});

test('it loads all mentions when search is empty', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);

    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    $channel->members()->create(['user_id' => $user->id]);
    $channel->members()->create(['user_id' => $user2->id]);

    Livewire::actingAs($user)
        ->test('message-input', ['channel' => $channel])
        ->set('mentionSearch', '')
        ->call('loadMentions')
        ->assertSet('mentionResults', [
            ['id' => 'channel', 'name' => 'channel'],
            ['id' => $user->id, 'name' => 'Alice'],
            ['id' => $user2->id, 'name' => 'Bob'],
        ]);
});

test('it loads mentions for channel members in Pro component', function () {
    config(['echochat.flux_pro' => true]);

    $user = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);

    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    $channel->members()->create(['user_id' => $user->id]);
    $channel->members()->create(['user_id' => $user2->id]);

    Livewire::actingAs($user)
        ->test('message-input-pro', ['channel' => $channel])
        ->set('mentionSearch', 'Bo')
        ->call('loadMentions')
        ->assertSet('mentionResults', [
            ['id' => $user2->id, 'name' => 'Bob'],
        ]);
});
