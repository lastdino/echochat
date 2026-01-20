<?php

use EchoChat\Models\Channel;
use Livewire\Component;

new class extends Component
{
    public Channel $channel;

    public string $search = '';

    public function getListeners()
    {
        return [
            "echo-private:workspace.{$this->channel->workspace_id}.channel.{$this->channel->id},.EchoChat\\Events\\MessageSent" => 'handleMessageSent',
            "echo-private:workspace.{$this->channel->workspace_id}.channel.{$this->channel->id},.EchoChat\\Events\\ReactionUpdated" => '$refresh',
            'messageSent' => 'handleMessageSent',
            'searchMessages' => 'updateSearch',
            'scrollToMessage' => 'scrollToMessage',
        ];
    }

    public function scrollToMessage(int $messageId, array $ancestorIds = [])
    {
        $this->dispatch('message-target-scrolled', messageId: $messageId, ancestorIds: $ancestorIds);
    }

    public function handleMessageSent()
    {
        $this->dispatch('message-sent')->to('echochat::chat');
    }

    public function updateSearch(string $search)
    {
        $this->search = $search;
    }

    public function with()
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

        return [
            'groupedMessages' => $query->get()->groupBy(fn ($message) => $message->created_at->translatedFormat(config('echochat.date_format', 'n月j日 (D)'))),
        ];
    }

    public function replyTo(int $messageId)
    {
        $this->dispatch('setReplyTo', messageId: $messageId);
    }

    public function deleteAttachment(int $messageId, int $mediaId)
    {
        $message = \EchoChat\Models\Message::findOrFail($messageId);

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

    public function toggleReaction(int $messageId, string $emoji)
    {
        $userId = auth()->id();
        $reaction = \EchoChat\Models\MessageReaction::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($reaction) {
            $reaction->delete();
            broadcast(new \EchoChat\Events\ReactionUpdated($reaction, 'removed'))->toOthers();
        } else {
            $reaction = \EchoChat\Models\MessageReaction::create([
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
            $withBreaks = nl2br($escaped);
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
            ->map(fn ($m) => \EchoChat\Support\UserSupport::getName($m->user))
            ->filter()
            ->unique()
            ->sortByDesc(fn ($name) => strlen($name));

        foreach ($names as $name) {
            $mention = '@'.e($name);
            $replacement = '___MENTION_'.md5($name).'___';
            $withBreaks = str_replace($mention, $replacement, $withBreaks);
            $replacements[$replacement] = '<span class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 px-1 rounded font-medium">'.$mention.'</span>';
        }

        if (isset($replacements)) {
            foreach ($replacements as $placeholder => $html) {
                $withBreaks = str_replace($placeholder, $html, $withBreaks);
            }
        }

        return $withBreaks;
    }
}; ?>

<div class="p-4 space-y-4">
    @foreach($groupedMessages as $date => $messages)
        <div
            x-data="{ open: true }"
            x-on:expand-date-groups.window="if ($event.detail.messageId && document.getElementById('message-' + $event.detail.messageId)) open = true"
            class="space-y-4"
            wire:key="date-group-{{ $channel->id }}-{{ Str::slug($date) }}"
        >
            <div class="flex items-center gap-4 my-4 group/date cursor-pointer select-none" @click="open = !open">
                <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                <div class="flex items-center gap-2">
                    <flux:badge variant="neutral" size="sm" class="px-3 py-1 font-medium group-hover/date:bg-zinc-200 dark:group-hover/date:bg-zinc-700 transition-colors">
                        {{ $date }}
                    </flux:badge>
                    <flux:icon icon="chevron-down" class="w-4 h-4 text-zinc-400 transition-transform duration-200" x-bind:class="{ '-rotate-90': !open }" />
                </div>
                <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
            </div>

            <div x-show="open" x-collapse class="space-y-4">
                @foreach($messages as $message)
                    <x-echochat::message-item :message="$message" />
                @endforeach
            </div>
        </div>
    @endforeach
</div>
