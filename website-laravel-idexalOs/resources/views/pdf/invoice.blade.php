@php
    $direction = locale_direction();
    $align = $direction === 'rtl' ? 'right' : 'left';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            color: #1b1d1a;
            line-height: 1.6;
        }
        .muted { color: #6b7069; }
        .small { font-size: 8pt; }
        .mono { font-family: dejavusansmono, monospace; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: top; padding: 0 0 18px; }
        .logo { height: 34px; }
        .title { font-size: 20pt; font-weight: bold; color: #080a08; margin: 0; }
        .badge { display: inline-block; padding: 2px 10px; border: 1px solid #c8cbc6; border-radius: 10px; font-size: 8pt; }
        .rule { border-top: 1px solid #d8dbd5; }
        .section { padding: 14px 0; }
        .label { font-size: 7.5pt; letter-spacing: .12em; text-transform: uppercase; color: #8a8f88; margin: 0 0 4px; }
        .items th { background: #f1f1ec; padding: 8px; font-size: 8pt; text-align: {{ $align }}; border-bottom: 1px solid #d8dbd5; }
        .items td { padding: 8px; font-size: 9pt; border-bottom: 1px solid #ecece7; }
        .totals td { padding: 4px 0; font-size: 9pt; }
        .totals .grand td { padding-top: 8px; border-top: 1px solid #d8dbd5; font-size: 11pt; font-weight: bold; }
        .notes { background: #f7f6f1; padding: 12px; font-size: 8.5pt; }
        .footer { margin-top: 18px; text-align: center; font-size: 7.5pt; color: #8a8f88; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 55%;">
                <img src="{{ public_path('brand/logo-light.png') }}" alt="{{ config('idexal.brand.name') }}" class="logo">
                <p class="small muted" style="margin: 8px 0 0; line-height: 1.7;">
                    {{ config('idexal.brand.legal_name') }}<br>
                    {{ config('idexal.brand.domain') }}<br>
                    {{ config('idexal.brand.support_email') }}
                </p>
            </td>
            <td style="width: 45%; text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};">
                <p class="title mono">{{ $invoice->number }}</p>
                <p style="margin: 6px 0 0;"><span class="badge">{{ __('ui.billing.invoice_statuses.'.$invoice->status) }}</span></p>
                <p class="small muted" style="margin: 10px 0 0;">
                    {{ __('ui.billing.issued') }}: {{ $invoice->issued_at?->translatedFormat('d M Y') ?? '—' }}<br>
                    {{ __('ui.billing.due') }}: {{ $invoice->due_at?->translatedFormat('d M Y') ?? '—' }}
                </p>
            </td>
        </tr>
    </table>

    <table class="rule section">
        <tr>
            <td style="width: 60%; vertical-align: top; padding-top: 14px;">
                <p class="label">{{ __('ui.billing.billed_to') }}</p>
                <strong>{{ $organization->name }}</strong><br>
                <span class="small muted">
                    @if ($organization->address_line_1)
                        {{ $organization->address_line_1 }}<br>
                    @endif
                    @if ($organization->city || $organization->country)
                        {{ collect([$organization->city, $organization->country])->filter()->implode(', ') }}<br>
                    @endif
                    @if ($organization->billing_email)
                        {{ $organization->billing_email }}<br>
                    @endif
                    @if ($organization->tax_id)
                        {{ __('ui.settings.tax_id') }}: {{ $organization->tax_id }}
                    @endif
                </span>
            </td>
            <td style="width: 40%; vertical-align: top; padding-top: 14px; text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};">
                <p class="label">{{ __('ui.billing.balance_due') }}</p>
                <p class="mono" style="font-size: 18pt; font-weight: bold; margin: 0;">{{ $invoice->formattedBalance() }}</p>
                <p class="small muted" style="margin: 2px 0 0;">{{ $invoice->currency }}</p>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 52%;">{{ __('ui.common.description') }}</th>
                <th style="width: 12%; text-align: center;">{{ __('ui.billing.quantity') }}</th>
                <th style="width: 18%; text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};">{{ __('ui.billing.unit_price') }}</th>
                <th style="width: 18%; text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};">{{ __('ui.common.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td style="text-align: center;" class="mono">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ','), '0'), '.') }}</td>
                    <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};" class="mono">{{ money((float) $item->unit_price, $invoice->currency) }}</td>
                    <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};" class="mono">{{ money((float) $item->amount, $invoice->currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="section">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                @if ($invoice->notes)
                    <div class="notes">
                        <p class="label">{{ __('ui.billing.notes') }}</p>
                        <p style="margin: 0;">{{ $invoice->notes }}</p>
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="totals" style="margin-{{ $direction === 'rtl' ? 'right' : 'left' }}: auto; width: 80%;">
                    <tr>
                        <td class="muted">{{ __('ui.common.subtotal') }}</td>
                        <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};" class="mono">{{ money((float) $invoice->subtotal, $invoice->currency) }}</td>
                    </tr>
                    @if ((float) $invoice->discount > 0)
                        <tr>
                            <td class="muted">{{ __('ui.common.discount') }}</td>
                            <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};" class="mono">− {{ money((float) $invoice->discount, $invoice->currency) }}</td>
                        </tr>
                    @endif
                    @if ((float) $invoice->tax > 0)
                        <tr>
                            <td class="muted">{{ __('ui.common.tax') }}</td>
                            <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};" class="mono">{{ money((float) $invoice->tax, $invoice->currency) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>{{ __('ui.common.total') }}</td>
                        <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};" class="mono">{{ $invoice->formattedTotal() }}</td>
                    </tr>
                    @if ((float) $invoice->amount_paid > 0)
                        <tr>
                            <td class="muted">{{ __('ui.common.paid') }}</td>
                            <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};" class="mono">{{ money((float) $invoice->amount_paid, $invoice->currency) }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">{{ __('ui.billing.balance_due') }}</td>
                            <td style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }}; font-weight: bold;" class="mono">{{ $invoice->formattedBalance() }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if ($invoice->payments->isNotEmpty())
        <table class="section" style="border-top: 1px solid #d8dbd5;">
            <tr>
                <td>
                    <p class="label" style="padding-top: 10px;">{{ __('ui.billing.payments') }}</p>
                    @foreach ($invoice->payments as $payment)
                        <p class="small" style="margin: 2px 0;">
                            {{ $payment->paid_at?->translatedFormat('d M Y') ?? $payment->created_at?->translatedFormat('d M Y') }}
                            · {{ ucfirst($payment->gateway) }}
                            · <span class="mono">{{ money((float) $payment->amount, $payment->currency) }}</span>
                            @if ($payment->gateway_reference)
                                · <span class="mono">{{ $payment->gateway_reference }}</span>
                            @endif
                        </p>
                    @endforeach
                </td>
            </tr>
        </table>
    @endif

    <p class="footer">
        {{ config('idexal.brand.name') }} · {{ config('idexal.brand.domain') }} · {{ config('idexal.brand.support_email') }}
    </p>
</body>
</html>
