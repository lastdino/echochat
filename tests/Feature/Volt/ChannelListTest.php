<?php

use App\Models\User;
use EchoChat\Livewire\ChannelList;
use EchoChat\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('it refreshes when channelUpdated event is dispatched', function () {
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

    $component = Livewire::test(ChannelList::class, ['workspace' => $workspace]);

    $component->assertSee('Old Name');

    // チャンネル名を更新
    $channel->update(['name' => 'New Name']);

    // イベントをディスパッチしてリフレッシュを確認
    $component->dispatch('channelUpdated');

    $component->assertSee('New Name');
});
