<?php

namespace EchoChat\Livewire;

use EchoChat\Events\MessageSent;
use EchoChat\Models\Channel;
use EchoChat\Models\ChannelUser;
use EchoChat\Models\Message;
use EchoChat\Support\MessageSupport;
use EchoChat\Support\UserSupport;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class MessageInput extends Component
{
    use WithFileUploads;

    public Channel $channel;

    public string $content = '';

    public $attachments = [];

    public ?int $replyToId = null;

    public bool $isThreadInput = false;

    public ?Message $replyToMessage = null;

    public $mentionSearch = '';

    public $mentionResults = [];

    public int $mentionIndex = 0;

    protected $listeners = [
        'setReplyTo' => 'setReplyTo',
        'cancelReply' => 'cancelReply',
    ];

    public function mount(Channel $channel, ?int $replyToId = null): void
    {
        $this->channel = $channel;
        $this->replyToId = $replyToId;
        $this->isThreadInput = ! is_null($replyToId);
        if ($replyToId) {
            $this->replyToMessage = Message::with('user')->find($replyToId);
        }
    }

    public function setReplyTo($messageId): void
    {
        // 統合方針：返信ボタンはスレッドを開くようになったため、
        // このメソッドは（もし他から呼ばれても）スレッドを開くイベントをディスパッチするか、
        // 互換性のために残す。ただし、インライン返信は行わない。
        $message = Message::find($messageId);
        if ($message) {
            $parentId = $message->parent_id ?: $message->id;
            $this->dispatch('openThread', messageId: $parentId)->to(Chat::class);
        }
    }

    public function cancelReply(): void
    {
        $this->replyToId = null;
        $this->replyToMessage = null;
    }

    public function updatedMentionSearch(): void
    {
        $this->loadMentions();
    }

    public function loadMentions(): void
    {
        $members = [];
        if (empty($this->mentionSearch)) {
            $members = $this->channel->members()
                ->with('user')
                ->take(10)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->user_id,
                    'name' => UserSupport::getName($m->user),
                ])
                ->toArray();
        } else {
            $searchTerm = '%'.$this->mentionSearch.'%';
            $members = $this->channel->members()
                ->whereHas('user', function ($query) use ($searchTerm) {
                    $query->where('name', 'like', $searchTerm);
                })
                ->with('user')
                ->take(10)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->user_id,
                    'name' => UserSupport::getName($m->user),
                ])
                ->toArray();
        }

        // Add "channel" to results if it matches
        if (empty($this->mentionSearch) || str_contains('channel', strtolower($this->mentionSearch))) {
            array_unshift($members, [
                'id' => 'channel',
                'name' => 'channel',
            ]);
        }

        $this->mentionResults = $members;
        $this->mentionIndex = 0;
    }

    public function sendMessage(): void
    {
        $this->validate([
            'content' => 'required_without:attachments|string|nullable',
            'attachments.*' => 'file|max:10240', // 10MB max per file
        ]);

        $message = $this->channel->messages()->create([
            'user_id' => auth()->id(),
            'content' => $this->content ?? '',
            'parent_id' => $this->replyToId,
        ]);

        foreach ($this->attachments as $attachment) {
            $message->addMedia($attachment->getRealPath())
                ->usingFileName($attachment->getClientOriginalName())
                ->toMediaCollection('attachments');
        }

        ChannelUser::updateOrCreate(
            ['channel_id' => $this->channel->id, 'user_id' => auth()->id()],
            ['last_read_at' => now()]
        );

        broadcast(new MessageSent($message->load('media')))->toOthers();
        broadcast(new \EchoChat\Events\ChannelRead($this->channel, auth()->id()))->toOthers();

        // メンション通知の送信
        MessageSupport::notifyMentions($message);

        // スレッド参加者への通知
        MessageSupport::notifyThreadParticipants($message);

        $this->content = '';
        $this->attachments = [];

        if (! $this->isThreadInput) {
            $this->replyToId = null;
            $this->replyToMessage = null;
        }

        $this->dispatch('messageSent');
        $this->dispatch('channelRead', channelId: $this->channel->id);
    }

    public function removeAttachment($index): void
    {
        if (isset($this->attachments[$index])) {
            array_splice($this->attachments, $index, 1);
        }
    }

    public function render(): View
    {
        return view('echochat::pages.message-input');
    }
}
