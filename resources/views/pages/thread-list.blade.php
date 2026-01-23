<div class="flex flex-col h-full bg-white dark:bg-zinc-900">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
        <flux:heading size="lg">スレッド一覧</flux:heading>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if($this->threads->isEmpty())
            <div class="p-8 text-center text-zinc-500">
                参加しているスレッドはありません
            </div>
        @else
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($this->threads as $thread)
                    <div
                        wire:key="thread-{{ $thread['id'] }}"
                        class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors"
                        wire:click="selectThread('{{ $thread['channel_id'] }}', '{{ $thread['id'] }}')"
                    >
                        <div class="flex items-start gap-3">
                            <flux:avatar size="sm" :name="$thread['user_name']" :src="$thread['user_avatar']" />

                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline gap-2">
                                    <span class="font-bold text-sm truncate dark:text-white">{{ $thread['user_name'] }}</span>
                                    <span class="text-[10px] text-zinc-500 shrink-0">{{ $thread['latest_reply_at']->diffForHumans() }}</span>
                                </div>

                                <div class="text-xs text-zinc-500 mb-1">
                                    in <span class="font-medium text-zinc-700 dark:text-zinc-300">#{{ $thread['channel_name'] }}</span>
                                </div>

                                <div class="text-sm text-zinc-800 dark:text-zinc-200 line-clamp-2 mb-2">
                                    {{ $thread['content'] }}
                                </div>

                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" color="zinc" variant="subtle">
                                        {{ $thread['reply_count'] }} 件の返信
                                    </flux:badge>

                                    @if($thread['latest_reply_user_name'])
                                        <span class="text-[10px] text-zinc-400">
                                            最新: {{ $thread['latest_reply_user_name'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
