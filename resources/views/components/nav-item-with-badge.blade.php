@props([
    'type' => 'navlist', // 'navlist' or 'navbar'
    'badge' => null,
    'badgeColor' => 'red',
])

@if ($type === 'navbar')
    <flux:navbar.item {{ $attributes->merge(['badge' => $badge > 0 ? $badge : null, 'badge:color' => $badgeColor]) }} badge:variant="outline" class="min-w-0 flex-1">
        <div class="">{{ $slot }}</div>
    </flux:navbar.item>
@else
    <flux:navlist.item {{ $attributes->merge(['badge' => $badge > 0 ? $badge : null, 'badge:color' => $badgeColor]) }} badge:variant="outline" class="min-w-0 flex-1">
        <div class="">{{ $slot }}</div>
    </flux:navlist.item>
@endif
