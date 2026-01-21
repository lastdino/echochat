<div class="flex flex-col h-full bg-white dark:bg-zinc-900">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex justify-between items-center">
            <flux:heading size="lg">アクティビティ</flux:heading>

            <flux:modal.trigger name="activity-settings">
                <flux:button variant="subtle" size="sm" icon="cog-6-tooth" square title="設定" />
            </flux:modal.trigger>
        </div>

        <flux:modal name="activity-settings" class="md:w-[400px]">
            <div class="space-y-6">
                <flux:heading size="lg">アクティビティ設定</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>表示範囲</flux:label>
                        <div class="flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                            <button
                                wire:click="$set('showAllWorkspaces', false)"
                                class="flex-1 py-1 text-xs font-medium rounded-md transition-colors {{ !$showAllWorkspaces ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                            >
                                現在のワークスペース
                            </button>
                            <button
                                wire:click="$set('showAllWorkspaces', true)"
                                class="flex-1 py-1 text-xs font-medium rounded-md transition-colors {{ $showAllWorkspaces ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                            >
                                すべてのワークスペース
                            </button>
                        </div>
                    </flux:field>

                    <flux:field>
                        <flux:label>フィルター</flux:label>
                        <div class="flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                            <button
                                wire:click="$set('onlyUnread', false)"
                                class="flex-1 py-1 text-xs font-medium rounded-md transition-colors {{ !$onlyUnread ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                            >
                                すべて
                            </button>
                            <button
                                wire:click="$set('onlyUnread', true)"
                                class="flex-1 py-1 text-xs font-medium rounded-md transition-colors {{ $onlyUnread ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                            >
                                未読のみ
                            </button>
                        </div>
                    </flux:field>
                </div>

                <div class="flex">
                    <flux:spacer />
                    <flux:button x-on:click="Flux.modal('activity-settings').close()" variant="primary">閉じる</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if($this->activities->isEmpty())
            <div class="p-8 text-center text-zinc-500">
                アクティビティはありません
            </div>
        @else
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($this->activities as $activity)
                    <div
                        wire:key="activity-{{ $activity['type'] }}-{{ $activity['id'] }}"
                        class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors"
                        wire:click.stop="selectActivity('{{ $activity['channel_id'] }}', '{{ $activity['id'] }}', '{{ $activity['notification_id'] ?? '' }}', '{{ $activity['workspace_slug'] ?? '' }}')"
                    >
                        <div class="flex items-start gap-3">
                            <div class="relative shrink-0">
                                <flux:avatar size="sm" :name="$activity['user_name']" :src="$activity['user_avatar']" />
                                <div class="absolute -bottom-1 -right-1 p-0.5 rounded-full bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                                    @if($activity['type'] === 'mention')
                                        <flux:icon icon="at-symbol" variant="mini" class="w-3 h-3 text-red-500" />
                                    @elseif($activity['type'] === 'reply')
                                        <flux:icon icon="arrow-uturn-left" variant="mini" class="w-3 h-3 text-blue-500" />
                                    @elseif($activity['type'] === 'dm')
                                        <flux:icon icon="chat-bubble-left-right" variant="mini" class="w-3 h-3 text-green-500" />
                                    @endif
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline gap-2">
                                    <span class="font-bold text-sm truncate dark:text-white">{{ $activity['user_name'] }}</span>
                                    <span class="text-[10px] text-zinc-500 shrink-0">{{ $activity['created_at']->diffForHumans() }}</span>
                                </div>

                                <div class="text-xs text-zinc-500 mb-1">
                                    @if($activity['type'] === 'mention')
                                        あなたをメンションしました
                                    @elseif($activity['type'] === 'reply')
                                        あなたのメッセージに返信しました
                                    @elseif($activity['type'] === 'dm')
                                        ダイレクトメッセージを送信しました
                                    @endif
                                    in <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $activity['channel_name'] }}</span>
                                </div>

                                @if($showAllWorkspaces && isset($activity['workspace_name']))
                                    <div class="flex items-center gap-1 text-[10px] text-zinc-400 mb-2">
                                        <flux:icon icon="briefcase" variant="mini" class="w-3 h-3" />
                                        <span>{{ $activity['workspace_name'] }}</span>
                                    </div>
                                @endif

                                @if($activity['type'] === 'reply' && isset($activity['parent_content']))
                                    <div class="bg-zinc-100 dark:bg-zinc-800 p-2 rounded text-[11px] text-zinc-600 dark:text-zinc-400 border-l-2 border-zinc-300 dark:border-zinc-600 truncate mb-2">
                                        {{ $activity['parent_content'] }}
                                    </div>
                                @endif

                                <div class="text-sm text-zinc-800 dark:text-zinc-200 line-clamp-2">
                                    {{ $activity['content'] }}
                                </div>
                            </div>

                            @if($activity['type'] === 'mention' && !$activity['is_read'])
                                <div class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-1.5" title="未読"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
