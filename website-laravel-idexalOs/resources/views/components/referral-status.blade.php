@props(['status' => 'pending'])

@php
    $map = [
        'pending' => ['label' => __('ui.referrals.statuses.pending'), 'class' => 'badge-amber'],
        'qualified' => ['label' => __('ui.referrals.statuses.qualified'), 'class' => 'badge-blue'],
        'rewarded' => ['label' => __('ui.referrals.statuses.rewarded'), 'class' => 'badge-green'],
        'rejected' => ['label' => __('ui.referrals.statuses.rejected'), 'class' => 'badge-red'],
    ];

    $meta = $map[$status] ?? ['label' => $status, 'class' => 'badge-gray'];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$meta['class']]) }}>{{ $meta['label'] }}</span>
