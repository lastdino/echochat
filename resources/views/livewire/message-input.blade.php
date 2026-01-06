<?php

use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use EchoChat\Events\MessageSent;
use Livewire\Volt\Component;

new class extends Component {
    public Channel $channel;
    public string $content = '';

    public function sendMessage()
    {
        $this->validate(['content' => 'required|string']);

        $message = $this->channel->messages()->create([
            'user_id' => auth()->id(),
            'content' => $this->content,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        $this->content = '';
        $this->dispatch('messageSent');
    }
}; ?>

<form wire:submit.prevent="sendMessage">
    <div class="relative">
        <textarea
            wire:model="content"
            wire:keydown.enter.prevent="sendMessage"
            placeholder="# {{ $channel->name }} へのメッセージ"
            class="w-full rounded-lg border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:ring-blue-500 focus:border-blue-500 resize-none p-3"
            rows="3"
        ></textarea>
        <div class="absolute bottom-2 right-2">
            <button type="submit" class="p-2 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                </svg>
            </button>
        </div>
    </div>
</form>
