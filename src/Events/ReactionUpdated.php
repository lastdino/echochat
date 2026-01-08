<?php

namespace EchoChat\Events;

use EchoChat\Models\MessageReaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MessageReaction $reaction, public string $action = 'added') {}

    public function broadcastOn(): array
    {
        $message = $this->reaction->message;

        return [
            new PrivateChannel('workspace.'.$message->channel->workspace_id.'.channel.'.$message->channel_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->reaction->message_id,
            'user_id' => $this->reaction->user_id,
            'emoji' => $this->reaction->emoji,
            'action' => $this->action,
        ];
    }
}
