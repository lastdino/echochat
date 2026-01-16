<?php

use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use EchoChat\Services\AIModelService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
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

    public function mount(Workspace $workspace)
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
            $this->dispatch('scrollToMessage', messageId: $this->message, parentId: $parentId)->to('echochat::message-feed');
        }
    }

    protected $listeners = [
        'channelSelected' => 'selectChannel',
        'channelUpdated' => '$refresh',
        'memberAdded' => '$refresh',
        'setActivityMessage' => 'setActivityMessage',
    ];

    public function getAncestorIds($messageId)
    {
        $ancestorIds = [];
        $message = \EchoChat\Models\Message::find($messageId);

        while ($message && $message->parent_id) {
            $ancestorIds[] = $message->parent_id;
            $message = $message->parent;
        }

        return array_reverse($ancestorIds);
    }

    public function setActivityMessage($messageId, $channelId = null, $clickId = null)
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
        $this->dispatch('scrollToMessage', messageId: $messageId, ancestorIds: $ancestorIds)->to('echochat::message-feed');
    }

    public function selectChannel($channelId)
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

    public function updatedSearch()
    {
        $this->dispatch('searchMessages', search: $this->search)->to('echochat::message-feed');
    }

    public function toggleSearch()
    {
        $this->isSearching = ! $this->isSearching;
        if (! $this->isSearching) {
            $this->search = '';
            $this->updatedSearch();
        }
    }

    public function summarize(AIModelService $aiService)
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

    public function joinChannel()
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
}; ?>

<div
    x-data="{ showSidebar: false }"
    class="flex h-[calc(100vh-theme(spacing.16))] lg:h-screen bg-white dark:bg-zinc-900 overflow-hidden -m-6 lg:-m-8 relative"
>
    <!-- Sidebar -->
    <div
        :class="{ 'translate-x-0': showSidebar, '-translate-x-full': !showSidebar }"
        class="fixed inset-y-0 left-0 z-40 w-64 bg-zinc-100 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700 transition-transform duration-300 lg:relative lg:translate-x-0 lg:flex-shrink-0"
    >
        <livewire:echochat::channel-list :workspace="$workspace" :activeChannel="$activeChannel" />
    </div>

    <!-- Backdrop for mobile -->
    <div
        x-show="showSidebar"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="showSidebar = false"
        x-cloak
        class="fixed inset-0 z-30 bg-black/50 lg:hidden"
    ></div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-w-0">
        @if($activeChannel)
            <div class="flex-1 flex flex-col overflow-hidden" wire:key="active-channel-{{ $activeChannel->id }}">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Sidebar toggle for mobile -->
                        <button
                            @click="showSidebar = true"
                            class="lg:hidden p-1 -ml-1 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                        >
                            <flux:icon icon="bars-3" variant="mini" />
                        </button>

                        <div class="flex flex-col min-w-0">
                            <h2 class="text-lg font-bold dark:text-white flex items-center gap-2 group">
                                <flux:icon :icon="$activeChannel->displayIcon" class="inline-block w-4 h-4"/>
                                <span class="truncate">{{ $activeChannel->displayName }}</span>

                                @can('update', $activeChannel)
                                    <flux:modal.trigger name="edit-channel-modal">
                                        <flux:button variant="subtle" size="sm" icon="pencil-square" square class="ml-1 opacity-0 group-hover:opacity-100 transition-opacity"/>
                                    </flux:modal.trigger>
                                @endcan
                            </h2>

                            @if($activeChannel->description)
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 truncate">
                                    {{ $activeChannel->description }}
                                </p>
                            @endif
                        </div>
                    </div>

                    @if(! $activeChannel->is_dm)
                        <div class="flex items-center gap-2"
                             title="{{ $activeChannel->members->map(fn($m) => $m->user->name)->implode(', ') }}">
                            <flux:avatar.group>
                                @foreach($activeChannel->members->take(5) as $member)
                                    <flux:avatar size="xs" :name="$member->user->name" src="{{$member->user->getUserAvatar()}}"/>
                                @endforeach

                                @if($activeChannel->members->count() > 5)
                                    <flux:avatar size="xs">+{{ $activeChannel->members->count() - 5 }}</flux:avatar>
                                @endif
                            </flux:avatar.group>

                            @if($activeChannel->is_private)
                                <flux:modal.trigger name="invite-member-modal">
                                    <flux:button variant="subtle" size="sm" icon="user-plus" square/>
                                </flux:modal.trigger>
                            @endif

                            <flux:button wire:click="toggleSearch" variant="subtle" size="sm" icon="magnifying-glass" square title="検索" :class="$isSearching ? 'text-blue-600' : ''" />

                            <flux:button wire:click="summarize" variant="subtle" size="sm" icon="sparkles" square title="AIで要約" />

                            <flux:modal.trigger name="activity-feed">
                                <div class="relative">
                                    <flux:button variant="subtle" size="sm" icon="bell" square title="アクティビティ" />
                                    <div
                                        x-data="{ show: false }"
                                        x-show="show"
                                        x-on:activity-updated.window="show = true"
                                        x-on:click.window="if (window.Flux && Flux.modal('activity-feed').open) show = false"
                                        class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-white dark:border-zinc-900"
                                        style="display: none;"
                                    ></div>
                                </div>
                            </flux:modal.trigger>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <flux:button wire:click="toggleSearch" variant="subtle" size="sm" icon="magnifying-glass" square title="検索" :class="$isSearching ? 'text-blue-600' : ''" />

                            <flux:modal.trigger name="activity-feed">
                                <div class="relative">
                                    <flux:button variant="subtle" size="sm" icon="bell" square title="アクティビティ" />
                                    <div
                                        x-data="{ show: false }"
                                        x-show="show"
                                        x-on:activity-updated.window="show = true"
                                        x-on:click.window="if (window.Flux && Flux.modal('activity-feed').open) show = false"
                                        class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full border border-white dark:border-zinc-900"
                                        style="display: none;"
                                    ></div>
                                </div>
                            </flux:modal.trigger>
                        </div>
                    @endif
                </div>

                <flux:modal name="activity-feed" variant="flyout" class="md:w-[400px]">
                    <livewire:echochat::activity-feed
                        :workspace="$workspace"
                        wire:key="activity-feed-{{ $workspace->id }}"
                    />
                </flux:modal>

                @if($isSearching)
                    <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700" wire:key="search-bar">
                        <flux:input
                            x-init="$el.querySelector('input')?.focus()"
                            wire:model.live.debounce.300ms="search"
                            placeholder="メッセージを検索..."
                            icon="magnifying-glass"
                            size="sm"
                        />
                    </div>
                @endif

                @if($summary)
                    <div class="px-4 py-2 bg-indigo-50 dark:bg-indigo-950 border-b border-indigo-100 dark:border-indigo-900">
                        <div class="flex items-start gap-2">
                            <flux:icon icon="sparkles" class="w-4 h-4 text-indigo-500 mt-1 shrink-0" />
                            <div class="flex-1">
                                <h4 class="text-xs font-bold text-indigo-700 dark:text-indigo-300 mb-1 flex justify-between">
                                    AIによる要約
                                    <button wire:click="$set('summary', '')" class="text-zinc-400 hover:text-zinc-600">
                                        <flux:icon icon="x-mark" class="w-3 h-3" />
                                    </button>
                                </h4>
                                <p class="text-sm text-indigo-900 dark:text-indigo-100 whitespace-pre-wrap">{{ $summary }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($isSummarizing)
                    <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-2 text-zinc-500 text-sm italic">
                            <flux:icon icon="sparkles" class="w-4 h-4 animate-pulse" />
                            要約を作成中...
                        </div>
                    </div>
                @endif

                <div
                    class="flex-1 overflow-y-auto flex flex-col"
                    x-data="{
                        scrollToBottom() {
                            this.$el.scrollTop = this.$el.scrollHeight;
                        },
                        scrollToMessage(messageId, ancestorIds = [], retryCount = 0) {
                            if (ancestorIds.length > 0) {
                                window.dispatchEvent(new CustomEvent('expand-replies', { detail: { messageId: messageId, ancestorIds: ancestorIds } }));
                                window.dispatchEvent(new CustomEvent('expand-date-groups', { detail: { messageId: messageId } }));
                            } else {
                                window.dispatchEvent(new CustomEvent('expand-date-groups', { detail: { messageId: messageId } }));
                            }

                            const tryScroll = () => {
                                const el = document.getElementById('message-' + messageId);
                                if (el) {
                                    // 要素が見つかったら日付グループを展開（念のため）
                                    window.dispatchEvent(new CustomEvent('expand-date-groups', { detail: { messageId: messageId } }));
                                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    el.classList.add('bg-indigo-50', 'dark:bg-indigo-900/20');
                                    setTimeout(() => el.classList.remove('bg-indigo-50', 'dark:bg-indigo-900/20'), 2000);
                                } else if (retryCount < 10) {
                                    // 要素が見つからない場合、少し待ってから再試行（Livewireのレンダリング待ち）
                                    setTimeout(() => this.scrollToMessage(messageId, ancestorIds, retryCount + 1), 150);
                                }
                            };

                            if (ancestorIds.length > 0) {
                                // スレッド展開のアニメーション待ち
                                setTimeout(tryScroll, 400);
                            } else {
                                this.$nextTick(tryScroll);
                            }
                        }
                    }"
                    x-init="
                        scrollToBottom();
                        Livewire.on('messageSent', () => { $nextTick(() => scrollToBottom()) });
                        Livewire.on('channelSelected', () => { $nextTick(() => scrollToBottom()) });
                        Livewire.on('scrollToBottom', () => { $nextTick(() => scrollToBottom()) });
                        Livewire.on('activity-message-set', (data) => { if (data.messageId) { scrollToMessage(data.messageId, data.ancestorIds || []) } });
                        Livewire.on('message-target-scrolled', (data) => { scrollToMessage(data.messageId, data.ancestorIds || []) });

                        // 初回レンダリング時、URLにmessageパラメータがあればスクロール実行
                        $nextTick(() => {
                            const urlParams = new URLSearchParams(window.location.search);
                            const msgId = urlParams.get('message');
                            if (msgId) {
                                // Livewireコンポーネントから祖先IDを取得するか、リトライに任せる
                                if ($wire.messageId == msgId) {
                                    $wire.call('getAncestorIds', msgId).then(ancestorIds => {
                                        scrollToMessage(msgId, ancestorIds);
                                    });
                                }
                            }
                        });

                        Livewire.on('url-updated', () => {
                            $nextTick(() => {
                                // 画面を強制的にリフレッシュするためのダミー処理
                                // または、Livewireの内部状態とURLが同期されるのを確実にする
                            });
                        });

                        const observer = new MutationObserver(() => {
                            // Only scroll to bottom if we are not scrolling to a specific message
                            // and the user is already near the bottom
                            if ($el.scrollHeight - $el.scrollTop - $el.clientHeight < 150) {
                                scrollToBottom();
                            }
                        });
                        if ($el) {
                            observer.observe($el, { childList: true, subtree: true });
                        }
                    "
                    @message-sent.window="$nextTick(() => scrollToBottom())"
                    @channel-selected.window="$nextTick(() => scrollToBottom())"
                >
                    <div class="flex-1"></div>
                    <div>
                        <livewire:echochat::message-feed :channel="$activeChannel" wire:key="feed-{{ $activeChannel->id }}"/>
                    </div>
                </div>

                <div class="p-4">
                    @if($activeChannel->isMember(auth()->id()))
                        @if(config('echochat.flux_pro'))
                            <livewire:echochat::message-input-pro :channel="$activeChannel" wire:key="input-pro-{{ $activeChannel->id }}"/>
                        @else
                            <livewire:echochat::message-input :channel="$activeChannel" wire:key="input-{{ $activeChannel->id }}"/>
                        @endif
                    @elseif($activeChannel->canJoin(auth()->id()))
                        <div
                            wire:key="join-{{ $activeChannel->id }}"
                            class="bg-zinc-50 dark:bg-zinc-800 p-8 rounded-lg border border-zinc-200 dark:border-zinc-700 text-center">
                            <h3 class="text-zinc-900 dark:text-white font-bold mb-2"># {{ $activeChannel->name }}
                                に参加しますか？</h3>
                            <p class="text-zinc-500 dark:text-zinc-400 mb-6">
                                参加すると、このチャンネルでメッセージを送信できるようになります。</p>
                            <flux:button wire:click="joinChannel" variant="primary">チャンネルに参加する</flux:button>
                        </div>
                    @else
                        <div
                            wire:key="restricted-{{ $activeChannel->id }}"
                            class="bg-zinc-50 dark:bg-zinc-800 p-8 rounded-lg border border-zinc-200 dark:border-zinc-700 text-center text-zinc-500">
                            このプライベートチャンネルを閲覧する権限がありません。
                        </div>
                    @endif
                </div>

                @if($activeChannel->is_private)
                    <flux:modal name="invite-member-modal" class="md:w-[500px]">
                        <livewire:echochat::invite-member :channel="$activeChannel"/>
                    </flux:modal>
                @endif

                @can('update', $activeChannel)
                    <flux:modal name="edit-channel-modal" class="md:w-[500px]">
                        <livewire:echochat::edit-channel :channel="$activeChannel" wire:key="edit-{{ $activeChannel->id }}"/>
                    </flux:modal>
                @endcan
            </div>
        @else
            <div class="flex-1 flex items-center justify-center" wire:key="no-active-channel">
                <p class="text-zinc-500">チャンネルを選択してください</p>
            </div>
        @endif
    </div>
</div>
