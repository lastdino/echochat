<?php

use Livewire\Livewire;

use App\Models\User;
use EchoChat\Models\Workspace;
use EchoChat\Support\Tables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('messages can be sent with attachments', function () {
    Storage::fake('local');

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

    $file = UploadedFile::fake()->image('test-image.jpg');

    Livewire::actingAs($user)
        ->test(\EchoChat\Livewire\MessageInput::class, ['channel' => $channel])
        ->set('content', 'Message with attachment')
        ->set('attachments', [$file])
        ->call('sendMessage')
        ->assertDispatched('messageSent');

    $this->assertDatabaseHas(Tables::name('messages'), [
        'content' => 'Message with attachment',
        'user_id' => $user->id,
        'channel_id' => $channel->id,
    ]);

    $message = \EchoChat\Models\Message::latest()->first();
    expect($message->getMedia('attachments'))->toHaveCount(1);
    expect($message->getMedia('attachments')->first()->file_name)->toBe('test-image.jpg');
});

test('can remove attachment before sending', function () {
    Storage::fake('local');

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

    $file1 = UploadedFile::fake()->image('file1.jpg');
    $file2 = UploadedFile::fake()->image('file2.jpg');

    Livewire::actingAs($user)
        ->test('message-input', ['channel' => $channel])
        ->set('attachments', [$file1, $file2])
        ->call('removeAttachment', 0)
        ->assertCount('attachments', 1);
});

test('message feed displays attachments', function () {
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

    $file = UploadedFile::fake()->image('test-image.jpg');
    $message->addMedia($file)->toMediaCollection('attachments');

    Livewire::actingAs($user)
        ->test('message-feed', ['channel' => $channel])
        ->assertSee($message->media->first()->uuid)
        ->assertSee('Test message');
});

test('attachments can be deleted from message feed', function () {
    Storage::fake('local');

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
        'content' => 'Message with attachment to delete',
    ]);

    $file = UploadedFile::fake()->image('delete-me.jpg');
    $media = $message->addMedia($file)->toMediaCollection('attachments');

    expect($message->getMedia('attachments'))->toHaveCount(1);

    Livewire::actingAs($user)
        ->test('message-feed', ['channel' => $channel])
        ->call('deleteAttachment', $message->id, $media->id)
        ->assertDispatched('messageSent');

    $message->refresh();
    expect($message->getMedia('attachments'))->toHaveCount(0);
});

test('other users cannot delete attachments', function () {
    Storage::fake('local');

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Test Workspace',
        'slug' => 'test-workspace',
        'owner_id' => $user1->id,
    ]);
    $channel = $workspace->channels()->create([
        'name' => 'general',
        'creator_id' => $user1->id,
    ]);

    $message = $channel->messages()->create([
        'user_id' => $user1->id,
        'content' => 'Message with attachment',
    ]);

    $file = UploadedFile::fake()->image('keep-me.jpg');
    $media = $message->addMedia($file)->toMediaCollection('attachments');

    Livewire::actingAs($user2)
        ->test('message-feed', ['channel' => $channel])
        ->call('deleteAttachment', $message->id, $media->id);

    $message->refresh();
    expect($message->getMedia('attachments'))->toHaveCount(1);
});

test('can access attachment via secure route', function () {
    Storage::fake('local');

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
        'content' => 'Message with attachment',
    ]);

    $file = UploadedFile::fake()->image('test.jpg');
    $media = $message->addMedia($file)->toMediaCollection('attachments');

    // 認証済みかつメンバーならアクセス可能
    $this->actingAs($user)
        ->get(route('echochat.attachments.show', ['media' => $media->uuid]))
        ->assertSuccessful()
        ->assertHeader('Content-Disposition', 'attachment; filename=test.jpg');

    // 未認証ならリダイレクト（ログインへ）
    auth()->logout();
    $this->get(route('echochat.attachments.show', ['media' => $media->uuid]))
        ->assertRedirect('/login');

    // 他のユーザー（メンバー外）ならアクセス拒否
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)
        ->get(route('echochat.attachments.show', ['media' => $media->uuid]))
        ->assertForbidden();
});
