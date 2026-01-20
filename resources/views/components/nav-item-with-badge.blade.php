@props([
    'type' => 'navlist', // 'navlist' or 'navbar'
    'badge' => null,
    'badgeColor' => 'red',
])

@php
    $badgeValue = (is_numeric($badge) && $badge > 0) ? (int) $badge : null;
@endphp

@if ($type === 'navbar')
    <flux:navbar.item {{ $attributes->merge(['badge' => $badgeValue, 'badge:color' => $badgeColor]) }} badge:variant="outline" class="min-w-0 flex-1">
        <div class="">{{ $slot }}</div>
    </flux:navbar.item>
@else
    <flux:navlist.item {{ $attributes->merge(['badge' => $badgeValue, 'badge:color' => $badgeColor]) }} badge:variant="outline" class="min-w-0 flex-1">
        <div class="">{{ $slot }}</div>
    </flux:navlist.item>
@endif
