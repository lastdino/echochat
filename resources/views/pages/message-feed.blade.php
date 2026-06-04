<div class="p-4 space-y-4">
    @if(($hasMore ?? false) && trim($search) === '')
        <div
            wire:key="load-more-sentinel-{{ $channel->id }}"
            x-data="{
                loading: false,
                observer: null,
                init() {
                    const root = this.$el.closest('.overflow-y-auto');

                    this.observer = new IntersectionObserver((entries) => {
                        const entry = entries[0];

                        if (! entry || ! entry.isIntersecting || this.loading) {
                            return;
                        }

                        this.loading = true;

                        const previousHeight = root ? root.scrollHeight : 0;
                        const previousTop = root ? root.scrollTop : 0;

                        $wire.loadMore().then(() => {
                            this.$nextTick(() => {
                                if (root) {
                                    root.scrollTop = previousTop + (root.scrollHeight - previousHeight);
                                }
                                this.loading = false;
                            });
                        }).catch(() => {
                            this.loading = false;
                        });
                    }, { root: root, threshold: 0.1 });

                    this.observer.observe(this.$el);
                },
                destroy() {
                    if (this.observer) {
                        this.observer.disconnect();
                    }
                }
            }"
            class="flex justify-center py-3"
        >
            <flux:icon icon="arrow-path" class="w-5 h-5 animate-spin text-zinc-400" />
        </div>
    @endif

    @foreach($groupedMessages as $date => $messages)
        <div
            x-data="{ open: true }"
            x-on:expand-date-groups.window="if ($event.detail.messageId && document.getElementById('message-' + $event.detail.messageId)) open = true"
            class="space-y-4"
            wire:key="date-group-{{ $channel->id }}-{{ Str::slug($date) }}"
        >
            <div class="flex items-center gap-4 my-4 group/date cursor-pointer select-none" @click="open = !open">
                <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                <div class="flex items-center gap-2">
                    <flux:badge variant="neutral" size="sm" class="px-3 py-1 font-medium group-hover/date:bg-zinc-200 dark:group-hover/date:bg-zinc-700 transition-colors">
                        {{ $date }}
                    </flux:badge>
                    <flux:icon icon="chevron-down" class="w-4 h-4 text-zinc-400 transition-transform duration-200" x-bind:class="{ '-rotate-90': !open }" />
                </div>
                <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
            </div>

            <div x-show="open" x-collapse class="space-y-4">
                @php $hasShownUnreadLabel = false; @endphp
                @foreach($messages as $message)
                    @if($lastReadAtDate && $message->created_at->gt($lastReadAtDate) && ! $hasShownUnreadLabel && $message->user_id !== auth()->id())
                        <div class="flex items-center gap-4 my-2">
                            <div class="flex-1 border-t border-red-500/50"></div>
                            <span class="text-[10px] font-bold text-red-500 uppercase tracking-wider">ここから未読</span>
                            <div class="flex-1 border-t border-red-500/50"></div>
                        </div>
                        @php $hasShownUnreadLabel = true; @endphp
                    @endif
                    <x-echochat-message-item :message="$message" />
                @endforeach
            </div>
        </div>
    @endforeach
</div>
