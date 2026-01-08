<?php

namespace EchoChat\Events;

use EchoChat\Models\Message;
use EchoChat\Support\UserSupport;
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
        $channels = [
            new PrivateChannel('workspace.'.$this->message->channel->workspace_id),
            new PrivateChannel('workspace.'.$this->message->channel->workspace_id.'.channel.'.$this->message->channel_id),
        ];

        // 各メンバーの個別チャンネルにも送る（サイドバーのバッジ更新用など）
        if ($this->message->channel->is_private || $this->message->channel->is_dm) {
            $memberIds = $this->message->channel->members->pluck('user_id')
                ->push($this->message->channel->creator_id)
                ->unique();
        } else {
            $workspace = $this->message->channel->workspace;
            $memberIds = $workspace->members->pluck('id')->push($workspace->owner_id)->unique();
        }

        foreach ($memberIds as $memberId) {
            $channels[] = new PrivateChannel('App.Models.User.'.$memberId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->message->content,
            'user_name' => UserSupport::getName($this->message->user),
            'channel_id' => $this->message->channel_id,
            'parent_id' => $this->message->parent_id,
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
