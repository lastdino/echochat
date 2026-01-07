<?php

use App\Models\User;
use EchoChat\Events\MessageSent;
use EchoChat\Models\ChannelUser;
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
        ->assertSee('general');
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

    $this->assertDatabaseHas(Tables::name('channel_user'), [
        'channel_id' => $channel->id,
        'user_id' => $user->id,
    ]);

    Event::assertDispatched(MessageSent::class);
});

test('messages with emojis can be sent', function () {
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
        ->set('content', 'Hello 😀')
        ->call('sendMessage')
        ->assertDispatched('messageSent');

    $this->assertDatabaseHas(Tables::name('messages'), [
        'content' => 'Hello 😀',
        'user_id' => $user->id,
        'channel_id' => $channel->id,
    ]);
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
        'name' => 'New Channel',
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
    ]);
});

test('replying to a message', function () {
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

    $parentMessage = $channel->messages()->create([
        'user_id' => $user->id,
        'content' => 'Parent message',
    ]);

    Volt::actingAs($user)
        ->test('message-input', ['channel' => $channel])
        ->call('setReplyTo', $parentMessage->id)
        ->assertSet('replyToId', $parentMessage->id)
        ->set('content', 'Reply message')
        ->call('sendMessage')
        ->assertDispatched('messageSent');

    $this->assertDatabaseHas(Tables::name('messages'), [
        'content' => 'Reply message',
        'parent_id' => $parentMessage->id,
        'user_id' => $user->id,
        'channel_id' => $channel->id,
    ]);
});

test('channel list receives notifications', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel1 = $workspace->channels()->create(['name' => 'general', 'creator_id' => $user->id]);
    $channel2 = $workspace->channels()->create(['name' => 'random', 'creator_id' => $user->id]);

    Volt::actingAs($user)
        ->test('channel-list', ['workspace' => $workspace, 'activeChannel' => $channel1])
        ->call('handleIncomingMessage', ['channel_id' => $channel2->id])
        ->assertSet('notifications.'.$channel2->id, 1)
        ->call('selectChannel', $channel2->id)
        ->assertSet('notifications.'.$channel2->id, 0)
        ->assertSet('activeChannel.id', $channel2->id);
});

test('channel list shows persistent notifications on mount', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create(['name' => 'general', 'creator_id' => $user->id]);

    // Create a message while user hasn't read it
    $channel->messages()->create([
        'user_id' => User::factory()->create()->id,
        'content' => 'Unread message',
        'created_at' => now()->subMinutes(5),
    ]);

    Volt::actingAs($user)
        ->test('channel-list', ['workspace' => $workspace])
        ->assertSet('notifications.'.$channel->id, 1);

    // Update last read
    ChannelUser::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    Volt::actingAs($user)
        ->test('channel-list', ['workspace' => $workspace])
        ->assertSet('notifications.'.$channel->id, 0);
});

test('channel members avatars are displayed', function () {
    $user1 = User::factory()->create(['name' => 'User One']);
    $user2 = User::factory()->create(['name' => 'User Two']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user1->id,
    ]);
    $channel = $workspace->channels()->create(['name' => 'general', 'creator_id' => $user1->id]);
    $channel->members()->create(['user_id' => $user1->id]);
    $channel->members()->create(['user_id' => $user2->id]);

    Volt::actingAs($user1)
        ->test('chat', ['workspace' => $workspace, 'channel' => $channel])
        ->assertSee('User One')
        ->assertSee('User Two');
});

test('message feed displays user avatars', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user->id,
    ]);
    $channel = $workspace->channels()->create(['name' => 'general', 'creator_id' => $user->id]);
    $channel->messages()->create([
        'user_id' => $user->id,
        'content' => 'Test message',
    ]);

    Volt::actingAs($user)
        ->test('message-feed', ['channel' => $channel])
        ->assertSee('John Doe')
        ->assertSee('Test message');
});

test('joining a public channel creates a message', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => User::factory()->create()->id,
    ]);
    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $workspace->owner_id,
        'is_private' => false,
    ]);

    Volt::actingAs($user)
        ->test('chat', ['workspace' => $workspace, 'channel' => $channel])
        ->call('joinChannel')
        ->assertDispatched('messageSent');

    $this->assertDatabaseHas(Tables::name('messages'), [
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'content' => "# {$channel->name} に参加しました",
    ]);
});

test('inviting a member to a private channel creates a message', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['name' => 'Invited User']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);
    $workspace->members()->attach($invitee->id);
    $channel = $workspace->channels()->create([
        'name' => 'private-channel',
        'creator_id' => $owner->id,
        'is_private' => true,
    ]);
    $channel->members()->create(['user_id' => $owner->id]);

    Volt::actingAs($owner)
        ->test('invite-member', ['channel' => $channel])
        ->set('selectedUserIds', [$invitee->id])
        ->call('invite')
        ->assertDispatched('messageSent');

    $this->assertDatabaseHas(Tables::name('messages'), [
        'channel_id' => $channel->id,
        'user_id' => $owner->id,
        'content' => 'Invited Userを招待しました',
    ]);
});
