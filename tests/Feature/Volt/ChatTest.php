<?php

use App\Models\User;
use EchoChat\Events\MessageSent;
use EchoChat\Models\Workspace;
use EchoChat\Support\Tables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('chat component can be rendered', function () {
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

    Volt::actingAs($user)
        ->test('chat', ['workspace' => $workspace])
        ->assertSee('# general');
});

test('messages can be sent and broadcasted', function () {
    Event::fake();

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

    Volt::actingAs($user)
        ->test('message-input', ['channel' => $channel])
        ->set('content', 'Hello world')
        ->call('sendMessage')
        ->assertDispatched('messageSent');

    $this->assertDatabaseHas(Tables::name('messages'), [
        'content' => 'Hello world',
        'user_id' => $user->id,
        'channel_id' => $channel->id,
    ]);

    Event::assertDispatched(MessageSent::class);
});

test('channels can be created', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);

    Volt::actingAs($user)
        ->test('create-channel', ['workspace' => $workspace])
        ->set('name', 'New Channel')
        ->call('createChannel')
        ->assertDispatched('channelCreated');

    $this->assertDatabaseHas(Tables::name('channels'), [
        'name' => 'new-channel',
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
    ]);
});
