@php
    /**
     * The expanded metadata of one audit entry. A save that records before/after
     * fields (settings pages, gateway features, provider pins) reads as a small
     * table; anything free-form keeps the raw JSON view.
     *
     * @var \App\Models\AuditLog $log
     */
    $sections = $log->changeSections();
    $other = $log->otherMeta();

    $format = function (mixed $value): string {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? __('ui.common.yes') : __('ui.common.no'),
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => \Illuminate\Support\Str::limit((string) $value, 90),
        };
    };

    $fieldLabel = fn (string $key): ?string => \App\Support\AuditFieldLabel::for($key);
@endphp

<div class="space-y-3">
    @foreach ($sections as $section => $rows)
        <div class="overflow-hidden rounded-xl bg-white">
            <table class="w-full text-start text-[11px]">
                <thead>
                    <tr class="border-b border-ink-950/5 text-[10px] uppercase tracking-wide text-ink-400">
                        <th class="p-2 text-start font-medium">{{ __('ui.admin.audit.field') }}</th>
                        <th class="p-2 text-start font-medium">{{ __('ui.admin.audit.from') }}</th>
                        <th class="p-2 text-start font-medium">{{ __('ui.admin.audit.to') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $change)
                        <tr class="border-b border-ink-950/5 last:border-0">
                            <td class="p-2">
                                @php($label = $fieldLabel((string) $key))
                                <span class="text-xs text-ink-600">{{ $label ?? $key }}</span>
                                @if ($label !== null)
                                    <span class="block font-mono text-[9px] text-ink-300" dir="ltr">{{ $key }}</span>
                                @endif
                            </td>
                            <td class="p-2 font-mono text-[10px] text-ink-400" dir="ltr">{{ $format($change['from'] ?? null) }}</td>
                            <td class="p-2 font-mono text-[10px] text-ink-700" dir="ltr">{{ $format($change['to'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @if ($other !== [])
        <pre class="max-h-64 overflow-auto whitespace-pre-wrap break-all rounded-xl bg-white p-4 font-mono text-[10px] leading-5 text-ink-500" dir="ltr">{{ json_encode($other, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    @endif
</div>
