@props(['message', 'isReply' => false])

@php
    $userName = \EchoChat\Support\UserSupport::getName($message->user);
@endphp

<div id="message-{{ $message->id }}"
    x-data="{ showReplies: false }"
    x-on:expand-replies.window="if ($event.detail.messageId == {{ $message->id }} || ($event.detail.ancestorIds && $event.detail.ancestorIds.includes({{ $message->id }}))) showReplies = true"
    class="flex items-start gap-3 group/message transition-colors duration-500 {{ $isReply ? 'mt-3' : '' }}"
    wire:key="message-{{ $message->id }}"
>
    <flux:avatar size="sm" :name="$userName" src="{{$message->user->getUserAvatar()}}"/>
    <div class="min-w-0 flex-1">
        @if($message->parent && ! $isReply)
            @php
                $parentUserName = \EchoChat\Support\UserSupport::getName($message->parent->user);
            @endphp
            <a href="#message-{{ $message->parent_id }}" class="flex items-center gap-1 text-xs text-zinc-500 mb-1 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors" @click.prevent="document.getElementById('message-{{ $message->parent_id }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); const el = document.getElementById('message-{{ $message->parent_id }}'); el.classList.add('bg-zinc-100', 'dark:bg-zinc-800'); setTimeout(() => el.classList.remove('bg-zinc-100', 'dark:bg-zinc-800'), 2000)">
                <flux:icon icon="arrow-uturn-left" class="w-3 h-3" />
                <span class="font-bold">{{ $parentUserName }}</span>
                <span class="truncate opacity-70">{{ $message->parent->content }}</span>
            </a>
        @endif
        <div class="flex items-baseline gap-2">
            <span class="font-bold dark:text-white">{{ $userName }}</span>
            <span class="text-xs text-zinc-500">{{ $message->created_at->format('H:i') }}</span>
            <button
                type="button"
                wire:click="replyTo({{ $message->id }})"
                class="hidden group-hover/message:inline-flex items-center text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors ml-2"
                title="返信"
            >
                <flux:icon icon="arrow-uturn-left" class="w-3 h-3 mr-1" />
                返信
            </button>
        </div>
        @if($message->content)
            <div class="text-zinc-700 dark:text-zinc-300 break-words whitespace-pre-wrap">{!! $this->formatContent($message->content) !!}</div>
        @endif

        <div class="mt-2 flex flex-wrap gap-1 items-center">
            @if($message->reactions->count() > 0)
                @foreach($message->reactions->groupBy('emoji') as $emoji => $reactions)
                    @php
                        $hasReacted = $reactions->contains('user_id', auth()->id());
                        $userNames = $reactions->map(fn($r) => \EchoChat\Support\UserSupport::getName($r->user))->join(', ');
                    @endphp
                    <button
                        type="button"
                        wire:click="toggleReaction({{ $message->id }}, '{{ $emoji }}')"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs transition-colors {{ $hasReacted ? 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300' : 'bg-zinc-50 border-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}"
                        title="{{ $userNames }}"
                    >
                        <span>{{ $emoji }}</span>
                        <span class="font-medium">{{ $reactions->count() }}</span>
                    </button>
                @endforeach
            @endif

            <flux:dropdown>
                <flux:button type="button" variant="subtle" size="xs" class="rounded-full px-1.5 py-0.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-opacity text-sm opacity-0 group-hover/message:opacity-100" icon="smile-plus" icon:variant="outline" title="リアクションを追加" />

                <flux:menu class="min-w-32">
                    <div class="p-2 grid grid-cols-6 gap-1">
                        @foreach(['👍', '❤️', '😄', '😮', '😢', '🔥', '👏', '🎉', '🙌', '👀', '✅', '🚀'] as $emoji)
                            <button
                                type="button"
                                wire:click="toggleReaction({{ $message->id }}, '{{ $emoji }}')"
                                class="p-1 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded text-lg flex items-center justify-center transition-colors"
                            >
                                {{ $emoji }}
                            </button>
                        @endforeach
                    </div>
                </flux:menu>
            </flux:dropdown>

            <flux:dropdown>
                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="opacity-0 group-hover/message:opacity-100 transition-opacity" />
                <flux:menu x-data="{
                    copy(content) {
                        if (! navigator.clipboard) {
                            const el = document.createElement('textarea');
                            el.value = content;
                            document.body.appendChild(el);
                            el.select();
                            document.execCommand('copy');
                            document.body.removeChild(el);
                            return;
                        }
                        navigator.clipboard.writeText(content);
                    }
                }">
                    <flux:menu.item icon="arrow-uturn-left" wire:click="replyTo({{ $message->id }})">返信</flux:menu.item>
                    <flux:menu.item icon="document-duplicate" @click="copy({{ Js::from($message->content) }})">コピー</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        @if($message->media->count() > 0)
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($message->media as $media)
                    <div class="max-w-sm relative group/media">
                        @php
                            $url = route('echochat.attachments.show', ['media' => $media->uuid]);
                        @endphp
                        @if(str_starts_with($media->mime_type, 'image/'))
                            <a href="{{ $url }}" target="_blank" class="block">
                                <img src="{{ $url }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 max-h-64 object-contain" alt="{{ $media->name }}" />
                            </a>
                        @else
                            <a href="{{ $url }}" target="_blank" class="flex items-center gap-2 p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                                <flux:icon icon="paper-clip" class="w-4 h-4 text-zinc-500" />
                                <span class="text-sm dark:text-white truncate max-w-xs">{{ $media->file_name }}</span>
                                <span class="text-xs text-zinc-500">({{ $media->human_readable_size }})</span>
                            </a>
                        @endif

                        @if($message->user_id === auth()->id())
                            <button
                                type="button"
                                wire:click="deleteAttachment({{ $message->id }}, {{ $media->id }})"
                                wire:confirm="この添付ファイルを削除してもよろしいですか？"
                                class="absolute -top-2 -right-2 bg-white dark:bg-zinc-800 text-zinc-400 hover:text-red-500 rounded-full p-1 shadow-sm border border-zinc-200 dark:border-zinc-700 group-hover/media:block hidden transition-opacity"
                                title="削除"
                            >
                                <flux:icon icon="x-mark" class="w-3 h-3" />
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- 返信のサマリー表示（トップレベルのみ） --}}
        @if(! $isReply && $message->replies->isNotEmpty())
            @php
                $replyCount = $message->replies->count();
                $lastReply = $message->replies->sortByDesc('created_at')->first();
                $replyUsers = $message->replies->map(fn($r) => $r->user)->unique('id')->take(3);
            @endphp
            <div x-show="! showReplies" class="mt-2 flex items-center gap-2">
                <div class="flex -space-x-2">
                    @foreach($replyUsers as $replyUser)
                        <flux:avatar size="xs" :name="\EchoChat\Support\UserSupport::getName($replyUser)" src="{{ $replyUser->getUserAvatar() }}" class="ring-2 ring-white dark:ring-zinc-900" />
                    @endforeach
                </div>
                <button
                    type="button"
                    @click="showReplies = true"
                    class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline transition-colors flex items-center gap-2"
                >
                    {{ $replyCount }} 件の返信
                    <span class="font-normal text-zinc-500 dark:text-zinc-400">最終返信: {{ $lastReply->created_at->diffForHumans() }}</span>
                </button>
            </div>
        @endif

        {{-- 返信の再帰表示 --}}
        @if(empty($search) && $message->replies->isNotEmpty())
            <div
                x-show="showReplies || {{ $isReply ? 'true' : 'false' }}"
                class="mt-3 space-y-3 pl-4 border-l-2 border-zinc-100 dark:border-zinc-800"
            >
                @foreach($message->replies as $reply)
                    <x-echochat::message-item :message="$reply" :is-reply="true" />
                @endforeach

                @if(! $isReply)
                    <div class="pt-2">
                        <button
                            type="button"
                            @click="showReplies = false"
                            class="text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 flex items-center gap-1 transition-colors"
                        >
                            <flux:icon icon="chevron-up" class="w-3 h-3" />
                            返信を閉じる
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
