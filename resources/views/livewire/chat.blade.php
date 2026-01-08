<?php

use EchoChat\Models\Channel;
use EchoChat\Models\Workspace;
use EchoChat\Services\AIModelService;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component
{
    public Workspace $workspace;

    public ?Channel $activeChannel = null;

    public string $summary = '';

    public bool $isSummarizing = false;

    public string $search = '';

    public bool $isSearching = false;

    public function mount(Workspace $workspace, ?string $channel = null)
    {
        Gate::authorize('view', $workspace);

        $this->workspace = $workspace;

        if ($channel) {
            $this->activeChannel = $workspace->channels()->where('name', $channel)->first();
        }

        if (! $this->activeChannel) {
            $this->activeChannel = $workspace->channels()->first();
        }

        if ($this->activeChannel) {
            $this->activeChannel->load('members.user');
        }
    }

    protected $listeners = [
        'channelSelected' => 'selectChannel',
        'channelUpdated' => '$refresh',
        'memberAdded' => '$refresh',
    ];

    public function selectChannel($channelId)
    {
        $this->activeChannel = Channel::with('members.user')->find($channelId);
        $this->summary = '';
        $this->search = '';
        $this->isSearching = false;
    }

    public function updatedSearch()
    {
        $this->dispatch('searchMessages', search: $this->search)->to('message-feed');
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

            $this->activeChannel = Channel::find($this->activeChannel->id);
            $this->dispatch('channelCreated'); // サイドバーを更新するため
            $this->dispatch('messageSent'); // メッセージフィードを更新するため
        }
    }
}; ?>

<div class="flex h-[calc(100vh-theme(spacing.16))] lg:h-screen bg-white dark:bg-zinc-900 overflow-hidden -m-6 lg:-m-8">
    <!-- Sidebar -->
    <div class="w-64 flex-shrink-0 bg-zinc-100 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700">
        <livewire:channel-list :workspace="$workspace" :activeChannel="$activeChannel" />
    </div>
    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-w-0">
        @if($activeChannel)
            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
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
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <flux:button wire:click="toggleSearch" variant="subtle" size="sm" icon="magnifying-glass" square title="検索" :class="$isSearching ? 'text-blue-600' : ''" />
                        </div>
                    @endif
                </div>

                @if($isSearching)
                    <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="メッセージを検索..."
                            icon="magnifying-glass"
                            size="sm"
                            autofocus
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

                <div class="flex-1 overflow-y-auto flex flex-col-reverse">
                    <div>
                        <livewire:message-feed :channel="$activeChannel" wire:key="feed-{{ $activeChannel->id }}"/>
                    </div>
                </div>

                <div class="p-4">
                    @if($activeChannel->isMember(auth()->id()))
                        <livewire:message-input :channel="$activeChannel" wire:key="input-{{ $activeChannel->id }}"/>
                    @elseif($activeChannel->canJoin(auth()->id()))
                        <div
                            class="bg-zinc-50 dark:bg-zinc-800 p-8 rounded-lg border border-zinc-200 dark:border-zinc-700 text-center">
                            <h3 class="text-zinc-900 dark:text-white font-bold mb-2"># {{ $activeChannel->name }}
                                に参加しますか？</h3>
                            <p class="text-zinc-500 dark:text-zinc-400 mb-6">
                                参加すると、このチャンネルでメッセージを送信できるようになります。</p>
                            <flux:button wire:click="joinChannel" variant="primary">チャンネルに参加する</flux:button>
                        </div>
                    @else
                        <div
                            class="bg-zinc-50 dark:bg-zinc-800 p-8 rounded-lg border border-zinc-200 dark:border-zinc-700 text-center text-zinc-500">
                            このプライベートチャンネルを閲覧する権限がありません。
                        </div>
                    @endif
                </div>

                @if($activeChannel->is_private)
                    <flux:modal name="invite-member-modal" class="md:w-[500px]">
                        <livewire:invite-member :channel="$activeChannel"/>
                    </flux:modal>
                @endif

                @can('update', $activeChannel)
                    <flux:modal name="edit-channel-modal" class="md:w-[500px]">
                        <livewire:edit-channel :channel="$activeChannel" wire:key="edit-{{ $activeChannel->id }}"/>
                    </flux:modal>
                @endcan
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <p class="text-zinc-500">チャンネルを選択してください</p>
            </div>
        @endif
    </div>
</div>
