<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use EchoChat\Models\MessageReaction;
use EchoChat\Support\UserSupport;
use Illuminate\View\View;
use Livewire\Component;

class MessageFeed extends Component
{
    public Channel $channel;

    public string $search = '';

    public function getListeners(): array
    {
        return [
            "echo-private:workspace.{$this->channel->workspace_id}.channel.{$this->channel->id},.EchoChat\\Events\\MessageSent" => 'handleMessageSent',
            "echo-private:workspace.{$this->channel->workspace_id}.channel.{$this->channel->id},.EchoChat\\Events\\ReactionUpdated" => '$refresh',
            'messageSent' => 'handleMessageSent',
            'searchMessages' => 'updateSearch',
            'scrollToMessage' => 'scrollToMessage',
        ];
    }

    public function scrollToMessage(int $messageId, array $ancestorIds = []): void
    {
        $this->dispatch('message-target-scrolled', messageId: $messageId, ancestorIds: $ancestorIds);
    }

    public function handleMessageSent(): void
    {
        $this->dispatch('message-sent')->to(Chat::class);
    }

    public function updateSearch(string $search): void
    {
        $this->search = $search;
    }

    public function render(): View
    {
        $query = $this->channel->messages()
            ->with(['user', 'media', 'parent.user', 'reactions.user', 'replies.user', 'replies.media', 'replies.reactions.user', 'replies.replies'])
            ->oldest();

        if (trim($this->search) !== '') {
            $searchTerm = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('content', 'like', $searchTerm)
                    ->orWhereHas('user', function ($uq) use ($searchTerm) {
                        $uq->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('media', function ($mq) use ($searchTerm) {
                        $mq->where('file_name', 'like', $searchTerm);
                    });
            });
        } else {
            // 検索中でない場合はトップレベルのメッセージのみ取得
            $query->whereNull('parent_id');
        }

        $groupedMessages = $query->get()->groupBy(fn ($message) => $message->created_at->translatedFormat(config('echochat.date_format', 'n月j日 (D)')));

        return view('echochat::pages.message-feed', [
            'groupedMessages' => $groupedMessages,
        ]);
    }

    public function replyTo(int $messageId): void
    {
        $this->dispatch('setReplyTo', messageId: $messageId);
    }

    public function deleteAttachment(int $messageId, int $mediaId): void
    {
        $message = Message::findOrFail($messageId);

        // 基本的な権限チェック：メッセージの作成者のみ削除可能とする
        if ($message->user_id !== auth()->id()) {
            return;
        }

        $media = $message->media()->find($mediaId);

        if ($media) {
            $media->delete();
            $this->dispatch('messageSent'); // フィードを更新

            // ブロードキャストも必要かもしれないが、messageSent で $refresh されるので
            // 他のクライアントへの通知は別途検討
            broadcast(new \EchoChat\Events\MessageSent($message->load('media')))->toOthers();
        }
    }

    public function toggleReaction(int $messageId, string $emoji): void
    {
        $userId = auth()->id();
        $reaction = MessageReaction::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($reaction) {
            $reaction->delete();
            broadcast(new \EchoChat\Events\ReactionUpdated($reaction, 'removed'))->toOthers();
        } else {
            $reaction = MessageReaction::create([
                'message_id' => $messageId,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
            broadcast(new \EchoChat\Events\ReactionUpdated($reaction, 'added'))->toOthers();
        }
    }

    public function formatContent(string $content): string
    {
        // HTMLタグが含まれているかチェック
        if ($content === strip_tags($content)) {
            // タグが含まれていない場合は通常通りエスケープと改行処理
            $escaped = e($content);
            $withBreaks = $escaped;
        } else {
            // HTMLタグが含まれている場合は、許可されたタグ以外を除去（XSS対策）
            // 許可するタグ: <b>, <i>, <u>, <s>, <a>, <ul>, <ol>, <li>, <code>, <pre>, <br>, <p>, <h1>, <h2>, <h3>
            $allowedTags = '<b><i><u><s><a><ul><ol><li><code><pre><br><p><h1><h2><h3>';
            $sanitized = strip_tags($content, $allowedTags);

            // 属性の除去（javascript: 等の除去のため、簡易的な処理）
            // より厳格な対策が必要な場合は HTML Purifier などの導入を推奨
            $sanitized = preg_replace('/on\w+="[^"]*"/i', '', $sanitized);
            $sanitized = preg_replace('/href="javascript:[^"]*"/i', 'href="#"', $sanitized);

            $withBreaks = $sanitized;
        }

        // @channel のハイライト
        $replacements = [];
        if (str_contains($withBreaks, '@channel')) {
            $placeholder = '___MENTION_CHANNEL___';
            $withBreaks = str_replace('@channel', $placeholder, $withBreaks);
            $replacements[$placeholder] = '<span class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-1 rounded font-medium">@channel</span>';
        }

        // メンションをハイライト
        // チャンネルメンバーの名前リストを取得（長い順にソートして部分一致を防ぐ）
        $names = $this->channel->members()
            ->with('user')
            ->get()
            ->map(fn ($m) => UserSupport::getName($m->user))
            ->filter()
            ->unique()
            ->sortByDesc(fn ($name) => strlen($name));

        foreach ($names as $name) {
            $mention = '@'.e($name);
            $replacement = '___MENTION_'.md5($name).'___';
            $withBreaks = str_replace($mention, $replacement, $withBreaks);
            $replacements[$replacement] = '<span class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-1 rounded font-medium">'.$mention.'</span>';
        }

        if (count($replacements) > 0) {
            foreach ($replacements as $placeholder => $html) {
                $withBreaks = str_replace($placeholder, $html, $withBreaks);
            }
        }

        return $withBreaks;
    }
}
