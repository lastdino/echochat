<div
    x-data="{
        showSidebar: false,
        threadWidth: $persist(384).as('echochat_thread_width'),
        isResizing: false,
        showReopenButton: false,
        startWidth: 0,
        startX: 0,
        startResize(e) {
            this.isResizing = true;
            this.startWidth = this.threadWidth;
            this.startX = e.clientX;
            document.addEventListener('mousemove', this.handleMouseMove.bind(this));
            document.addEventListener('mouseup', this.stopResize.bind(this));
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        },
        handleMouseMove(e) {
            if (!this.isResizing) return;
            const delta = this.startX - e.clientX;
            const newWidth = Math.min(Math.max(this.startWidth + delta, 300), 800);
            this.threadWidth = newWidth;
        },
        stopResize() {
            this.isResizing = false;
            document.removeEventListener('mousemove', this.handleMouseMove);
            document.removeEventListener('mouseup', this.stopResize);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        }
    }"
    x-on:thread-opened.window="showReopenButton = false"
    class="flex h-[calc(100vh-theme(spacing.16))] lg:h-screen bg-white dark:bg-zinc-900 overflow-hidden -m-6 lg:-m-8 relative"
>
    <!-- Sidebar -->
    <div
        :class="{ 'translate-x-0': showSidebar, '-translate-x-full': !showSidebar }"
        class="fixed inset-y-0 left-0 z-40 w-64 bg-zinc-100 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700 transition-transform duration-300 lg:relative lg:translate-x-0 lg:flex-shrink-0"
    >
        <livewire:echochat-channel-list :workspace="$workspace" :activeChannel="$activeChannel" />
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
    <div class="flex-1 flex min-w-0">
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

                            <flux:button wire:click="extractImportantInfo" variant="subtle" size="sm" icon="user-circle" square title="自分宛ての重要情報を抽出" />

                            <flux:modal.trigger name="thread-list">
                                <flux:button variant="subtle" size="sm" icon="chat-bubble-left-right" square title="スレッド一覧" />
                            </flux:modal.trigger>

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

                            <flux:modal.trigger name="thread-list">
                                <flux:button variant="subtle" size="sm" icon="chat-bubble-left-right" square title="スレッド一覧" />
                            </flux:modal.trigger>

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
                    <livewire:echochat-activity-feed
                        :workspace="$workspace"
                        wire:key="activity-feed-{{ $workspace->id }}"
                    />
                </flux:modal>

                <flux:modal name="thread-list" variant="flyout" class="md:w-[400px]">
                    <livewire:echochat-thread-list
                        :workspace="$workspace"
                        :channel="$activeChannel"
                        wire:key="thread-list-{{ $workspace->id }}-{{ $activeChannel?->id }}"
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

                @if($importantInfo)
                    <div class="px-4 py-2 bg-indigo-50 dark:bg-indigo-950 border-b border-indigo-100 dark:border-indigo-900">
                        <div class="flex items-start gap-2">
                            <flux:icon icon="user-circle" class="w-4 h-4 text-indigo-500 mt-1 shrink-0" />
                            <div class="flex-1">
                                <h4 class="text-xs font-bold text-indigo-700 dark:text-indigo-300 mb-1 flex justify-between">
                                    あなた宛ての重要情報
                                    <button wire:click="$set('importantInfo', '')" class="text-zinc-400 hover:text-zinc-600">
                                        <flux:icon icon="x-mark" class="w-3 h-3" />
                                    </button>
                                </h4>
                                <div class="text-sm text-indigo-900 dark:text-indigo-100 whitespace-pre-wrap">{!! \Illuminate\Support\Str::markdown($importantInfo) !!}</div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($isExtracting)
                    <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-2 text-zinc-500 text-sm italic">
                            <flux:icon icon="user-circle" class="w-4 h-4 animate-pulse" />
                            重要情報を抽出中...
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
                        <livewire:echochat-message-feed :channel="$activeChannel" wire:key="feed-{{ $activeChannel->id }}"/>
                    </div>
                </div>

                <div class="p-4">
                    @if($activeChannel->isMember(auth()->id()))
                        <livewire:echochat-message-input :channel="$activeChannel" wire:key="input-{{ $activeChannel->id }}"/>
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
                        <livewire:echochat-invite-member :channel="$activeChannel"/>
                    </flux:modal>
                @endif

                @can('update', $activeChannel)
                    <flux:modal name="edit-channel-modal" class="md:w-[500px]">
                        <livewire:echochat-edit-channel :channel="$activeChannel" wire:key="edit-{{ $activeChannel->id }}"/>
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

    <!-- Thread Sidebar -->
    @if($threadParentMessageId)
        <div
            @mousedown="startResize"
            class="relative w-1 hover:w-1.5 bg-zinc-200 dark:bg-zinc-700 cursor-col-resize z-10 hover:bg-blue-400 dark:hover:bg-blue-600 transition-all group/resizer"
        >
            <div class="absolute inset-y-0 -left-1 -right-1 cursor-col-resize group-hover/resizer:bg-blue-400/20"></div>
        </div>

        <div
            :style="`width: ${threadWidth}px`"
            class="bg-white dark:bg-zinc-900 border-l border-zinc-200 dark:border-zinc-700 flex flex-col h-full"
            wire:key="thread-sidebar"
        >
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                <h3 class="font-bold dark:text-white">スレッド</h3>
                <button wire:click="closeThread" class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                    <flux:icon icon="x-mark" variant="mini" />
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                @php
                    $parentMessage = \EchoChat\Models\Message::with(['user', 'media', 'reactions.user', 'replies.user', 'replies.media', 'replies.reactions.user'])->find($threadParentMessageId);
                @endphp
                @if($parentMessage)
                    <div class="mb-6 pb-6 border-b border-zinc-100 dark:border-zinc-800">
                        <x-echochat-message-item :message="$parentMessage" />
                    </div>
                    <div class="space-y-6">
                        @foreach($parentMessage->replies as $reply)
                            <x-echochat-message-item :message="$reply" :is-reply="true" />
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                @if(config('echochat.flux_pro'))
                    <livewire:echochat-message-input-pro :channel="$activeChannel" :reply-to-id="$threadParentMessageId" wire:key="thread-input-pro-{{ $threadParentMessageId }}"/>
                @else
                    <livewire:echochat-message-input :channel="$activeChannel" :reply-to-id="$threadParentMessageId" wire:key="thread-input-{{ $threadParentMessageId }}"/>
                @endif
            </div>
        </div>
    @endif

    <!-- Reopen Thread Button Area -->
    @if(!$threadParentMessageId && $lastThreadParentMessageId)
        <div
            class="fixed inset-y-0 right-0 w-4 z-20 group/reopen flex items-center justify-center"
            @mouseenter="showReopenButton = true"
            @mouseleave="showReopenButton = false"
        >
            <button
                x-show="showReopenButton"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-4"
                wire:click="openThread({{ $lastThreadParentMessageId }})"
                class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-lg rounded-l-lg p-2 mr-0 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors group"
                title="スレッドを再表示"
            >
                <flux:icon icon="chat-bubble-left-right" variant="mini" class="text-zinc-500 group-hover:text-blue-500" />
            </button>
        </div>
    @endif
</div>
