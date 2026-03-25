<?php

use App\Models\User;
use EchoChat\Events\MessageSent;
use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use EchoChat\Support\UserSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->beforeEach(function () {
    config(['broadcasting.default' => 'reverb']);

    require base_path('routes/channels.php');
    require __DIR__.'/../../routes/channels.php';
});

test('MessageSent event is broadcastable on correct channels', function () {
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

    $event = new MessageSent($message);
    $channels = $event->broadcastOn();

    // workspace + workspace.channel + user channel (owner)
    expect($channels)->toHaveCount(3);
    expect($channels[0]->name)->toBe('private-workspace.'.$workspace->id);
    expect($channels[1]->name)->toBe('private-workspace.'.$workspace->id.'.channel.'.$channel->id);
    expect($channels[2]->name)->toBe('private-App.Models.User.'.$user->id);
});

test('MessageSent event contains correct data', function () {
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

    $event = new MessageSent($message);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('content', 'Test message');
    expect($data)->toHaveKey('user_name', UserSupport::getName($user));
    expect($data)->toHaveKey('channel_id', $channel->id);
});

test('UserSupport::getName falls back to fallback column when primary is empty', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
    ]);

    config(['echochat.user_name_column' => 'nickname']);
    config(['echochat.user_name_column_fallback' => 'name']);

    // nickname is null, should return 'Original Name'
    expect(UserSupport::getName($user))->toBe('Original Name');

    // nickname is set, should return nickname
    $user->nickname = 'Nick';
    expect(UserSupport::getName($user))->toBe('Nick');
});

test('broadcast channels are authorized for authenticated users', function () {
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

    $this->actingAs($user);

    // Workspace channel authorization
    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-workspace.'.$workspace->id,
        'socket_id' => '123.456',
    ]);
    $response->assertSuccessful();

    // Channel channel authorization
    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-workspace.'.$workspace->id.'.channel.'.$channel->id,
        'socket_id' => '123.456',
    ]);
    $response->assertSuccessful();
});

test('broadcast channels are not authorized for guests', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-workspace.'.$workspace->id,
        'socket_id' => '123.456',
    ]);

    $response->assertStatus(403);
});
