@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-950/10 px-4 py-4" aria-label="pagination">
        <p class="text-xs text-ink-500">
            {{ __('ui.common.of') }}
            <span class="font-mono">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="btn btn-outline btn-sm pointer-events-none opacity-40">
                    <x-icon name="chevron-right" class="size-4 rtl:rotate-180" />
                    {{ __('ui.common.previous') }}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-outline btn-sm" rel="prev">
                    <x-icon name="chevron-right" class="size-4 rtl:rotate-180" />
                    {{ __('ui.common.previous') }}
                </a>
            @endif

            @php
                $start = max(1, $paginator->currentPage() - 2);
                $end = min($paginator->lastPage(), $paginator->currentPage() + 2);
            @endphp

            @if ($start > 1)
                <a href="{{ $paginator->url(1) }}" class="btn btn-ghost btn-sm font-mono">1</a>
                @if ($start > 2)
                    <span class="px-1 text-ink-400">…</span>
                @endif
            @endif

            @for ($page = $start; $page <= $end; $page++)
                @if ($page === $paginator->currentPage())
                    <span class="btn btn-primary btn-sm font-mono" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="btn btn-ghost btn-sm font-mono">{{ $page }}</a>
                @endif
            @endfor

            @if ($end < $paginator->lastPage())
                @if ($end < $paginator->lastPage() - 1)
                    <span class="px-1 text-ink-400">…</span>
                @endif
                <a href="{{ $paginator->url($paginator->lastPage()) }}" class="btn btn-ghost btn-sm font-mono">{{ $paginator->lastPage() }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-outline btn-sm" rel="next">
                    {{ __('ui.common.next') }}
                    <x-icon name="chevron-left" class="size-4 rtl:rotate-180" />
                </a>
            @else
                <span class="btn btn-outline btn-sm pointer-events-none opacity-40">
                    {{ __('ui.common.next') }}
                    <x-icon name="chevron-left" class="size-4 rtl:rotate-180" />
                </span>
            @endif
        </div>
    </nav>
@endif
