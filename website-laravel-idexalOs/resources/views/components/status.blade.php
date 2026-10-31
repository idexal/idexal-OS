@props(['status' => 'pending', 'class' => ''])

@php
    $key = 'ui.statuses.'.$status;
    $label = trans()->has($key) ? __($key) : str_replace('_', ' ', (string) $status);

    $tone = match ((string) $status) {
        'active', 'paid', 'succeeded', 'replied' => 'badge-green',
        'trialing', 'pending', 'new', 'incomplete', 'open', 'draft', 'read' => 'badge-amber',
        'past_due', 'unpaid', 'failed', 'suspended', 'void', 'uncollectible' => 'badge-red',
        'canceled', 'revoked', 'expired' => 'badge-gray',
        'refunded' => 'badge-blue',
        default => 'badge-gray',
    };
@endphp

<span {{ $attributes->merge(['class' => trim('badge '.$tone.' '.$class)]) }}>
    <span class="dot"></span>
    {{ $label }}
</span>
