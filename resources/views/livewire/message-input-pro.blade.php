<?php

use EchoChat\Events\MessageSent;
use EchoChat\Models\Channel;
use EchoChat\Models\ChannelUser;
use EchoChat\Models\Message;
use EchoChat\Notifications\MentionedInMessage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
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

    public function setReplyTo($messageId)
    {
        $this->replyToId = $messageId;
        $this->replyToMessage = Message::with('user')->find($messageId);
    }

    public function cancelReply()
    {
        $this->replyToId = null;
        $this->replyToMessage = null;
    }

    public function updatedMentionSearch()
    {
        $this->loadMentions();
    }

    public function loadMentions()
    {
        $members = [];
        if (empty($this->mentionSearch)) {
            $members = $this->channel->members()
                ->with('user')
                ->take(10)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->user_id,
                    'name' => \EchoChat\Support\UserSupport::getName($m->user),
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
                    'name' => \EchoChat\Support\UserSupport::getName($m->user),
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

    protected function notifyMentions(Message $message)
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
                'name' => \EchoChat\Support\UserSupport::getName($m->user),
            ])
            ->filter(fn ($m) => ! empty($m['name']))
            ->sortByDesc(fn ($m) => strlen($m['name']));

        $mentionedUserIds = [];

        foreach ($members as $member) {
            $name = $member['name'];
            $mention = '@'.$name;

            // メッセージ内に @名前 が含まれているか確認（単語境界を考慮）
            // 名前の中にスペースが含まれる可能性があるため、単純な \b は使えない場合がある
            // ここでは文字列置換の要領でチェック
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

    public function sendMessage()
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

    public function removeAttachment($index)
    {
        array_splice($this->attachments, $index, 1);
    }
}; ?>

<form wire:submit.prevent="sendMessage"
    x-data="{
        showMentions: false,
        mentionSearch: @entangle('mentionSearch'),
        mentionResults: @entangle('mentionResults'),
        mentionIndex: @entangle('mentionIndex'),
        cursorPos: 0,
        mentionStart: 0,
        lastContent: '',
        getPlainText(html) {
            if (!html) return '';
            const temp = document.createElement('div');
            temp.innerHTML = html;
            return temp.textContent || temp.innerText || '';
        },
        init() {
            this.$watch('$wire.content', (value) => {
                this.checkForMention(value);
            });
        },
        checkForMention(content) {
            const text = this.getPlainText(content || '');
            const pos = text.length;
            this.cursorPos = pos;

            const lastAt = text.lastIndexOf('@');
            if (lastAt !== -1 && (lastAt === 0 || /\s/.test(text[lastAt - 1]))) {
                const query = text.substring(lastAt + 1);
                if (!/\s/.test(query)) {
                    this.showMentions = true;
                    this.mentionStart = lastAt;
                    this.mentionSearch = query;
                    // 入力が進むたびにメンション候補を更新
                    $wire.loadMentions();
                    return;
                }
            }
            this.showMentions = false;
        },
        handleKeydown(e) {
            if (this.showMentions) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.mentionIndex = (this.mentionIndex + 1) % this.mentionResults.length;
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.mentionIndex = (this.mentionIndex - 1 + this.mentionResults.length) % this.mentionResults.length;
                } else if (e.key === 'Enter' || e.key === 'Tab') {
                    e.preventDefault();
                    this.selectMention(this.mentionResults[this.mentionIndex]);
                } else if (e.key === 'Escape') {
                    this.showMentions = false;
                }
            }
        },
        handleInput(e) {
            const content = $wire.get('content') || '';
            this.checkForMention(content);
        },
        triggerMention() {
            const editor = this.$el.querySelector('[data-flux-editor]');
            const currentContent = $wire.get('content') || '';
            const newContent = currentContent + '@';

            $wire.set('content', newContent);

            this.$nextTick(() => {
                if (editor) {
                    editor.focus();
                }
                this.showMentions = true;
                this.mentionStart = this.getPlainText(newContent).length - 1;
                this.cursorPos = this.getPlainText(newContent).length;
                this.mentionSearch = '';
                $wire.loadMentions();
            });
        },
        selectMention(member) {
            const text = this.getPlainText($wire.get('content') || '');
            const before = text.substring(0, this.mentionStart);
            const after = text.substring(this.cursorPos);
            const newContent = before + '@' + member.name + ' ' + after;

            $wire.set('content', newContent);
            this.showMentions = false;

            this.$nextTick(() => {
                const editor = this.$el.querySelector('[data-flux-editor]');
                if (editor) {
                    editor.focus();
                }
            });
        }
    }"
>
    <div class="relative bg-white dark:bg-zinc-800 rounded-lg border border-zinc-300 dark:border-zinc-700">
        <div
            x-show="showMentions"
            x-cloak
            class="absolute bottom-full left-0 mb-2 w-64 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl z-50 overflow-hidden"
            @click.away="showMentions = false"
        >
            <div class="p-2 text-xs font-bold text-zinc-500 border-b border-zinc-100 dark:border-zinc-700">メンバーをメンション</div>
            <div class="max-h-48 overflow-y-auto" x-show="mentionResults && mentionResults.length > 0">
                <template x-for="(member, index) in mentionResults" :key="member.id">
                    <button
                        type="button"
                        @click="selectMention(member)"
                        @mouseenter="mentionIndex = index"
                        :class="mentionIndex === index ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-zinc-700 dark:text-zinc-300'"
                        class="w-full text-left px-3 py-2 text-sm flex items-center gap-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors"
                    >
                        <span class="font-medium" x-text="member.name"></span>
                    </button>
                </template>
            </div>
            <div class="p-3 text-sm text-zinc-500 text-center" x-show="!mentionResults || mentionResults.length === 0">
                メンバーが見つかりません
            </div>
        </div>

        @if($replyToMessage)
            <div class="flex items-center justify-between mb-1 p-2 bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700 rounded-lg">
                <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 overflow-hidden">
                    <flux:icon icon="arrow-uturn-left" class="w-3 h-3 flex-shrink-0" />
                    <span class="font-bold flex-shrink-0">{{ $replyToMessage->user->name }}</span>
                    <span class="truncate">{{ strip_tags($replyToMessage->content) }}</span>
                </div>
                <button type="button" wire:click="cancelReply" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                    <flux:icon icon="x-mark" class="w-4 h-4" />
                </button>
            </div>
        @endif

        @if(count($attachments) > 0)
            <div class="flex flex-wrap gap-2 mb-1 p-2 border-b border-zinc-200 dark:border-zinc-700">
                @foreach($attachments as $index => $attachment)
                    <div class="relative group">
                        @if(str_starts_with($attachment->getMimeType(), 'image/'))
                            <img src="{{ $attachment->temporaryUrl() }}" class="h-20 w-20 object-cover rounded border border-zinc-200 dark:border-zinc-600" />
                        @else
                            <div class="h-20 w-20 flex flex-col items-center justify-center bg-zinc-100 dark:bg-zinc-700 rounded border border-zinc-200 dark:border-zinc-600 text-[10px] text-zinc-500 text-center p-1">
                                <flux:icon icon="paper-clip" class="w-4 h-4 mb-1" />
                                <span class="truncate w-full">{{ $attachment->getClientOriginalName() }}</span>
                            </div>
                        @endif
                        <button type="button" wire:click="removeAttachment({{ $index }})" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full p-0.5 group-hover:block hidden transition-opacity">
                            <flux:icon icon="x-mark" class="w-3 h-3" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

            <flux:composer wire:model="content" rows="3" label="メッセージ" label:sr-only placeholder="# {{ $channel->name }} へのメッセージ"
                           x-ref="textarea"
                           @input="handleInput"
                           @keydown="handleKeydown">
                <x-slot name="input">
                    <flux:editor variant="borderless" toolbar="bold italic bullet ordered | link | align"
                        @input.stop="handleInput"
                        @keydown.stop="handleKeydown"
                    />
                </x-slot>
                <x-slot name="actionsLeading">
                    <flux:button size="sm" icon="at-symbol" variant="subtle" @click="triggerMention"></flux:button>
                    <flux:modal.trigger name="file-upload-modal">
                        <flux:button size="sm" variant="subtle" icon="paper-clip" />
                    </flux:modal.trigger>
                    <flux:dropdown>
                        <flux:button size="sm" variant="subtle" icon="face-smile" icon:variant="outline"  />

                        <flux:menu class="w-64 p-2">
                            <div class="grid grid-cols-8 gap-1">
                                @foreach(['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾', '🤖'] as $emoji)
                                    <button type="button" @click="$wire.set('content', $wire.get('content') + '{{ $emoji }}')" class="p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded text-xl">
                                        {{ $emoji }}
                                    </button>
                                @endforeach
                            </div>
                        </flux:menu>
                    </flux:dropdown>
                </x-slot>
                <x-slot name="actionsTrailing">
                    <flux:button type="submit" size="sm" variant="primary" icon="paper-airplane" />
                </x-slot>
            </flux:composer>
            <flux:modal name="file-upload-modal" class="md:w-[400px]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">ファイルを添付</flux:heading>
                        <flux:subheading>送信するファイルを選択してください。</flux:subheading>
                    </div>
                    <flux:file-upload wire:model="attachments" multiple label="">
                        <flux:file-upload.dropzone
                            heading="ファイルをドロップするかクリックして参照してください"
                            text="最大 10MB"
                            with-progress
                            inline
                        />
                    </flux:file-upload>

                    <div class="mt-3 flex flex-col gap-2">
                        @foreach ($attachments as $index => $photo)
                            <flux:file-item
                                :heading="$photo->getClientOriginalName()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="removeAttachment({{ $index }})" aria-label="{{ 'Remove file: ' . $photo->getClientOriginalName() }}" />
                                </x-slot>
                            </flux:file-item>
                        @endforeach
                    </div>

                    <div class="flex">
                        <flux:spacer />
                        <flux:button x-on:click="Flux.modal('file-upload-modal').close()">閉じる</flux:button>
                    </div>
                </div>
            </flux:modal>
    </div>
</form>
