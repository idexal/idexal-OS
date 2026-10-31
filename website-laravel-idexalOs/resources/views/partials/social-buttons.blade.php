@if (($providers ?? []) !== [])
    <div class="grid gap-2.5 {{ count($providers) > 1 ? 'sm:grid-cols-2' : '' }}">
        @foreach ($providers as $key => $provider)
            <a href="{{ route('social.redirect', $key) }}" class="btn btn-outline h-11 gap-2.5" rel="nofollow">
                <x-social-icon :provider="$key" />
                <span class="truncate">{{ __('ui.auth.continue_with', ['provider' => $provider['label']]) }}</span>
            </a>
        @endforeach
    </div>

    <div class="my-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-ink-950/10"></span>
        <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-300">{{ __('ui.auth.or') }}</span>
        <span class="h-px flex-1 bg-ink-950/10"></span>
    </div>
@endif
