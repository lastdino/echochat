<div class="p-4 bg-white dark:bg-zinc-800 rounded-lg shadow">
    <h3 class="text-lg font-bold mb-4 dark:text-white">メンバーを招待</h3>
    <form wire:submit.prevent="invite">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="ユーザー名またはメールアドレスで検索..." icon="magnifying-glass" />

            <div class="max-h-60 overflow-y-auto space-y-2">
                @forelse($this->workspaceMembers as $member)
                    <label wire:key="member-{{ $member->id }}" class="flex items-center gap-2 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 rounded cursor-pointer">
                        <flux:checkbox wire:model.live="selectedUserIds" value="{{ $member->id }}" />
                        <div class="flex flex-col">
                            <span class="text-sm font-medium dark:text-white">{{ \EchoChat\Support\UserSupport::getName($member) }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</span>
                        </div>
                    </label>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 p-2">招待可能なワークスペースメンバーはいません。</p>
                @endforelse
            </div>

            @if($message)
                <p class="text-sm text-blue-600 dark:text-blue-400">{{ $message }}</p>
            @endif

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary" :disabled="empty($selectedUserIds)">招待</flux:button>
            </div>
        </div>
    </form>
</div>
