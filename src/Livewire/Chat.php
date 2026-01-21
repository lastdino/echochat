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

    public string $search = '';

    public bool $isSearching = false;

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
            $parentId = \EchoChat\Models\Message::find($this->message)?->parent_id;
            $this->dispatch('scrollToMessage', messageId: $this->message, parentId: $parentId)->to('echochat-message-feed');
        }
    }

    protected $listeners = [
        'channelSelected' => 'selectChannel',
        'channelUpdated' => '$refresh',
        'memberAdded' => '$refresh',
        'setActivityMessage' => 'setActivityMessage',
    ];

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

        $this->dispatch('activity-message-set', messageId: $messageId, channelId: $channelId, ancestorIds: $ancestorIds);
        $this->dispatch('channelSelected', channelId: $channelId);
        $this->dispatch('scrollToMessage', messageId: $messageId, ancestorIds: $ancestorIds)->to('echochat-message-feed');
    }

    public function selectChannel($channelId): void
    {
        $channelId = (string) $channelId;

        if ($this->channel === $channelId && $this->message === '') {
            $this->dispatch('channelSelected', channelId: $channelId);

            return;
        }

        // チャンネル切り替え時は、最後のアクティビティクリックIDを更新して、
        // 直前のアクティビティイベントが遅れて届いても無視されるようにする
        $this->lastActivityClickId = now()->getTimestampMs();

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
