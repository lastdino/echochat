<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;


uses(RefreshDatabase::class);

test('it can update a channel name', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'Old Name',
        'creator_id' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test(\EchoChat\Livewire\EditChannel::class, ['channel' => $channel])
        ->set('name', 'New Name')
        ->set('description', 'New Description')
        ->call('updateChannel')
        ->assertDispatched('channelUpdated');

    $channel->refresh();
    expect($channel->name)->toBe('New Name');
    expect($channel->description)->toBe('New Description');

    Livewire::test(\EchoChat\Livewire\Chat::class, ['workspace' => $workspace, 'channel' => 'New Name'])
        ->assertSee('New Name')
        ->assertSee('New Description');
});

test('non-owner and non-creator cannot update channel', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $creator = User::factory()->create();
    $workspace->members()->attach($creator->id);

    $channel = $workspace->channels()->create([
        'name' => 'Original Name',
        'creator_id' => $creator->id,
    ]);

    $otherUser = User::factory()->create();
    $workspace->members()->attach($otherUser->id);

    $this->actingAs($otherUser);

    Livewire::test(\EchoChat\Livewire\EditChannel::class, ['channel' => $channel])
        ->set('name', 'Hacked Name')
        ->call('updateChannel')
        ->assertForbidden();

    $channel->refresh();
    expect($channel->name)->toBe('Original Name');
});

test('creator can update their own channel', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $creator = User::factory()->create();
    $workspace->members()->attach($creator->id);

    $channel = $workspace->channels()->create([
        'name' => 'Original Name',
        'creator_id' => $creator->id,
    ]);

    $this->actingAs($creator);

    Livewire::test(\EchoChat\Livewire\EditChannel::class, ['channel' => $channel])
        ->set('name', 'Updated by Creator')
        ->call('updateChannel')
        ->assertDispatched('channelUpdated');

    $channel->refresh();
    expect($channel->name)->toBe('Updated by Creator');
});

test('it does not throw TypeError when channel name or description is null', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    // Use query builder to bypass any model events or validation if necessary,
    // though create should work if not guarded/validated in a way that prevents null.
    // In database migrations name might be nullable or empty string.
    // The issue says "Cannot assign null to property ... of type string"

    $channel = $workspace->channels()->create([
        'name' => null, // This is what we want to test
        'description' => null,
        'creator_id' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test(\EchoChat\Livewire\EditChannel::class, ['channel' => $channel])
        ->assertSet('name', '')
        ->assertSet('description', '');
});
