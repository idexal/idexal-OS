@extends('layouts.public')

@section('title', __('ui.download.title'))
@section('description', __('ui.download.subtitle'))

@push('schema')
    <script type="application/ld+json">{!! json_ld([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => config('idexal.brand.name'),
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Windows, macOS, Linux',
        'url' => localized_route('download'),
        'downloadUrl' => localized_route('download'),
        'softwareVersion' => config('idexal.version'),
        'description' => __('ui.meta.description'),
        'inLanguage' => str_replace('_', '-', app()->getLocale()),
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => strtoupper((string) config('idexal.billing.currency', 'USD')),
        ],
    ]) !!}</script>
@endpush

@section('content')
    <section class="relative overflow-hidden border-b border-ink-950/10">
        <div class="pointer-events-none absolute inset-0 grid-lines opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent_85%)]"></div>
        <div class="container-x relative grid gap-10 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:py-24">
            <div>
                <span class="eyebrow">
                    <span class="dot bg-lime-dark"></span>
                    {{ __('ui.download.eyebrow') }}
                </span>
                <h1 class="mt-6 max-w-2xl text-balance text-4xl font-semibold leading-[1.16] tracking-[-0.03em] sm:text-5xl">
                    {{ __('ui.download.heading') }}
                </h1>
                <p class="mt-5 max-w-xl text-pretty text-base leading-8 text-ink-500 sm:text-lg sm:leading-9">
                    {{ __('ui.download.subtitle') }}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    @php($windows = $byPlatform->get('windows', collect())->firstWhere('arch', 'x64') ?? $byPlatform->get('windows', collect())->first())
                    @if ($windows)
                        <a href="{{ asset('desktop/'.$windows['file']) }}" class="btn btn-primary h-12 px-6 text-[15px]" data-download-primary>
                            <x-icon name="download" class="size-4" />
                            {{ __('ui.download.download_for_windows') }}
                        </a>
                    @endif
                    <a href="{{ localized_route('docs') }}" class="btn btn-outline h-12 px-6 text-[15px]">
                        <x-icon name="book" class="size-4" />
                        {{ __('ui.nav.docs') }}
                    </a>
                </div>
                <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-ink-400">
                    <span class="inline-flex items-center gap-2"><x-icon name="shield" class="size-4 text-lime-dark" />{{ __('ui.download.unsigned_note') }}</span>
                    @if ($latestVersion !== '')
                        <span class="font-mono" dir="ltr">{{ __('ui.download.latest_version', ['version' => $latestVersion]) }}</span>
                    @endif
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-4 rounded-[2.5rem] bg-lime/10 blur-2xl"></div>
                <div class="relative overflow-hidden rounded-[2rem] border border-ink-950/10 bg-ink-950 p-6 text-paper shadow-float">
                    <div class="flex items-center justify-between border-b border-white/10 pb-4">
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-paper/40">{{ __('ui.download.available_now') }}</div>
                            <div class="mt-1 font-mono text-2xl text-lime" dir="ltr">{{ $latestVersion !== '' ? 'v'.$latestVersion : '—' }}</div>
                        </div>
                        <span class="grid size-10 place-items-center rounded-xl bg-lime/15 text-lime"><x-icon name="server" class="size-5" /></span>
                    </div>
                    <div class="mt-4 space-y-2.5">
                        @foreach (['windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux'] as $key => $label)
                            @php($entries = $byPlatform->get($key, collect()))
                            <div class="flex items-center justify-between rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm" data-platform-card="{{ $key }}">
                                <span class="font-medium">{{ $label }}</span>
                                @if ($entries->isNotEmpty())
                                    <span class="font-mono text-[11px] text-lime">{{ $entries->map(fn ($release) => strtoupper($release['arch'] ?? 'x64'))->implode(' · ') }}</span>
                                @else
                                    <span class="text-[11px] text-paper/35">{{ __('ui.download.coming_soon') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @php($latestNotes = $releases->first()['notes'] ?? null)
                    @if (! empty($latestNotes))
                        <p class="mt-4 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-[11px] leading-5 text-paper/70" data-release-notes>{{ $latestNotes }}</p>
                    @endif
                    <p class="mt-4 text-[11px] leading-5 text-paper/35">{{ __('ui.download.native_note') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container-x py-16 lg:py-24">
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ([
                ['windows' => ['Windows', 'monitor', 'windows']],
                ['macos' => ['macOS', 'sparkles', 'macos']],
                ['linux' => ['Linux', 'terminal', 'linux']],
            ] as $item)
                @php($key = array_key_first($item))
                @php([$label, $icon, $platform] = $item[$key])
                @php($entries = $byPlatform->get($key, collect()))
                <article class="card card-pad flex flex-col" data-platform-section="{{ $platform }}">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-ink-950 text-lime"><x-icon :name="$icon" class="size-5" /></span>
                        @if ($entries->isNotEmpty())
                            <span class="badge badge-green">{{ __('ui.download.available') }}</span>
                        @else
                            <span class="badge badge-gray">{{ __('ui.download.coming_soon') }}</span>
                        @endif
                    </div>
                    <h2 class="mt-5 text-lg font-semibold">{{ $label }}</h2>
                    @if ($entries->isEmpty())
                        <p class="mt-2 flex-1 text-sm leading-7 text-ink-500">{{ __('ui.download.coming_soon_body') }}</p>
                    @else
                        <p class="mt-2 text-sm leading-7 text-ink-500">{{ __('ui.download.platform_body') }}</p>
                        <div class="mt-4 space-y-2">
                            @foreach ($entries as $release)
                                <a href="{{ asset('desktop/'.$release['file']) }}" class="flex items-center justify-between gap-3 rounded-xl border border-ink-950/10 px-4 py-3 text-sm transition hover:border-ink-950/30" data-arch="{{ $release['arch'] ?? 'x64' }}">
                                    <span class="inline-flex items-center gap-2 font-medium">
                                        <x-icon name="download" class="size-4" />
                                        {{ $release['arch'] === 'arm64' ? __('ui.download.arm64') : __('ui.download.intel') }}
                                    </span>
                                    <span class="font-mono text-[11px] text-ink-400" dir="ltr">{{ isset($release['size']) ? \Illuminate\Support\Number::fileSize((int) $release['size']) : '' }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    @if ($releases->isNotEmpty())
        <section class="bg-paper-2/60 py-16 lg:py-24">
            <div class="container-x">
                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <span class="eyebrow">{{ __('ui.download.history') }}</span>
                        <h2 class="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('ui.download.history_title') }}</h2>
                    </div>
                    <p class="max-w-md text-xs leading-6 text-ink-400">{{ __('ui.download.verify_body') }}</p>
                </div>
                <div class="mt-8 overflow-hidden rounded-2xl border border-ink-950/10 bg-white">
                    @foreach ($releases as $release)
                        <div class="flex flex-wrap items-center gap-3 border-b border-ink-950/10 px-4 py-3 text-xs last:border-b-0">
                            <span class="font-mono font-medium" dir="ltr">v{{ $release['version'] }}</span>
                            <span class="badge badge-gray">{{ ucfirst($release['platform']) }} · {{ $release['arch'] ?? 'x64' }}</span>
                            <span class="text-ink-400">{{ $release['publishedAt'] ?? '' }}</span>
                            <a href="{{ asset('desktop/'.$release['file']) }}" class="ms-auto inline-flex items-center gap-1.5 font-medium text-ink-600 hover:text-ink-950">
                                <x-icon name="download" class="size-3.5" />
                                {{ __('ui.download.download') }}
                            </a>
                            @if (! empty($release['notes']))
                                <p class="w-full text-[11px] leading-5 text-ink-500" data-release-notes>{{ $release['notes'] }}</p>
                            @endif
                            @if (! empty($release['sha512']))
                                <details class="w-full text-[10px] text-ink-400">
                                    <summary class="cursor-pointer">{{ __('ui.download.sha') }}</summary>
                                    <code class="mt-1 block break-all font-mono" dir="ltr">{{ $release['sha512'] }}</code>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="container-x pb-16 pt-14">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-3xl border border-ink-950/10 bg-white p-6">
                <h2 class="text-lg font-semibold">{{ __('ui.download.requirements_title') }}</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-ink-950/5 pb-3">
                        <dt class="font-medium text-ink-700">{{ __('ui.download.req_os') }}</dt>
                        <dd class="text-xs text-ink-500">{{ __('ui.download.req_os_value') }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-ink-950/5 pb-3">
                        <dt class="font-medium text-ink-700">{{ __('ui.download.req_disk') }}</dt>
                        <dd class="text-xs text-ink-500">{{ __('ui.download.req_disk_value') }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-ink-950/5 pb-3">
                        <dt class="font-medium text-ink-700">{{ __('ui.download.req_account') }}</dt>
                        <dd class="text-xs text-ink-500">{{ __('ui.download.req_account_value') }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <dt class="font-medium text-ink-700">{{ __('ui.download.req_network') }}</dt>
                        <dd class="text-xs text-ink-500">{{ __('ui.download.req_network_value') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-3xl border border-ink-950/10 bg-paper-2/60 p-6">
                <h2 class="text-lg font-semibold">{{ __('ui.download.install_title') }}</h2>
                <ol class="mt-4 space-y-4 text-sm">
                    @foreach (['one', 'two', 'three', 'four'] as $index => $step)
                        <li class="flex gap-3">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-ink-950 font-mono text-[11px] text-paper">{{ $index + 1 }}</span>
                            <span class="leading-7 text-ink-600">{{ __('ui.download.install_'.$step) }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-5 text-[11px] leading-5 text-ink-400">{{ __('ui.download.update_note') }}</p>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-ink-950/10 bg-paper-2/60 p-5">
            <h3 class="text-sm font-semibold">{{ __('ui.download.first_launch_title') }}</h3>
            <p class="mt-2 max-w-3xl text-xs leading-6 text-ink-500">{{ __('ui.download.first_launch_body') }}</p>
        </div>

        <div class="mt-4 rounded-2xl border border-ink-950/10 bg-white p-5 text-xs leading-6 text-ink-500">
            {{ __('ui.download.install_note') }}
        </div>
    </section>

    <script>
        (() => {
            const platform = navigator.userAgentData?.platform || navigator.platform || "";
            const key = /mac/i.test(platform) ? "macos" : /linux/i.test(platform) ? "linux" : "windows";
            document.querySelectorAll('[data-platform-section]').forEach((node) => {
                node.classList.toggle('ring-2', node.getAttribute('data-platform-section') === key);
                node.classList.toggle('ring-lime-dark/40', node.getAttribute('data-platform-section') === key);
            });
            document.querySelectorAll('[data-platform-card]').forEach((node) => {
                node.classList.toggle('border-lime/40', node.getAttribute('data-platform-card') === key);
            });
        })();
    </script>
@endsection
