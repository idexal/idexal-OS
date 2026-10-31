@props(['status' => 'open'])

@php
    $map = [
        'open' => ['label' => __('ui.support.statuses.open'), 'class' => 'badge-amber'],
        'pending' => ['label' => __('ui.support.statuses.pending'), 'class' => 'badge-blue'],
        'resolved' => ['label' => __('ui.support.statuses.resolved'), 'class' => 'badge-green'],
        'closed' => ['label' => __('ui.support.statuses.closed'), 'class' => 'badge-gray'],
    ];

    $meta = $map[$status] ?? ['label' => $status, 'class' => 'badge-gray'];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$meta['class']]) }}>{{ $meta['label'] }}</span>
