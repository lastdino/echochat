@props([
    'type' => 'navlist', // 'navlist' or 'navbar'
    'badge' => null,
    'badgeColor' => 'red',
])

@if ($type === 'navbar')
    <flux:navbar.item {{ $attributes->merge(['badge' => $badge > 0 ? $badge : null, 'badge:color' => $badgeColor]) }}>
        {{ $slot }}
    </flux:navbar.item>
@else
    <flux:navlist.item {{ $attributes->merge(['badge' => $badge > 0 ? $badge : null, 'badge:color' => $badgeColor]) }}>
        {{ $slot }}
    </flux:navlist.item>
@endif
