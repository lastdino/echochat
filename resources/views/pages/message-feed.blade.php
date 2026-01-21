<div class="p-4 space-y-4">
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
                @foreach($messages as $message)
                    <x-echochat-message-item :message="$message" />
                @endforeach
            </div>
        </div>
    @endforeach
</div>
