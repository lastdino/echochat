<?php

use App\Models\User;
use EchoChat\Livewire\WorkspaceList;
use EchoChat\Models\Workspace;
use Livewire\Livewire;

test('user can see their owned workspaces', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'My Workspace',
        'slug' => 'my-workspace',
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('echochat.workspaces'))
        ->assertOk()
        ->assertSee('My Workspace');
});

test('user can see workspaces they are a member of', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Joined Workspace',
        'slug' => 'joined-workspace',
        'owner_id' => $owner->id,
    ]);

    $user = User::factory()->create();
    $workspace->members()->attach($user->id);

    $this->actingAs($user)
        ->get(route('echochat.workspaces'))
        ->assertOk()
        ->assertSee('Joined Workspace');
});

test('user cannot see workspaces they are not part of', function () {
    $owner = User::factory()->create();
    Workspace::create([
        'name' => 'Private Workspace',
        'slug' => 'private-workspace',
        'owner_id' => $owner->id,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('echochat.workspaces'))
        ->assertOk()
        ->assertDontSee('Private Workspace');
});

test('workspace list displays member and channel counts', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Stats Workspace',
        'slug' => 'stats-workspace',
        'owner_id' => $user->id,
    ]);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(WorkspaceList::class)
        ->assertSee('2 メンバー') // Owner + 1 member
        ->assertSee('1 チャンネル');
});

test('workspace list displays unread count', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Unread Workspace',
        'slug' => 'unread-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    $otherUser = User::factory()->create();

    // 自分のメッセージは未読にカウントされない
    $channel->messages()->create([
        'user_id' => $user->id,
        'content' => 'My message',
    ]);

    // 他人のメッセージは未読にカウントされる
    $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Other message 1',
    ]);
    $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Other message 2',
    ]);

    Livewire::actingAs($user)
        ->test(WorkspaceList::class)
        ->assertSee('unread-badge')
        ->assertSee('2'); // 2 unread messages

    // 既読にする (未来の日時を設定して、既存のメッセージをすべて既読にする)
    \EchoChat\Models\ChannelUser::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'last_read_at' => now()->addMinutes(1),
    ]);

    // コンポーネントを再作成して、新しいデータでレンダリングされることを確認する
    Livewire::actingAs($user)
        ->test(WorkspaceList::class)
        ->assertDontSee('unread-badge');
});

test('workspace list refreshes on message sent event', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Realtime Workspace',
        'slug' => 'realtime-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    $component = Livewire::actingAs($user)->test(WorkspaceList::class);

    $component->assertDontSee('unread-badge');

    // 他のユーザーからメッセージが届く
    $otherUser = User::factory()->create();
    $message = $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Hello',
    ]);

    // イベントをシミュレート (User個別チャンネル)
    $component->dispatch('echo-private:App.Models.User.'.$user->id.',.EchoChat\\Events\\MessageSent');

    $component->assertSee('unread-badge')
        ->assertSee('1');

    // 別のメッセージを送信
    $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Hello again',
    ]);

    // イベントをシミュレート (Workspaceチャンネル)
    $component->dispatch('echo-private:workspace.'.$workspace->id.',.EchoChat\\Events\\MessageSent');

    $component->assertSee('unread-badge')
        ->assertSee('2');
});

test('workspace list resets unread count on channel read event', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Read Test Workspace',
        'slug' => 'read-test-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    $otherUser = User::factory()->create();
    $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Unread message',
    ]);

    $component = Livewire::actingAs($user)->test(WorkspaceList::class);
    $component->assertSee('unread-badge')
        ->assertSee('1');

    // 既読にする (実際にDBを更新)
    \EchoChat\Models\ChannelUser::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'last_read_at' => now()->addMinute(),
    ]);

    // Livewire イベントをシミュレート
    $component->dispatch('channelRead', channelId: $channel->id);
    $component->assertDontSee('unread-badge');

    // Echo イベント (ChannelRead) をシミュレート
    // 別のチャンネルで未読を作る
    $channel2 = $workspace->channels()->create([
        'name' => 'random',
        'creator_id' => $user->id,
    ]);
    $channel2->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Another unread',
    ]);

    $component->dispatch('echo-private:App.Models.User.'.$user->id.',.EchoChat\\Events\\MessageSent');
    $component->assertSee('unread-badge')
        ->assertSee('1');

    // チャンネル2を既読にする
    \EchoChat\Models\ChannelUser::updateOrCreate(
        ['channel_id' => $channel2->id, 'user_id' => $user->id],
        ['last_read_at' => now()->addMinutes(5)]
    );

    $component->dispatch('echo-private:App.Models.User.'.$user->id.',.EchoChat\\Events\\ChannelRead');
    $component->assertDontSee('unread-badge');
});

test('workspace list reflects unread count even if relation is loaded', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Relation Test Workspace',
        'slug' => 'relation-test-workspace',
        'owner_id' => $user->id,
    ]);

    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user->id,
    ]);

    // 事前にリレーションをロードさせておくような状況をシミュレート
    $component = Livewire::actingAs($user)->test(WorkspaceList::class);
    $component->assertDontSee('unread-badge');

    // 他のユーザーからメッセージが届く
    $otherUser = User::factory()->create();
    $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Hello',
    ]);

    // イベントをシミュレート
    $component->dispatch('echo-private:workspace.'.$workspace->id.',.EchoChat\\Events\\MessageSent');

    // ここで更新されない場合、モデル内の channels リレーションがキャッシュされている可能性がある
    $component->assertSee('unread-badge')
        ->assertSee('1');
});

test('user can create a workspace', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WorkspaceList::class)
        ->set('name', 'New Workspace')
        ->set('slug', 'new-workspace')
        ->call('createWorkspace')
        ->assertRedirect(route('echochat.chat', ['workspace' => 'new-workspace']));

    $this->assertDatabaseHas('echochat_workspaces', [
        'name' => 'New Workspace',
        'slug' => 'new-workspace',
        'owner_id' => $user->id,
    ]);

    $workspace = Workspace::where('slug', 'new-workspace')->first();
    $this->assertDatabaseHas('echochat_channels', [
        'workspace_id' => $workspace->id,
        'name' => '一般',
        'creator_id' => $user->id,
    ]);
});

test('workspace name automatically generates slug', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WorkspaceList::class)
        ->set('name', 'My New Team')
        ->assertSet('slug', 'my-new-team');
});

test('workspace slug must be unique', function () {
    $user = User::factory()->create();
    Workspace::create([
        'name' => 'Existing Workspace',
        'slug' => 'existing',
        'owner_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(WorkspaceList::class)
        ->set('name', 'New Workspace')
        ->set('slug', 'existing')
        ->call('createWorkspace')
        ->assertHasErrors(['slug' => 'unique']);
});
