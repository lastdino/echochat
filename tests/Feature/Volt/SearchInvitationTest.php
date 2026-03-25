<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;


test('can search users in workspace invitation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    Livewire::actingAs($owner)
        ->test('invite-workspace-member', ['workspace' => $workspace])
        ->set('search', 'John')
        ->assertSee('John Doe')
        ->assertDontSee('Jane Smith')
        ->set('search', 'jane@example.com')
        ->assertSee('Jane Smith')
        ->assertDontSee('John Doe');
});

test('can search users in channel invitation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'name' => 'general',
        'type' => 'public',
        'creator_id' => $owner->id,
    ]);

    $user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    $workspace->members()->attach([$user1->id, $user2->id]);

    Livewire::actingAs($owner)
        ->test('invite-member', ['channel' => $channel])
        ->set('search', 'John')
        ->assertSee('John Doe')
        ->assertDontSee('Jane Smith')
        ->set('search', 'jane@example.com')
        ->assertSee('Jane Smith')
        ->assertDontSee('John Doe');
});
