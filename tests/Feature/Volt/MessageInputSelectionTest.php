<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;


test('it shows regular message-input when flux_pro is false', function () {
    config(['echochat.flux_pro' => false]);

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
    $channel->members()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('chat', ['workspace' => $workspace, 'channel' => 'general'])
        ->assertSeeLivewire('message-input')
        ->assertDontSeeLivewire('message-input-pro');
});

test('it shows message-input-pro when flux_pro is true', function () {
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
    $channel->members()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('chat', ['workspace' => $workspace, 'channel' => 'general'])
        ->assertSeeLivewire('message-input-pro')
        ->assertDontSeeLivewire('message-input');
});
