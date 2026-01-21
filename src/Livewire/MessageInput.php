<?php

namespace EchoChat\Livewire;

use EchoChat\Events\MessageSent;
use EchoChat\Models\Channel;
use EchoChat\Models\ChannelUser;
use EchoChat\Models\Message;
use EchoChat\Notifications\MentionedInMessage;
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

    public ?Message $replyToMessage = null;

    public $mentionSearch = '';

    public $mentionResults = [];

    public int $mentionIndex = 0;

    protected $listeners = [
        'setReplyTo' => 'setReplyTo',
    ];

    public function setReplyTo($messageId): void
    {
        $this->replyToId = $messageId;
        $this->replyToMessage = Message::with('user')->find($messageId);
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

    protected function notifyMentions(Message $message): void
    {
        if (empty($message->content)) {
            return;
        }

        $content = $message->content;

        // Check for @channel
        $hasChannelMention = str_contains($content, '@channel');
        if ($hasChannelMention) {
            $content = str_replace('@channel', '___MENTION_DONE___', $content);
            $membersToNotify = $this->channel->members()
                ->where('user_id', '!=', auth()->id())
                ->with('user')
                ->get()
                ->map(fn ($m) => $m->user);

            foreach ($membersToNotify as $user) {
                $user->notify(new MentionedInMessage($message, true));
            }
        }

        // チャンネルメンバーの名前リストを取得（長い順にソートして部分一致を防ぐ）
        $members = $this->channel->members()
            ->with('user')
            ->get()
            ->map(fn ($m) => [
                'user' => $m->user,
                'name' => UserSupport::getName($m->user),
            ])
            ->filter(fn ($m) => ! empty($m['name']))
            ->sortByDesc(fn ($m) => strlen($m['name']));

        $mentionedUserIds = [];

        foreach ($members as $member) {
            $name = $member['name'];
            $mention = '@'.$name;

            // メッセージ内に @名前 が含まれているか確認（単語境界を考慮）
            if (str_contains($content, $mention)) {
                if ($member['user']->id !== auth()->id()) {
                    $mentionedUserIds[] = $member['user']->id;
                    // 他の名前と部分一致しないように、マッチした部分を置換して除外する
                    $content = str_replace($mention, '___MENTION_DONE___', $content);
                }
            }
        }

        if (empty($mentionedUserIds)) {
            return;
        }

        $mentionedUsers = \App\Models\User::whereIn('id', array_unique($mentionedUserIds))->get();

        foreach ($mentionedUsers as $user) {
            $user->notify(new MentionedInMessage($message));
        }
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
        $this->notifyMentions($message);

        $this->content = '';
        $this->attachments = [];
        $this->replyToId = null;
        $this->replyToMessage = null;
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
