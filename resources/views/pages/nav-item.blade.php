<div class="w-full">
    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('echochat.workspaces')" :current="request()->routeIs('echochat.workspaces')" :badge="$unreadNotifications ?: null" badgeColor="red">{{ __('Inbox') }}</flux:sidebar.item>
</div>
