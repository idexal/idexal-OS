<?php

namespace App\Services;

use App\Exceptions\DesktopHandoffException;
use App\Models\ApiKey;
use App\Models\DesktopHandoff;
use App\Models\DesktopSession;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DesktopAuthService
{
    /**
     * @return array{handoff: DesktopHandoff, grant: string}
     */
    public function issueHandoff(
        User $user,
        Organization $organization,
        Request $request,
        ?string $deviceName = null,
    ): array {
        $grant = 'dh_'.Str::random(56);

        $handoff = DesktopHandoff::query()->create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization->getKey(),
            'grant_hash' => hash('sha256', $grant),
            'device_name' => $deviceName ?: $this->detectDeviceName($request),
            'expires_at' => now()->addMinutes((int) config('idexal.desktop.handoff_ttl_minutes', 10)),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        return [
            'handoff' => $handoff,
            'grant' => $grant,
        ];
    }

    /**
     * @return array{user: User, organization: Organization, token: string, apiKey: ApiKey, session: DesktopSession}
     */
    public function exchange(string $grant, Request $request): array
    {
        return DB::transaction(function () use ($grant, $request): array {
            $handoff = DesktopHandoff::query()
                ->where('grant_hash', hash('sha256', $grant))
                ->lockForUpdate()
                ->first();

            if (! $handoff instanceof DesktopHandoff) {
                throw new DesktopHandoffException(__('ui.errors.invalid_grant'), 404);
            }

            if ($handoff->consumed_at !== null) {
                throw new DesktopHandoffException(__('ui.errors.grant_used'), 410);
            }

            if ($handoff->expires_at->isPast()) {
                throw new DesktopHandoffException(__('ui.errors.grant_expired'), 410);
            }

            $user = $handoff->user;
            $organization = $handoff->organization;

            if (! $user instanceof User || $user->isSuspended()) {
                throw new DesktopHandoffException(__('ui.errors.account_suspended'), 403);
            }

            if (! $organization instanceof Organization || $organization->isSuspended()) {
                throw new DesktopHandoffException(__('ui.errors.organization_suspended'), 403);
            }

            $membership = $organization->membershipFor($user);

            if ($membership === null || ! $membership->isActive()) {
                throw new DesktopHandoffException(__('ui.errors.not_a_member'), 403);
            }

            $handoff->forceFill(['consumed_at' => now()])->save();

            [$apiKey, $token] = ApiKey::issue(
                $organization,
                $user,
                'Desktop — '.(string) $handoff->device_name,
                [
                    ApiKey::ABILITY_DESKTOP,
                    ApiKey::ABILITY_GATEWAY_CHAT,
                    ApiKey::ABILITY_GATEWAY_MODELS,
                ],
                now()->addDays((int) config('idexal.desktop.token_days', 365)),
            );

            $session = DesktopSession::query()->create([
                'user_id' => $user->getKey(),
                'organization_id' => $organization->getKey(),
                'api_key_id' => $apiKey->getKey(),
                'device_name' => (string) $handoff->device_name,
                'platform' => $request->header('X-Desktop-Platform'),
                'app_version' => $request->header('X-Desktop-Version'),
                'last_seen_at' => now(),
                'ip' => $request->ip(),
            ]);

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            return [
                'user' => $user,
                'organization' => $organization,
                'token' => $token,
                'apiKey' => $apiKey,
                'session' => $session,
            ];
        });
    }

    public function revoke(DesktopSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $session->forceFill(['revoked_at' => now()])->save();
            $session->apiKey?->revoke();
        });
    }

    public function deepLink(string $grant, string $baseUrl, ?string $scheme = null): string
    {
        $scheme = $scheme ?: (string) config('idexal.desktop.scheme', 'idexalos');

        if (! preg_match('/^[a-z][a-z0-9-]{1,30}$/', $scheme)) {
            $scheme = (string) config('idexal.desktop.scheme', 'idexalos');
        }

        return $scheme.'://den-auth?'.http_build_query([
            'grant' => $grant,
            'denBaseUrl' => rtrim($baseUrl, '/'),
        ]);
    }

    private function detectDeviceName(Request $request): string
    {
        $agent = (string) $request->userAgent();

        return match (true) {
            str_contains($agent, 'Windows') => 'Windows desktop',
            str_contains($agent, 'Macintosh') => 'macOS desktop',
            str_contains($agent, 'Linux') => 'Linux desktop',
            default => 'idexal Os desktop',
        };
    }
}
