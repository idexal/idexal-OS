@php
    $adminNav = [
        ['route' => 'admin.index', 'active' => 'admin.index', 'icon' => 'grid', 'label' => __('ui.admin.nav.dashboard')],
        ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'users', 'label' => __('ui.admin.nav.users')],
        ['route' => 'admin.organizations.index', 'active' => 'admin.organizations.*', 'icon' => 'building', 'label' => __('ui.admin.nav.organizations')],
        ['route' => 'admin.plans.index', 'active' => 'admin.plans.*', 'icon' => 'layers', 'label' => __('ui.admin.nav.plans')],
        ['route' => 'admin.subscriptions.index', 'active' => 'admin.subscriptions.*', 'icon' => 'refresh', 'label' => __('ui.admin.nav.subscriptions')],
        ['route' => 'admin.invoices.index', 'active' => 'admin.invoices.*', 'icon' => 'invoice', 'label' => __('ui.admin.nav.invoices')],
        ['route' => 'admin.payments.index', 'active' => 'admin.payments.*', 'icon' => 'card', 'label' => __('ui.admin.nav.payments')],
        ['route' => 'admin.payment-gateways', 'active' => 'admin.payment-gateways*', 'icon' => 'card', 'label' => __('ui.admin.nav.payment_gateways')],
        ['route' => 'admin.social-login', 'active' => 'admin.social-login*', 'icon' => 'users', 'label' => __('ui.admin.nav.social_login')],
        ['route' => 'admin.referrals', 'active' => 'admin.referrals*', 'icon' => 'send', 'label' => __('ui.admin.nav.referrals')],
        ['route' => 'admin.providers.index', 'active' => 'admin.providers.*', 'icon' => 'server', 'label' => __('ui.admin.nav.providers')],
        ['route' => 'admin.combos.index', 'active' => 'admin.combos.*', 'icon' => 'layers', 'label' => __('ui.admin.nav.combos')],
        ['route' => 'admin.catalog.index', 'active' => 'admin.catalog.*', 'icon' => 'search', 'label' => __('ui.admin.nav.catalog')],
        ['route' => 'admin.extensions.index', 'active' => 'admin.extensions.*', 'icon' => 'package', 'label' => __('ui.admin.nav.extensions')],
        ['route' => 'admin.features', 'active' => 'admin.features*', 'icon' => 'sparkles', 'label' => __('ui.admin.nav.features')],
        ['route' => 'admin.agent-permissions', 'active' => 'admin.agent-permissions*', 'icon' => 'shield', 'label' => __('ui.admin.nav.agent_permissions')],
        ['route' => 'admin.connectors', 'active' => 'admin.connectors*', 'icon' => 'shield', 'label' => __('ui.admin.nav.connectors')],
        ['route' => 'admin.proxy-providers.index', 'active' => 'admin.proxy-providers.*', 'icon' => 'globe', 'label' => __('ui.admin.nav.proxy_providers')],
        ['route' => 'admin.gateway', 'active' => 'admin.gateway', 'icon' => 'zap', 'label' => __('ui.admin.nav.gateway')],
        ['route' => 'admin.settings', 'active' => 'admin.settings*', 'icon' => 'settings', 'label' => __('ui.admin.nav.settings')],
        ['route' => 'admin.audit', 'active' => 'admin.audit', 'icon' => 'terminal', 'label' => __('ui.admin.nav.audit')],
        ['route' => 'admin.support.index', 'active' => 'admin.support*', 'icon' => 'message', 'label' => __('ui.admin.nav.support'), 'badge' => $adminOpenTickets > 0 ? $adminOpenTickets : null],
    ];
@endphp

<nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-5 no-scrollbar" aria-label="admin">
    <p class="sidebar-group-label">{{ __('ui.admin.title') }}</p>
    @foreach ($adminNav as $item)
        <a
            href="{{ route($item['route']) }}"
            class="admin-link {{ request()->routeIs($item['active']) ? 'admin-link-active' : '' }}"
            @if (request()->routeIs($item['active'])) aria-current="page" @endif
        >
            <x-icon :name="$item['icon']" class="size-[18px]" />
            <span class="flex-1">{{ $item['label'] }}</span>
            @if (! empty($item['badge']))
                <span class="rounded-full bg-lime px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink-950">{{ $item['badge'] }}</span>
            @endif
        </a>
    @endforeach

    <p class="sidebar-group-label">{{ __('ui.nav.dashboard') }}</p>
    <a href="{{ route('dashboard.index') }}" class="admin-link">
        <x-icon name="arrow-left" class="size-[18px] rtl:rotate-180" />
        <span class="flex-1">{{ __('ui.admin.nav.back') }}</span>
    </a>
</nav>
