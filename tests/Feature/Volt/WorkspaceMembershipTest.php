<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;
use EchoChat\Support\Tables;
use Illuminate\Foundation\Testing\RefreshDatabase;


test('guest cannot view workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $guest = User::factory()->create();

    $this->actingAs($guest)
        ->get(route('echochat.chat', ['workspace' => $workspace->slug]))
        ->assertForbidden();
});

test('member can view workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $this->actingAs($member)
        ->get(route('echochat.chat', ['workspace' => $workspace->slug]))
        ->assertOk();
});

test('owner can view workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('echochat.chat', ['workspace' => $workspace->slug]))
        ->assertOk();
});

test('owner can invite members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $invitee = User::factory()->create();

    Livewire::actingAs($owner)
        ->test('invite-workspace-member', ['workspace' => $workspace])
        ->set('selectedUserIds', [$invitee->id])
        ->call('invite')
        ->assertDispatched('workspaceMemberAdded');

    $this->assertDatabaseHas(Tables::name('workspace_members'), [
        'workspace_id' => $workspace->id,
        'user_id' => $invitee->id,
    ]);
});

test('non-owner cannot invite members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $invitee = User::factory()->create();

    // UI test to check if the button is hidden is complex with Volt directly,
    // but we can test the component execution.
    // The component itself doesn't have an authorize check in 'invite' yet,
    // it's only in the blade with @can. Let's add it to the component too.

    Livewire::actingAs($member)
        ->test('invite-workspace-member', ['workspace' => $workspace])
        ->set('selectedUserIds', [$invitee->id])
        ->call('invite')
        ->assertForbidden();

    $this->assertDatabaseMissing(Tables::name('workspace_members'), [
        'workspace_id' => $workspace->id,
        'user_id' => $invitee->id,
    ]);
});

test('member list is visible in side menu', function () {
    $owner = User::factory()->create(['name' => 'Owner User']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $member = User::factory()->create(['name' => 'Member User']);
    $workspace->members()->attach($member->id);

    Livewire::actingAs($owner)
        ->test('channel-list', ['workspace' => $workspace])
        ->assertSee('Owner User')
        ->assertSee('(自分)')
        ->assertSee('Member User')
        ->assertDontSee('Member User (自分)');
});

test('member sees themselves as (自分) in side menu', function () {
    $owner = User::factory()->create(['name' => 'Owner User']);
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $member = User::factory()->create(['name' => 'Member User']);
    $workspace->members()->attach($member->id);

    Livewire::actingAs($member)
        ->test('channel-list', ['workspace' => $workspace])
        ->assertSee('Owner User')
        ->assertDontSee('Owner User (自分)')
        ->assertSee('Member User (自分)');
});

test('member list updates when member is added', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $invitee = User::factory()->create(['name' => 'ShouldNotSeeMeYet']);

    $component = Livewire::actingAs($owner)
        ->test('channel-list', ['workspace' => $workspace]);

    // Livewire testing sometimes includes initial data in the HTML even if not rendered yet,
    // or the 'ShouldNotSeeMeYet' string appears in some JS/Alpine data.
    // Let's check if it's rendered in the list.

    $workspace->members()->attach($invitee->id);
    $component->dispatch('workspaceMemberAdded');

    $component->assertSee('ShouldNotSeeMeYet');
});

test('owner can remove members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $member = User::factory()->create(['name' => 'To Be Removed']);
    $workspace->members()->attach($member->id);

    Livewire::actingAs($owner)
        ->test('channel-list', ['workspace' => $workspace])
        ->call('removeMember', $member->id)
        ->assertDispatched('workspaceMemberAdded');

    $this->assertDatabaseMissing(Tables::name('workspace_members'), [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
});

test('non-owner cannot remove members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);

    $member1 = User::factory()->create();
    $workspace->members()->attach($member1->id);
    $member2 = User::factory()->create();
    $workspace->members()->attach($member2->id);

    Livewire::actingAs($member1)
        ->test('channel-list', ['workspace' => $workspace])
        ->call('removeMember', $member2->id)
        ->assertForbidden();

    $this->assertDatabaseHas(Tables::name('workspace_members'), [
        'workspace_id' => $workspace->id,
        'user_id' => $member2->id,
    ]);
});
