<?php

namespace EchoChat\Notifications;

use EchoChat\Models\Message;
use EchoChat\Support\UserSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MentionedInMessage extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Message $chatMessage, public bool $isChannelMention = false)
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

        if ($this->isChannelMention) {
            $title = "{$senderName}さんが{$channelName}の全員にメンションしました";
        } else {
            $title = "{$senderName}さんが{$channelName}であなたをメンションしました";
        }

        return [
            'message_id' => $this->chatMessage->id,
            'channel_id' => $this->chatMessage->channel_id,
            'workspace_id' => $this->chatMessage->channel->workspace_id,
            'sender_id' => $this->chatMessage->user_id,
            'sender_name' => $senderName,
            'content' => $this->chatMessage->content,
            'title' => $title,
            'is_channel_mention' => $this->isChannelMention,
            'timestamp' => now()->getTimestampMs(), // リアルタイム更新をトリガーするためのタイムスタンプ
        ];
    }

    public function broadcastType(): string
    {
        return 'NotificationSent';
    }
}
