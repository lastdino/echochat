<?php

use Livewire\Livewire;

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;


test('it automatically joins the general channel after workspace creation', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner);

    Livewire::test(\EchoChat\Livewire\WorkspaceList::class)
        ->set('name', 'New Workspace')
        ->set('slug', 'new-workspace')
        ->call('createWorkspace')
        ->assertRedirect(route('echochat.chat', ['workspace' => 'new-workspace']));

    $workspace = Workspace::where('slug', 'new-workspace')->first();
    $channel = $workspace->channels()->where('name', '一般')->first();

    expect($channel)->not->toBeNull();
    expect($channel->isMember($owner->id))->toBeTrue();

    Livewire::test(\EchoChat\Livewire\Chat::class, ['workspace' => $workspace, 'channel' => '一般'])
        ->assertDontSee('に参加しますか？');
});
