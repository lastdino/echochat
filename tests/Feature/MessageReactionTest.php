<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Events\ReactionUpdated;
use EchoChat\Models\Message;
use EchoChat\Models\MessageReaction;
use EchoChat\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;


uses(RefreshDatabase::class);

test('user can add and remove a reaction to a message', function () {
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

    Event::fake();

    $this->actingAs($user);

    Livewire::test(\EchoChat\Livewire\MessageFeed::class, ['channel' => $channel])
        ->call('toggleReaction', $message->id, '👍')
        ->assertStatus(200);

    expect(MessageReaction::where('message_id', $message->id)->where('emoji', '👍')->count())->toBe(1);
    Event::assertDispatched(ReactionUpdated::class, function ($event) {
        return $event->action === 'added' && $event->reaction->emoji === '👍';
    });

    Livewire::test(\EchoChat\Livewire\MessageFeed::class, ['channel' => $channel])
        ->call('toggleReaction', $message->id, '👍')
        ->assertStatus(200);

    expect(MessageReaction::where('message_id', $message->id)->where('emoji', '👍')->count())->toBe(0);
    Event::assertDispatched(ReactionUpdated::class, function ($event) {
        return $event->action === 'removed';
    });
});

test('ReactionUpdated event is broadcastable on correct channels', function () {
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

    $reaction = MessageReaction::create([
        'message_id' => $message->id,
        'user_id' => $user->id,
        'emoji' => '❤️',
    ]);

    $event = new ReactionUpdated($reaction);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-workspace.'.$workspace->id.'.channel.'.$channel->id);
});
