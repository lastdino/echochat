<?php

use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use Livewire\Volt\Component;

new class extends Component {
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
            'messages' => $this->channel->messages()->with('user')->oldest()->get(),
        ];
    }
}; ?>

<div class="p-4 space-y-4">
    @foreach($messages as $message)
        <div class="flex items-start gap-3" wire:key="message-{{ $message->id }}">
            <div class="w-10 h-10 rounded bg-zinc-200 dark:bg-zinc-700 flex-shrink-0 flex items-center justify-center font-bold text-zinc-500">
                {{ substr($message->user->name, 0, 1) }}
            </div>
            <div class="min-w-0">
                <div class="flex items-baseline gap-2">
                    <span class="font-bold dark:text-white">{{ $message->user->name }}</span>
                    <span class="text-xs text-zinc-500">{{ $message->created_at->format('H:i') }}</span>
                </div>
                <p class="text-zinc-700 dark:text-zinc-300 break-words">{{ $message->content }}</p>
            </div>
        </div>
    @endforeach
    <div x-init="$el.scrollIntoView()"></div>
</div>
