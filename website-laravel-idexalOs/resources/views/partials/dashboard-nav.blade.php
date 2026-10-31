@php
    $groups = [
        [
            'label' => __('ui.dashboard.title'),
            'items' => [
                ['route' => 'dashboard.index', 'active' => 'dashboard.index', 'icon' => 'grid', 'label' => __('ui.dashboard.nav.overview')],
                ['route' => 'dashboard.usage', 'active' => 'dashboard.usage*', 'icon' => 'chart', 'label' => __('ui.dashboard.nav.usage')],
                ['route' => 'dashboard.models', 'active' => 'dashboard.models*', 'icon' => 'cpu', 'label' => __('ui.dashboard.nav.models')],
                ['route' => 'dashboard.api-keys.index', 'active' => 'dashboard.api-keys.*', 'icon' => 'key', 'label' => __('ui.dashboard.nav.api_keys')],
                ['route' => 'dashboard.developer', 'active' => 'dashboard.developer*', 'icon' => 'code', 'label' => __('ui.dashboard.nav.developer')],
            ],
        ],
        [
            'label' => __('ui.team.title'),
            'items' => [
                ['route' => 'dashboard.team', 'active' => 'dashboard.team*', 'icon' => 'users', 'label' => __('ui.dashboard.nav.team')],
                ['route' => 'dashboard.billing', 'active' => 'dashboard.billing*', 'icon' => 'card', 'label' => __('ui.dashboard.nav.billing')],
                ['route' => 'dashboard.referrals', 'active' => 'dashboard.referrals*', 'icon' => 'send', 'label' => __('ui.dashboard.nav.referrals')],
                ['route' => 'dashboard.support.index', 'active' => 'dashboard.support*', 'icon' => 'message', 'label' => __('ui.dashboard.nav.support')],
                ['route' => 'dashboard.desktop', 'active' => 'dashboard.desktop*', 'icon' => 'monitor', 'label' => __('ui.dashboard.nav.desktop')],
            ],
        ],
        [
            'label' => __('ui.dashboard.nav.settings'),
            'items' => [
                ['route' => 'dashboard.notifications', 'active' => 'dashboard.notifications*', 'icon' => 'bell', 'label' => __('ui.dashboard.nav.notifications'), 'badge' => $layoutUnread > 0 ? $layoutUnread : null],
                ['route' => 'dashboard.settings', 'active' => 'dashboard.settings*', 'icon' => 'settings', 'label' => __('ui.dashboard.nav.settings')],
                ['route' => 'dashboard.profile', 'active' => 'dashboard.profile*', 'icon' => 'user', 'label' => __('ui.dashboard.nav.profile')],
            ],
        ],
    ];
@endphp

<nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-5 no-scrollbar" aria-label="dashboard">
    @foreach ($groups as $group)
        <p class="sidebar-group-label">{{ $group['label'] }}</p>
        @foreach ($group['items'] as $item)
            <a
                href="{{ route($item['route']) }}"
                class="sidebar-link {{ request()->routeIs($item['active']) ? 'sidebar-link-active' : '' }}"
                @if (request()->routeIs($item['active'])) aria-current="page" @endif
            >
                <x-icon :name="$item['icon']" class="size-[18px]" />
                <span class="flex-1">{{ $item['label'] }}</span>
                @if (isset($item['badge']) && $item['badge'])
                    <span class="rounded-full bg-lime px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink-950">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    @endforeach

    <p class="sidebar-group-label">{{ __('ui.nav.docs') }}</p>
    <a href="{{ route('docs') }}" class="sidebar-link">
        <x-icon name="book" class="size-[18px]" />
        <span class="flex-1">{{ __('ui.dashboard.nav.docs') }}</span>
        <x-icon name="external" class="size-3.5 opacity-40" />
    </a>

    @if (auth()->user()?->isAdmin())
        <a href="{{ route('admin.index') }}" class="sidebar-link">
            <x-icon name="shield" class="size-[18px]" />
            <span class="flex-1">{{ __('ui.dashboard.nav.admin') }}</span>
            <x-icon name="arrow-up-right" class="size-3.5 opacity-40" />
        </a>
    @endif
</nav>
