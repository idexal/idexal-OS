@php
    $direction = locale_direction();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.emails.team_invite_subject', ['organization' => $organization->name]) }}</title>
</head>
<body style="margin:0;padding:0;background:#f7f6f1;font-family:'IBM Plex Sans Arabic',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1b1d1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f6f1;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e4de;border-radius:24px;overflow:hidden;">
                    <tr>
                        <td style="background:#080a08;padding:24px 32px;">
                            <img src="{{ asset('brand/logo-dark.png') }}" alt="{{ config('idexal.brand.name') }}" height="34" style="height:34px;width:auto;display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px 12px;">
                            <p style="margin:0 0 10px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#8a8f88;">{{ __('ui.emails.team_invite_eyebrow') }}</p>
                            <h1 style="margin:0;font-size:24px;line-height:1.4;font-weight:700;color:#080a08;">{{ __('ui.emails.team_invite_title') }}</h1>
                            <p style="margin:16px 0 0;font-size:14px;line-height:1.9;color:#4b504a;">
                                {{ __('ui.emails.team_invite_body', ['inviter' => $inviter?->name ?? config('idexal.brand.name'), 'organization' => $organization->name]) }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f6f1;border-radius:16px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0;font-size:11px;color:#8a8f88;">{{ __('ui.emails.organization') }}</p>
                                        <p style="margin:4px 0 0;font-size:15px;font-weight:600;color:#080a08;">{{ $organization->name }}</p>
                                        <p style="margin:2px 0 0;font-size:11px;color:#6b7069;">{{ __('ui.emails.role') }}: {{ __('ui.roles.'.$invitation->role) }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px;" align="{{ $direction === 'rtl' ? 'right' : 'left' }}">
                            <a href="{{ $url }}" style="display:inline-block;background:#7dff00;color:#080a08;text-decoration:none;font-size:14px;font-weight:700;padding:14px 28px;border-radius:12px;">
                                {{ __('ui.emails.accept_invitation') }}
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 32px;">
                            <p style="margin:0;font-size:11px;line-height:1.8;color:#8a8f88;">{{ __('ui.emails.link_expiry', ['days' => 7]) }}</p>
                            <p style="margin:12px 0 0;font-size:10px;line-height:1.6;color:#a8aca6;word-break:break-all;">{{ $url }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #ecece7;padding:18px 32px;">
                            <p style="margin:0;font-size:10px;line-height:1.7;color:#a8aca6;">
                                {{ config('idexal.brand.name') }} · {{ config('idexal.brand.domain') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
