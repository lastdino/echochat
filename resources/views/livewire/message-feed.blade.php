<?php

use EchoChat\Models\Channel;
use Livewire\Volt\Component;

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
        ];
    }

    public function handleMessageSent()
    {
        $this->dispatch('message-sent')->to('chat');
    }

    public function updateSearch(string $search)
    {
        $this->search = $search;
    }

    public function with()
    {
        $query = $this->channel->messages()->with(['user', 'media', 'parent.user', 'reactions.user'])->oldest();

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
        }

        return [
            'groupedMessages' => $query->get()->groupBy(fn ($message) => $message->created_at->translatedFormat(config('echochat.date_format', 'n月j日 (D)'))),
        ];
    }

    public function replyTo(int $messageId)
    {
        $this->dispatch('setReplyTo', messageId: $messageId)->to('message-input');
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
}; ?>

<div class="p-4 space-y-4">
    @foreach($groupedMessages as $date => $messages)
        <div x-data="{ open: true }" class="space-y-4">
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
                    @php
                        $userName = \EchoChat\Support\UserSupport::getName($message->user);
                    @endphp

                    <div id="message-{{ $message->id }}" class="flex items-start gap-3 group/message transition-colors duration-500" wire:key="message-{{ $message->id }}">
                        <flux:avatar size="sm" :name="$userName" src="{{$message->user->getUserAvatar()}}"/>
                        <div class="min-w-0 flex-1">
                            @if($message->parent)
                                @php
                                    $parentUserName = \EchoChat\Support\UserSupport::getName($message->parent->user);
                                @endphp
                                <a href="#message-{{ $message->parent_id }}" class="flex items-center gap-1 text-xs text-zinc-500 mb-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors" @click.prevent="document.getElementById('message-{{ $message->parent_id }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); const el = document.getElementById('message-{{ $message->parent_id }}'); el.classList.add('bg-zinc-100', 'dark:bg-zinc-800'); setTimeout(() => el.classList.remove('bg-zinc-100', 'dark:bg-zinc-800'), 2000)">
                                    <flux:icon icon="arrow-uturn-left" class="w-3 h-3" />
                                    <span class="font-bold">{{ $parentUserName }}</span>
                                    <span class="truncate opacity-70">{{ $message->parent->content }}</span>
                                </a>
                            @endif
                            <div class="flex items-baseline gap-2">
                                <span class="font-bold dark:text-white">{{ $userName }}</span>
                                <span class="text-xs text-zinc-500">{{ $message->created_at->format('H:i') }}</span>
                                <button
                                    type="button"
                                    wire:click="replyTo({{ $message->id }})"
                                    class="hidden group-hover/message:inline-flex items-center text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors ml-2"
                                    title="返信"
                                >
                                    <flux:icon icon="arrow-uturn-left" class="w-3 h-3 mr-1" />
                                    返信
                                </button>
                            </div>
                            @if($message->content)
                                <div class="text-zinc-700 dark:text-zinc-300 break-words whitespace-pre-wrap">{!! $message->content !!}</div>
                            @endif

                            <div class="mt-2 flex flex-wrap gap-1 items-center">
                                @if($message->reactions->count() > 0)
                                    @foreach($message->reactions->groupBy('emoji') as $emoji => $reactions)
                                        @php
                                            $hasReacted = $reactions->contains('user_id', auth()->id());
                                            $userNames = $reactions->map(fn($r) => \EchoChat\Support\UserSupport::getName($r->user))->join(', ');
                                        @endphp
                                        <button
                                            type="button"
                                            wire:click="toggleReaction({{ $message->id }}, '{{ $emoji }}')"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs transition-colors {{ $hasReacted ? 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300' : 'bg-zinc-50 border-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}"
                                            title="{{ $userNames }}"
                                        >
                                            <span>{{ $emoji }}</span>
                                            <span class="font-medium">{{ $reactions->count() }}</span>
                                        </button>
                                    @endforeach
                                @endif

                                <flux:dropdown>
                                    <flux:button type="button" variant="subtle" size="xs" class="rounded-full px-1.5 py-0.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors text-sm" icon="smile-plus" icon:variant="outline" title="リアクションを追加" />

                                    <flux:menu class="min-w-32">
                                        <div class="p-2 grid grid-cols-6 gap-1">
                                            @foreach(['👍', '❤️', '😄', '😮', '😢', '🔥', '👏', '🎉', '🙌', '👀', '✅', '🚀'] as $emoji)
                                                <button
                                                    type="button"
                                                    wire:click="toggleReaction({{ $message->id }}, '{{ $emoji }}')"
                                                    class="p-1 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded text-lg flex items-center justify-center transition-colors"
                                                >
                                                    {{ $emoji }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                            @if($message->media->count() > 0)
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($message->media as $media)
                                        <div class="max-w-sm relative group/media">
                                            @php
                                                $url = route('echochat.attachments.show', ['media' => $media->uuid]);
                                            @endphp
                                            @if(str_starts_with($media->mime_type, 'image/'))
                                                <a href="{{ $url }}" target="_blank" class="block">
                                                    <img src="{{ $url }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 max-h-64 object-contain" alt="{{ $media->name }}" />
                                                </a>
                                            @else
                                                <a href="{{ $url }}" target="_blank" class="flex items-center gap-2 p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                                                    <flux:icon icon="paper-clip" class="w-4 h-4 text-zinc-500" />
                                                    <span class="text-sm dark:text-white truncate max-w-xs">{{ $media->file_name }}</span>
                                                    <span class="text-xs text-zinc-500">({{ $media->human_readable_size }})</span>
                                                </a>
                                            @endif

                                            @if($message->user_id === auth()->id())
                                                <button
                                                    type="button"
                                                    wire:click="deleteAttachment({{ $message->id }}, {{ $media->id }})"
                                                    wire:confirm="この添付ファイルを削除してもよろしいですか？"
                                                    class="absolute -top-2 -right-2 bg-white dark:bg-zinc-800 text-zinc-400 hover:text-red-500 rounded-full p-1 shadow-sm border border-zinc-200 dark:border-zinc-700 group-hover/media:block hidden transition-opacity"
                                                    title="削除"
                                                >
                                                    <flux:icon icon="x-mark" class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
