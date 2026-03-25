<?php

use Livewire\Livewire;

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;


test('nav-item component displays unread count', function () {
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

    $otherUser = User::factory()->create();

    // 他人のメッセージ
    $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Hello',
    ]);

    $this->actingAs($user);

    Livewire::test(\EchoChat\Livewire\NavItem::class)
        ->assertSee('Inbox')
        ->assertSee('1'); // Unread count should be 1
});

test('nav-item updates unread count on Read event', function () {
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

    $component = Livewire::test(\EchoChat\Livewire\NavItem::class)
        ->assertSet('unreadNotifications', 0);

    // メッセージ追加
    $otherUser = User::factory()->create();
    $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Hello',
    ]);

    // channelRead イベントをディスパッチ (nav-item は channelRead をリッスンしている)
    $component->dispatch('channelRead')
        ->assertSet('unreadNotifications', 1);
});

test('nav item does not display badge when unread count is 0', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(\EchoChat\Livewire\NavItem::class)
        ->assertSet('unreadNotifications', 0)
        ->assertDontSee('data-flux-navlist-badge="">0', false);
});
