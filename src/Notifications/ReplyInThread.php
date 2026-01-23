<?php

namespace EchoChat\Notifications;

use EchoChat\Models\Message;
use EchoChat\Support\UserSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReplyInThread extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Message $chatMessage)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $senderName = UserSupport::getName($this->chatMessage->user);
        $channelName = $this->chatMessage->channel->is_dm ? 'ダイレクトメッセージ' : '#'.$this->chatMessage->channel->name;

        $title = "{$senderName}さんがスレッドに返信しました";

        return [
            'message_id' => $this->chatMessage->id,
            'channel_id' => $this->chatMessage->channel_id,
            'workspace_id' => $this->chatMessage->channel->workspace_id,
            'sender_id' => $this->chatMessage->user_id,
            'sender_name' => $senderName,
            'content' => $this->chatMessage->content,
            'title' => $title,
            'timestamp' => now()->getTimestampMs(),
        ];
    }

    public function broadcastType(): string
    {
        return 'NotificationSent';
    }
}
