@props(['priority' => 'normal'])

@php
    $map = [
        'low' => ['label' => __('ui.support.priorities.low'), 'class' => 'badge-gray'],
        'normal' => ['label' => __('ui.support.priorities.normal'), 'class' => 'badge-blue'],
        'high' => ['label' => __('ui.support.priorities.high'), 'class' => 'badge-amber'],
        'urgent' => ['label' => __('ui.support.priorities.urgent'), 'class' => 'badge-red'],
    ];

    $meta = $map[$priority] ?? ['label' => $priority, 'class' => 'badge-gray'];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$meta['class']]) }}>{{ $meta['label'] }}</span>
