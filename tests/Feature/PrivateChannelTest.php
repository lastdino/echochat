<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;


test('unread count updates for private channel in workspace list', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);
    $workspace->members()->attach($member->id);

    // プライベートチャンネルを作成
    $privateChannel = $workspace->channels()->create([
        'name' => 'private',
        'is_private' => true,
        'creator_id' => $owner->id,
    ]);

    // メンバーをプライベートチャンネルに追加
    $privateChannel->members()->create(['user_id' => $member->id]);

    // memberとしてログインしてワークスペース一覧を表示
    $component = Livewire::actingAs($member)->test('workspace-list');
    $component->assertDontSee('unread-badge');

    // ownerがプライベートチャンネルにメッセージを送信
    $message = $privateChannel->messages()->create([
        'user_id' => $owner->id,
        'content' => 'Private message',
    ]);

    // イベントをディスパッチ (User個別チャンネル)
    $component->dispatch('echo-private:App.Models.User.'.$member->id.',.EchoChat\\Events\\MessageSent');

    // 未読バッジが表示されることを確認
    $component->assertSee('unread-badge')
        ->assertSee('1');

    // 再度メッセージを送信
    $privateChannel->messages()->create([
        'user_id' => $owner->id,
        'content' => 'Another private message',
    ]);

    // イベントをディスパッチ (Workspaceチャンネル)
    $component->dispatch('echo-private:workspace.'.$workspace->id.',.EchoChat\\Events\\MessageSent');

    $component->assertSee('unread-badge')
        ->assertSee('2');
});

test('total unread count updates in nav item for private channel', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $owner->id,
    ]);
    $workspace->members()->attach($member->id);

    $privateChannel = $workspace->channels()->create([
        'name' => 'private',
        'is_private' => true,
        'creator_id' => $owner->id,
    ]);
    $privateChannel->members()->create(['user_id' => $member->id]);

    $component = Livewire::actingAs($member)->test('nav-item');
    $component->assertSet('unreadNotifications', 0);

    // ownerがメッセージを送信
    $message = $privateChannel->messages()->create([
        'user_id' => $owner->id,
        'content' => 'Private message',
    ]);

    // イベントをディスパッチ
    $component->dispatch('echo-private:App.Models.User.'.$member->id.',.EchoChat\\Events\\MessageSent');

    // 合計未読件数が更新されることを確認
    $component->assertSet('unreadNotifications', 1);
});

test('message feed refreshes on message sent event', function () {
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

    $component = Livewire::actingAs($user)
        ->test('message-feed', ['channel' => $channel]);

    $component->assertDontSee('Hello Realtime');

    // 他のユーザーからメッセージが届く
    $otherUser = User::factory()->create();
    $message = $channel->messages()->create([
        'user_id' => $otherUser->id,
        'content' => 'Hello Realtime',
    ]);

    // イベントをディスパッチ
    $component->dispatch("echo-private:workspace.{$workspace->id}.channel.{$channel->id},.EchoChat\\Events\\MessageSent");

    // メッセージが表示されることを確認
    $component->assertSee('Hello Realtime');
});
