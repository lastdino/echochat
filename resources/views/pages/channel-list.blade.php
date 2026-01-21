@use('EchoChat\Support\Tables')
<div class="flex flex-col h-full relative" x-ref="sidebar" x-data="{
    open: false,
    x: 0,
    y: 0,
    type: null,
    memberId: null,
    memberName: '',
    channelId: null,
    channelName: '',
    canDelete: false
}">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
            <a href="{{ route('echochat.workspaces') }}" class="lg:hidden text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" wire:navigate>
                <flux:icon icon="chevron-left" variant="mini" />
            </a>
            <h1 class="font-bold text-xl dark:text-white truncate">{{ $workspace->name }}</h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @can('update', $workspace)
                <flux:button variant="subtle" size="sm" icon="cog-6-tooth" square href="{{ route('echochat.workspaces.settings', ['workspace' => $workspace->slug]) }}" />
            @endcan

            <button
                @click="showSidebar = false"
                class="lg:hidden p-1 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
            >
                <flux:icon icon="x-mark" variant="mini" />
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
        <flux:navlist>
            @php
                $channels = $workspace->channels()
                    ->where('is_dm', false)
                    ->leftJoin(Tables::name('channel_user'), function ($join) {
                        $join->on(Tables::name('channels').'.id', '=', Tables::name('channel_user').'.channel_id')
                            ->where(Tables::name('channel_user').'.user_id', '=', auth()->id());
                    })
                    ->select(Tables::name('channels').'.*', Tables::name('channel_user').'.sort_order as user_sort_order')
                    ->orderByRaw('COALESCE('.Tables::name('channel_user').'.sort_order, '.Tables::name('channels').'.id) ASC')
                    ->get()
                    ->filter(fn($channel) => $channel->canView(auth()->id()));

                $publicChannels = $channels->where('is_private', false);
                $privateChannels = $channels->where('is_private', true);
            @endphp

            <flux:navlist.group heading="パブリックチャンネル" expandable>
                @can('createChannel', $workspace)
                    <x-slot name="actions">
                        <flux:modal.trigger name="create-channel-modal">
                            <flux:button variant="subtle" size="sm" icon="plus" square class="text-zinc-500 hover:text-blue-600" />
                        </flux:modal.trigger>
                    </x-slot>
                @endcan

                <div >
                    @foreach($publicChannels as $channel)
                        <div class="group/channel relative" >
                            <x-echochat-nav-item-with-badge
                                wire:click="selectChannel({{ $channel->id }})"
                                @contextmenu.prevent="let rect = $refs.sidebar.getBoundingClientRect(); open = true; x = $event.clientX - rect.left; y = $event.clientY - rect.top; type = 'channel'; channelId = {{ $channel->id }}; channelName = '{{ $channel->name }}'; canDelete = {{ Gate::check('delete', $channel) ? 'true' : 'false' }}"
                                :current="$activeChannel && $activeChannel->id === $channel->id"
                                :badge="isset($notifications[$channel->id]) ? $notifications[$channel->id] : 0"
                                badge-color="blue"
                                :icon="$channel->displayIcon"
                            >
                                {{ $channel->displayName }}
                            </x-echochat-nav-item-with-badge>
                        </div>
                    @endforeach
                </div>

                @can('createChannel', $workspace)
                    <flux:modal.trigger name="create-channel-modal">
                        <flux:navlist.item icon="plus" class="text-zinc-500">
                            チャンネルを追加...
                        </flux:navlist.item>
                    </flux:modal.trigger>
                @endcan
            </flux:navlist.group>

            <flux:navlist.group heading="プライベートチャンネル" expandable class="mt-4">
                <div >
                    @foreach($privateChannels as $channel)
                        <div class="group/channel relative" >
                            <x-echochat-nav-item-with-badge
                                wire:click="selectChannel({{ $channel->id }})"
                                @contextmenu.prevent="let rect = $refs.sidebar.getBoundingClientRect(); open = true; x = $event.clientX - rect.left; y = $event.clientY - rect.top; type = 'channel'; channelId = {{ $channel->id }}; channelName = '{{ $channel->name }}'; canDelete = {{ Gate::check('delete', $channel) ? 'true' : 'false' }}"
                                :current="$activeChannel && $activeChannel->id === $channel->id"
                                :badge="isset($notifications[$channel->id]) ? $notifications[$channel->id] : 0"
                                badge-color="blue"
                                :icon="$channel->displayIcon"
                            >
                                {{ $channel->displayName }}
                            </x-echochat-nav-item-with-badge>
                        </div>
                    @endforeach
                </div>
            </flux:navlist.group>

            <flux:navlist.group heading="ダイレクトメッセージ" expandable class="mt-4">
                @php
                    $ownerDmChannel = \EchoChat\Models\Channel::where('workspace_id', $workspace->id)
                        ->where('is_dm', true)
                        ->whereHas('members', fn($q) => $q->where('user_id', auth()->id()));

                    if ($workspace->owner->id === auth()->id()) {
                        $ownerDmChannel->has('members', 1);
                    } else {
                        $ownerDmChannel->whereHas('members', fn($q) => $q->where('user_id', $workspace->owner->id));
                    }

                    $ownerDmChannel = $ownerDmChannel->first();
                @endphp
                <x-echochat-nav-item-with-badge
                    wire:key="member-owner-{{ $workspace->owner->id }}"
                    wire:click="openDirectMessage({{ $workspace->owner->id }})"
                    :current="$activeChannel && $ownerDmChannel && $activeChannel->id === $ownerDmChannel->id"
                    :badge="$ownerDmChannel ? ($notifications[$ownerDmChannel->id] ?? 0) : 0"
                    badge-color="blue"
                >

                    <x-slot name="icon">
                        <flux:avatar size="xs" :name="\EchoChat\Support\UserSupport::getName($workspace->owner)" src="{{$workspace->owner->getUserAvatar()}}"/>
                    </x-slot>
                    <div class="flex items-center gap-1.5">
                        <span class="truncate">{{ \EchoChat\Support\UserSupport::getName($workspace->owner) }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-crown text-yellow-500 shrink-0 w-3 h-3"><path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.735H5.809a1 1 0 0 1-.956-.735L2.019 6.018a.5.5 0 0 1 .798-.519l4.277 3.664a1 1 0 0 0 1.516-.294z"/><path d="M5 21h14"/></svg>
                        @if($workspace->owner->id === auth()->id())
                            <span class="text-zinc-500 dark:text-zinc-400 shrink-0">(自分)</span>
                        @endif
                    </div>
                </x-echochat-nav-item-with-badge>

                @foreach($workspace->members as $member)
                    @php
                        $memberDmChannel = \EchoChat\Models\Channel::where('workspace_id', $workspace->id)
                            ->where('is_dm', true)
                            ->whereHas('members', fn($q) => $q->where('user_id', auth()->id()));

                        if ($member->id === auth()->id()) {
                            $memberDmChannel->has('members', 1);
                        } else {
                            $memberDmChannel->whereHas('members', fn($q) => $q->where('user_id', $member->id));
                        }

                        $memberDmChannel = $memberDmChannel->first();
                        $memberName = \EchoChat\Support\UserSupport::getName($member);
                    @endphp
                        @can('removeMember', $workspace)
                            <x-echochat-nav-item-with-badge
                                wire:key="member-{{ $member->id }}"
                                wire:click="openDirectMessage({{ $member->id }})"
                                @contextmenu.prevent="let rect = $refs.sidebar.getBoundingClientRect(); open = true; x = $event.clientX - rect.left; y = $event.clientY - rect.top; type = 'member'; memberId = {{ $member->id }}; memberName = '{{ $memberName }}'"
                                :current="$activeChannel && $memberDmChannel && $activeChannel->id === $memberDmChannel->id"
                                :badge="$memberDmChannel ? ($notifications[$memberDmChannel->id] ?? 0) : 0"
                                badge-color="blue"
                            >
                                <x-slot name="icon">
                                    <flux:avatar size="xs" :name="$memberName" src="{{$member->getUserAvatar()}}"/>
                                </x-slot>

                                {{ $memberName }} {{ $member->id === auth()->id() ? '(自分)' : '' }}
                            </x-echochat-nav-item-with-badge>
                        @else
                            <x-echochat-nav-item-with-badge
                                wire:key="member-{{ $member->id }}"
                                wire:click="openDirectMessage({{ $member->id }})"
                                :current="$activeChannel && $memberDmChannel && $activeChannel->id === $memberDmChannel->id"
                                :badge="$memberDmChannel ? ($notifications[$memberDmChannel->id] ?? 0) : 0"
                                badge-color="blue"
                            >
                                <x-slot name="icon">
                                    <flux:avatar size="xs" :name="$memberName" src="{{$member->getUserAvatar()}}"/>
                                </x-slot>

                                {{ $memberName }} {{ $member->id === auth()->id() ? '(自分)' : '' }}
                            </x-echochat-nav-item-with-badge>
                        @endcan
                    @endforeach

                @can('invite', $workspace)
                    <div class="px-2 mt-2">
                        <flux:modal.trigger name="invite-workspace-member-modal">
                            <flux:button variant="subtle" icon="plus" class="w-full justify-start text-zinc-500">
                                チームメンバーを追加...
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @endcan
            </flux:navlist.group>
        </flux:navlist>
    </div>

    <flux:modal name="create-channel-modal" class="md:w-[500px]">
        <livewire:echochat-create-channel :workspace="$workspace" />
    </flux:modal>

    <flux:modal name="invite-workspace-member-modal" class="md:w-[500px]">
        <livewire:echochat-invite-workspace-member :workspace="$workspace" />
    </flux:modal>

    {{-- Context Menu --}}
    <div
        x-show="open"
        x-cloak
        @click.away="open = false"
        @keydown.escape.window="open = false"
        x-bind:style="`position: absolute; left: ${x}px; top: ${y}px; z-index: 50;`"
        class="min-w-48 p-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-md"
    >
        <template x-if="type === 'member'">
            <flux:navlist>
                @can('transferOwnership', $workspace)
                    <flux:navlist.item icon="user-circle"
                                       @click="
                        if (confirm(`${memberName}にオーナー権限を譲渡しますか？`)) {
                            $wire.transferOwnership(memberId);
                        }
                        open = false;
                    "
                    >オーナー権限を譲渡</flux:navlist.item>
                @endcan

                <flux:navlist.item icon="trash"
                                   @click="
                    if (confirm(`${memberName}をワークスペースから削除しますか？`)) {
                        $wire.removeMember(memberId);
                    }
                    open = false;
                "
                >削除</flux:navlist.item>
            </flux:navlist>
        </template>

        <template x-if="type === 'channel' && canDelete">
            <flux:navlist>
                <flux:navlist.item icon="trash"
                                   @click="
                    if (confirm(`「${channelName}」を削除してもよろしいですか？`)) {
                        $wire.deleteChannel(channelId);
                    }
                    open = false;
                "
                >削除</flux:navlist.item>
            </flux:navlist>
        </template>
    </div>
</div>
