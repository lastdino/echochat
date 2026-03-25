<?php

use App\Models\User;
use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use Livewire\Livewire;

test('it can create a channel', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test(\EchoChat\Livewire\CreateChannel::class, ['workspace' => $workspace])
        ->set('name', 'New Channel')
        ->set('is_private', false)
        ->call('createChannel')
        ->assertDispatched('channelCreated');

    expect(Channel::where('name', 'New Channel')->exists())->toBeTrue();
    $channel = Channel::where('name', 'New Channel')->first();
    expect($channel->members()->where('user_id', $owner->id)->exists())->toBeTrue();
});

test('channel list shows add channel item', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test(\EchoChat\Livewire\ChannelList::class, ['workspace' => $workspace])
        ->assertSee('チャンネルを追加...');
});

test('member can see add channel item if allowed', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
        'allow_member_channel_creation' => true,
    ]);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $this->actingAs($member);

    Livewire::test(\EchoChat\Livewire\ChannelList::class, ['workspace' => $workspace])
        ->assertSee('チャンネルを追加...');
});

test('member cannot see add channel item if not allowed', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
        'allow_member_channel_creation' => false,
    ]);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $this->actingAs($member);

    Livewire::test(\EchoChat\Livewire\ChannelList::class, ['workspace' => $workspace])
        ->assertDontSee('チャンネルを追加...');
});

test('member can create channel if allowed', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
        'allow_member_channel_creation' => true,
    ]);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $this->actingAs($member);

    Livewire::test(\EchoChat\Livewire\CreateChannel::class, ['workspace' => $workspace])
        ->set('name', 'Member Channel')
        ->call('createChannel')
        ->assertDispatched('channelCreated');

    expect(Channel::where('name', 'Member Channel')->exists())->toBeTrue();
});

test('member cannot create channel if not allowed', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
        'allow_member_channel_creation' => false,
    ]);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $this->actingAs($member);

    Livewire::test(\EchoChat\Livewire\CreateChannel::class, ['workspace' => $workspace])
        ->set('name', 'Member Channel')
        ->call('createChannel')
        ->assertForbidden();

    expect(Channel::where('name', 'Member Channel')->exists())->toBeFalse();
});
