<?php

namespace EchoChat\Events;

use EchoChat\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.'.$this->message->channel->workspace_id.'.channel.'.$this->message->channel_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->message->content,
            'user_name' => $this->message->user->name,
            'channel_id' => $this->message->channel_id,
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
