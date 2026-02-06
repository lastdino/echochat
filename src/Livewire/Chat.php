<?php

namespace EchoChat\Livewire;

use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use EchoChat\Services\AIModelService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Chat extends Component
{
    public Workspace $workspace;

    public ?Channel $activeChannel = null;

    #[Url(as: 'channel', except: '')]
    public string $channel = '';

    #[Url(as: 'message', except: '')]
    public string $message = '';

    public ?string $messageId = null;

    public string $summary = '';

    public bool $isSummarizing = false;

    public string $importantInfo = '';

    public bool $isExtracting = false;

    public string $search = '';

    public bool $isSearching = false;

    #[Url(as: 'thread', except: '')]
    public string $thread = '';

    public ?int $threadParentMessageId = null;

    public ?int $lastThreadParentMessageId = null;

    public ?float $lastActivityClickId = null;

    public function mount(Workspace $workspace): void
    {
        Gate::authorize('view', $workspace);

        $this->workspace = $workspace;

        if ($this->channel !== '') {
            if (is_numeric($this->channel)) {
                $this->activeChannel = $workspace->channels()->find($this->channel);
            } else {
                $this->activeChannel = $workspace->channels()->where('name', $this->channel)->first();
            }
        }

        if (! $this->activeChannel) {
            $this->activeChannel = $workspace->channels()->first();
        }

        if ($this->activeChannel) {
            $this->activeChannel->load('members.user');
        }

        if ($this->message !== '') {
            $this->messageId = $this->message;
            $message = \EchoChat\Models\Message::find($this->message);
            $parentId = $message?->parent_id;

            if ($parentId) {
                $this->threadParentMessageId = $parentId;
                $this->thread = (string) $parentId;
                $this->lastThreadParentMessageId = $parentId;
            }

            $this->dispatch('scrollToMessage', messageId: $this->message, parentId: $parentId)->to('echochat-message-feed');
        } elseif ($this->thread !== '') {
            $this->threadParentMessageId = (int) $this->thread;
            $this->lastThreadParentMessageId = (int) $this->thread;
        }
    }

    public function getListeners(): array
    {
        $listeners = [
            'channelSelected' => 'selectChannel',
            'channelUpdated' => '$refresh',
            'memberAdded' => '$refresh',
            'setActivityMessage' => 'setActivityMessage',
            'openThread' => 'openThread',
            'closeThread' => 'closeThread',
        ];

        if ($this->activeChannel) {
            $listeners["echo-private:workspace.{$this->workspace->id}.channel.{$this->activeChannel->id},.EchoChat\\Events\\MessageSent"] = 'handleMessageSent';
        }

        return $listeners;
    }

    public function handleMessageSent($event = null): void
    {
        // MessageFeed コンポーネントに通知して、メッセージ一覧を更新させる
        $this->dispatch('messageSent')->to(MessageFeed::class);
    }

    public function openThread($messageId): void
    {
        if (is_array($messageId) && isset($messageId['messageId'])) {
            $messageId = $messageId['messageId'];
        }

        if (! $messageId) {
            return;
        }

        $message = \EchoChat\Models\Message::find($messageId);
        if ($message && $message->parent_id) {
            $this->threadParentMessageId = $message->parent_id;
        } else {
            $this->threadParentMessageId = $messageId;
        }
        $this->thread = (string) $this->threadParentMessageId;
        $this->lastThreadParentMessageId = $this->threadParentMessageId;
        $this->dispatch('thread-opened');
        // スレッドを開くときは検索やサマリーを閉じるなどの調整が必要な場合がある
    }

    public function closeThread(): void
    {
        $this->lastThreadParentMessageId = $this->threadParentMessageId;
        $this->threadParentMessageId = null;
        $this->thread = '';
    }

    public function formatContent(string $content): string
    {
        return \EchoChat\Support\MessageSupport::formatContent($content, $this->activeChannel);
    }

    public function toggleReaction(int $messageId, string $emoji): void
    {
        $userId = auth()->id();
        $reaction = \EchoChat\Models\MessageReaction::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($reaction) {
            $reaction->delete();
            broadcast(new \EchoChat\Events\ReactionUpdated($reaction, 'removed'))->toOthers();
        } else {
            $reaction = \EchoChat\Models\MessageReaction::create([
                'message_id' => $messageId,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
            broadcast(new \EchoChat\Events\ReactionUpdated($reaction, 'added'))->toOthers();
        }

        // 自身の表示も更新するために必要なら
        $this->dispatch('$refresh');
    }

    public function deleteAttachment(int $messageId, int $mediaId): void
    {
        $message = \EchoChat\Models\Message::findOrFail($messageId);

        if ($message->user_id !== auth()->id()) {
            return;
        }

        $media = $message->media()->find($mediaId);

        if ($media) {
            $media->delete();
            $this->dispatch('messageSent'); // フィードを更新

            broadcast(new \EchoChat\Events\MessageSent($message->load('media')))->toOthers();
        }
    }

    public function getAncestorIds($messageId): array
    {
        $ancestorIds = [];
        $message = \EchoChat\Models\Message::find($messageId);

        while ($message && $message->parent_id) {
            $ancestorIds[] = $message->parent_id;
            $message = $message->parent;
        }

        return array_reverse($ancestorIds);
    }

    public function setActivityMessage($messageId, $channelId = null, $clickId = null): void
    {
        $messageId = (string) $messageId;
        $channelId = $channelId ? (string) $channelId : (string) $this->channel;

        if ($clickId && $this->lastActivityClickId && $clickId < $this->lastActivityClickId) {
            return;
        }

        if ($clickId) {
            $this->lastActivityClickId = $clickId;
        }

        $ancestorIds = [];
        if ($messageId) {
            $ancestorIds = $this->getAncestorIds($messageId);
        }

        $this->messageId = $messageId;
        $this->channel = $channelId;
        $this->message = $messageId;
        $this->activeChannel = Channel::with('members.user')->find($channelId);
        $this->summary = '';
        $this->search = '';
        $this->isSearching = false;

        $message = \EchoChat\Models\Message::find($messageId);
        if ($message && $message->parent_id) {
            $this->threadParentMessageId = $message->parent_id;
            $this->thread = (string) $message->parent_id;
            $this->lastThreadParentMessageId = $message->parent_id;
        } else {
            $this->threadParentMessageId = null;
            $this->thread = '';
        }

        $this->dispatch('activity-message-set', messageId: $messageId, channelId: $channelId, ancestorIds: $ancestorIds);
        $this->dispatch('channelSelected', channelId: $channelId);
        $this->dispatch('scrollToMessage', messageId: $messageId, ancestorIds: $ancestorIds)->to('echochat-message-feed');
    }

    public function selectChannel($channelId): void
    {
        $channelId = (string) $channelId;

        $this->isSummarizing = false;
        $this->summary = '';
        $this->isExtracting = false;
        $this->importantInfo = '';

        if ($this->channel === $channelId && $this->message === '') {
            $this->dispatch('channelSelected', channelId: $channelId);

            return;
        }

        // チャンネル切り替え時は、最後のアクティビティクリックIDを更新して、
        // 直前のアクティビティイベントが遅れて届いても無視されるようにする
        $this->lastActivityClickId = now()->getTimestampMs();

        // チャンネルが変わった場合のみスレッドを閉じる
        if ($this->channel !== $channelId) {
            $this->threadParentMessageId = null;
            $this->thread = '';
        }

        $this->message = '';
        $this->messageId = null;
        $this->channel = $channelId;
        $this->activeChannel = Channel::with('members.user')->find($channelId);
        $this->summary = '';
        $this->search = '';
        $this->isSearching = false;

        $this->dispatch('scrollToBottom');
    }

    public function updatedSearch(): void
    {
        $this->dispatch('searchMessages', search: $this->search)->to('echochat-message-feed');
    }

    public function toggleSearch(): void
    {
        $this->isSearching = ! $this->isSearching;
        if (! $this->isSearching) {
            $this->search = '';
            $this->updatedSearch();
        }
    }

    public function summarize(AIModelService $aiService): void
    {
        if (! $this->activeChannel) {
            return;
        }

        $this->importantInfo = '';
        $this->isSummarizing = true;
        $this->summary = '';

        try {
            $this->summary = $aiService->summarizeChannel($this->activeChannel);
        } catch (\Exception $e) {
            $this->summary = 'エラーが発生しました: '.$e->getMessage();
        } finally {
            $this->isSummarizing = false;
        }
    }

    public function extractImportantInfo(AIModelService $aiService): void
    {
        if (! $this->activeChannel) {
            return;
        }

        $this->summary = '';
        $this->isExtracting = true;
        $this->importantInfo = '';

        try {
            $userName = \EchoChat\Support\UserSupport::getName(auth()->user());
            $this->importantInfo = $aiService->extractImportantInfo($this->activeChannel, $userName);
        } catch (\Exception $e) {
            $this->importantInfo = 'エラーが発生しました: '.$e->getMessage();
        } finally {
            $this->isExtracting = false;
        }
    }

    public function joinChannel(): void
    {
        if ($this->activeChannel && $this->activeChannel->canJoin(auth()->id())) {
            $this->activeChannel->members()->create([
                'user_id' => auth()->id(),
            ]);

            $this->activeChannel->messages()->create([
                'user_id' => auth()->id(),
                'content' => "# {$this->activeChannel->name} に参加しました",
            ]);

            $this->activeChannel->load('members.user');
            $this->dispatch('channelCreated'); // サイドバーを更新するため
            $this->dispatch('messageSent'); // メッセージフィードを更新するため
        }
    }

    public function render(): View
    {
        return view('echochat::pages.chat');
    }
}
