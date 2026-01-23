<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Channel;
use EchoChat\Models\Message;
use EchoChat\Models\MessageReaction;
use Illuminate\View\View;
use Livewire\Component;

class MessageFeed extends Component
{
    public Channel $channel;

    public string $search = '';

    public ?string $lastReadAt = null;

    public function mount(Channel $channel): void
    {
        $this->channel = $channel;
        $this->lastReadAt = \EchoChat\Models\ChannelUser::where('channel_id', $channel->id)
            ->where('user_id', auth()->id())
            ->first()
            ?->last_read_at
            ?->toDateTimeString();
    }

    public function getListeners(): array
    {
        return [
            "echo-private:workspace.{$this->channel->workspace_id}.channel.{$this->channel->id},.EchoChat\\Events\\MessageSent" => 'handleIncomingMessage',
            "echo-private:workspace.{$this->channel->workspace_id}.channel.{$this->channel->id},.EchoChat\\Events\\ReactionUpdated" => '$refresh',
            'messageSent' => 'handleIncomingMessage',
            'searchMessages' => 'updateSearch',
            'scrollToMessage' => 'scrollToMessage',
        ];
    }

    public function scrollToMessage(int $messageId, array $ancestorIds = []): void
    {
        $this->dispatch('message-target-scrolled', messageId: $messageId, ancestorIds: $ancestorIds);
    }

    public function handleIncomingMessage(): void
    {
        // 自身の状態を更新するために必要なら $refresh
        $this->dispatch('$refresh');
    }

    public function updateSearch(string $search): void
    {
        $this->search = $search;
    }

    public function render(): View
    {
        $query = $this->channel->messages()
            ->with(['user', 'media', 'parent.user', 'reactions.user', 'replies.user'])
            ->oldest();

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
        } else {
            // 検索中でない場合はトップレベルのメッセージのみ取得
            $query->whereNull('parent_id');
        }

        $groupedMessages = $query->get()->groupBy(fn ($message) => $message->created_at->translatedFormat(config('echochat.date_format', 'n月j日 (D)')));

        return view('echochat::pages.message-feed', [
            'groupedMessages' => $groupedMessages,
            'lastReadAtDate' => $this->lastReadAt ? \Carbon\Carbon::parse($this->lastReadAt) : null,
        ]);
    }

    public function replyTo(int $messageId): void
    {
        $message = Message::find($messageId);
        if ($message) {
            $parentId = $message->parent_id ?: $message->id;
            $this->dispatch('openThread', messageId: $parentId)->to(Chat::class);
        }
    }

    public function deleteAttachment(int $messageId, int $mediaId): void
    {
        $message = Message::findOrFail($messageId);

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

    public function toggleReaction(int $messageId, string $emoji): void
    {
        $userId = auth()->id();
        $reaction = MessageReaction::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($reaction) {
            $reaction->delete();
            broadcast(new \EchoChat\Events\ReactionUpdated($reaction, 'removed'))->toOthers();
        } else {
            $reaction = MessageReaction::create([
                'message_id' => $messageId,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
            broadcast(new \EchoChat\Events\ReactionUpdated($reaction, 'added'))->toOthers();
        }
    }

    public function formatContent(string $content): string
    {
        return \EchoChat\Support\MessageSupport::formatContent($content, $this->channel);
    }
}
