<?php

use EchoChat\Models\Channel;
use Livewire\Volt\Component;

new class extends Component
{
    public Channel $channel;

    public function getListeners()
    {
        return [
            "echo-private:workspace.{$this->channel->workspace_id}.channel.{$this->channel->id},.EchoChat\\Events\\MessageSent" => '$refresh',
            'messageSent' => '$refresh',
        ];
    }

    public function with()
    {
        return [
            'messages' => $this->channel->messages()->with(['user', 'media', 'parent.user'])->oldest()->get(),
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
}; ?>

<div class="p-4 space-y-4">
    @foreach($messages as $message)
        <div id="message-{{ $message->id }}" class="flex items-start gap-3 group/message transition-colors duration-500" wire:key="message-{{ $message->id }}">
            <flux:avatar size="sm" :name="$message->user->name" />
            <div class="min-w-0 flex-1">
                @if($message->parent)
                    <a href="#message-{{ $message->parent_id }}" class="flex items-center gap-1 text-xs text-zinc-500 mb-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors" @click.prevent="document.getElementById('message-{{ $message->parent_id }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); const el = document.getElementById('message-{{ $message->parent_id }}'); el.classList.add('bg-zinc-100', 'dark:bg-zinc-800'); setTimeout(() => el.classList.remove('bg-zinc-100', 'dark:bg-zinc-800'), 2000)">
                        <flux:icon icon="arrow-uturn-left" class="w-3 h-3" />
                        <span class="font-bold">{{ $message->parent->user->name }}</span>
                        <span class="truncate opacity-70">{{ $message->parent->content }}</span>
                    </a>
                @endif
                <div class="flex items-baseline gap-2">
                    <span class="font-bold dark:text-white">{{ $message->user->name }}</span>
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
                    <p class="text-zinc-700 dark:text-zinc-300 break-words whitespace-pre-wrap">{{ $message->content }}</p>
                @endif

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
