<?php

use EchoChat\Events\MessageSent;
use EchoChat\Models\Channel;
use EchoChat\Models\ChannelUser;
use EchoChat\Models\Message;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Channel $channel;

    public string $content = '';

    public $attachments = [];

    public ?int $replyToId = null;

    public ?Message $replyToMessage = null;

    protected $listeners = [
        'setReplyTo' => 'setReplyTo',
    ];

    public function setReplyTo($messageId)
    {
        $this->replyToId = $messageId;
        $this->replyToMessage = Message::with('user')->find($messageId);
    }

    public function cancelReply()
    {
        $this->replyToId = null;
        $this->replyToMessage = null;
    }

    public function sendMessage()
    {
        $this->validate([
            'content' => 'required_without:attachments|string|nullable',
            'attachments.*' => 'file|max:10240', // 10MB max per file
        ]);

        $message = $this->channel->messages()->create([
            'user_id' => auth()->id(),
            'content' => $this->content ?? '',
            'parent_id' => $this->replyToId,
        ]);

        foreach ($this->attachments as $attachment) {
            $message->addMedia($attachment->getRealPath())
                ->usingFileName($attachment->getClientOriginalName())
                ->toMediaCollection('attachments');
        }

        ChannelUser::updateOrCreate(
            ['channel_id' => $this->channel->id, 'user_id' => auth()->id()],
            ['last_read_at' => now()]
        );

        broadcast(new MessageSent($message->load('media')))->toOthers();

        $this->content = '';
        $this->attachments = [];
        $this->replyToId = null;
        $this->replyToMessage = null;
        $this->dispatch('messageSent');
    }

    public function removeAttachment($index)
    {
        array_splice($this->attachments, $index, 1);
    }
}; ?>

<form wire:submit.prevent="sendMessage">
    <div class="relative bg-white dark:bg-zinc-800 rounded-lg border border-zinc-300 dark:border-zinc-700">
        @if($replyToMessage)
            <div class="flex items-center justify-between p-2 bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 rounded-t-lg">
                <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 overflow-hidden">
                    <flux:icon icon="arrow-uturn-left" class="w-3 h-3 flex-shrink-0" />
                    <span class="font-bold flex-shrink-0">{{ $replyToMessage->user->name }}</span>
                    <span class="truncate">{{ $replyToMessage->content }}</span>
                </div>
                <button type="button" wire:click="cancelReply" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                    <flux:icon icon="x-mark" class="w-4 h-4" />
                </button>
            </div>
        @endif

        @if(count($attachments) > 0)
            <div class="flex flex-wrap gap-2 p-2 border-b border-zinc-200 dark:border-zinc-700">
                @foreach($attachments as $index => $attachment)
                    <div class="relative group">
                        @if(str_starts_with($attachment->getMimeType(), 'image/'))
                            <img src="{{ $attachment->temporaryUrl() }}" class="h-20 w-20 object-cover rounded border border-zinc-200 dark:border-zinc-600" />
                        @else
                            <div class="h-20 w-20 flex flex-col items-center justify-center bg-zinc-100 dark:bg-zinc-700 rounded border border-zinc-200 dark:border-zinc-600 text-[10px] text-zinc-500 text-center p-1">
                                <flux:icon icon="paper-clip" class="w-4 h-4 mb-1" />
                                <span class="truncate w-full">{{ $attachment->getClientOriginalName() }}</span>
                            </div>
                        @endif
                        <button type="button" wire:click="removeAttachment({{ $index }})" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full p-0.5 group-hover:block hidden transition-opacity">
                            <flux:icon icon="x-mark" class="w-3 h-3" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <textarea
            wire:model="content"
            placeholder="# {{ $channel->name }} へのメッセージ"
            class="w-full bg-transparent border-none focus:ring-0 focus:outline-none dark:text-white resize-none p-3"
            rows="3"
        ></textarea>

        <div class="flex items-center justify-between p-2">
            <div class="flex items-center gap-2">
                <label class="cursor-pointer p-2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                    <input type="file" wire:model="attachments" multiple class="hidden" />
                    <flux:icon icon="paper-clip" class="w-5 h-5" />
                </label>

                <flux:dropdown>
                    <flux:button type="button" variant="subtle" icon="face-smile" icon:variant="outline" square class="p-2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300" />

                    <flux:menu class="w-64 p-2">
                        <div class="grid grid-cols-8 gap-1">
                            @foreach(['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾', '🤖'] as $emoji)
                                <button type="button" @click="$wire.set('content', $wire.get('content') + '{{ $emoji }}')" class="p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded text-xl">
                                    {{ $emoji }}
                                </button>
                            @endforeach
                        </div>
                    </flux:menu>
                </flux:dropdown>
            </div>
            <flux:button type="submit" size="sm" variant="primary" icon="paper-airplane" />
        </div>
    </div>
</form>
