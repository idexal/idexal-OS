<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The referral programme: who invited whom, when an invite becomes a real
 * customer, and how the reward reaches the referrer's wallet. Every rule comes
 * from operator settings, so the programme can be switched off or repriced
 * without a deploy.
 *
 * Rewards land in the wallet the rest of billing already reads
 * (`organizations.credit_balance` through {@see CreditService}), which is why
 * the ledger and the invoice page need no special cases.
 */
class ReferralService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CreditService $credits,
        private readonly NotificationService $notifications,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->boolean('referral_enabled', true);
    }

    public function rewardAmount(): float
    {
        return round(max(0.0, (float) $this->settings->get('referral_reward_amount', 0)), 6);
    }

    public function refereeBonus(): float
    {
        return round(max(0.0, (float) $this->settings->get('referral_referee_bonus', 0)), 6);
    }

    public function currency(): string
    {
        $currency = trim((string) $this->settings->get('referral_reward_currency'));

        if ($currency === '') {
            $currency = (string) $this->settings->get('default_currency', 'USD');
        }

        return Str::upper(substr($currency, 0, 3));
    }

    public function autoReward(): bool
    {
        return $this->settings->boolean('referral_auto_reward', true);
    }

    public function attributionDays(): int
    {
        return max(1, $this->settings->integer('referral_cookie_days', 30));
    }

    /**
     * The shareable code for an account, generated once and then reused so a
     * link someone already shared never stops working.
     */
    public function codeFor(User $user): string
    {
        $existing = trim((string) $user->referral_code);

        if ($existing !== '') {
            return $existing;
        }

        do {
            $code = Str::upper(Str::random(10));
        } while (User::withTrashed()->where('referral_code', $code)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function linkFor(User $user): string
    {
        return route('referrals.track', ['code' => $this->codeFor($user)]);
    }

    public function referrerForCode(?string $code): ?User
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        return User::query()->where('referral_code', $code)->first();
    }

    /**
     * Record that someone signed up through a code. Silently does nothing when
     * the programme is off, the code is unknown, the person is inviting
     * themselves, or they were already referred.
     */
    public function attribute(User $referred, ?string $code): ?Referral
    {
        if (! $this->enabled()) {
            return null;
        }

        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $referrer = $this->referrerForCode($code);

        if (! $referrer instanceof User || $referrer->getKey() === $referred->getKey()) {
            return null;
        }

        if (Referral::query()->where('referred_user_id', $referred->getKey())->exists()) {
            return null;
        }

        $referral = Referral::query()->create([
            'referrer_user_id' => $referrer->getKey(),
            'referrer_organization_id' => $this->walletFor($referrer)?->getKey(),
            'referred_user_id' => $referred->getKey(),
            'referred_organization_id' => $this->walletFor($referred)?->getKey(),
            'code' => $code,
            'status' => Referral::STATUS_PENDING,
            'reward_amount' => $this->rewardAmount(),
            'reward_currency' => $this->currency(),
        ]);

        AuditLog::record(
            'referral.created',
            $referral->referrerOrganization,
            $referral,
            __('ui.admin.referrals.created_log', ['name' => $referred->name]),
        );

        return $referral;
    }

    /**
     * An invited account paid something, so the invite has become a customer.
     * With auto-reward on this immediately credits the referrer; otherwise the
     * referral waits for an operator to approve it.
     */
    public function qualifyForPaidInvoice(Invoice $invoice): ?Referral
    {
        if (! $this->enabled()) {
            return null;
        }

        $referral = Referral::query()
            ->where('referred_organization_id', $invoice->organization_id)
            ->where('status', Referral::STATUS_PENDING)
            ->first();

        if (! $referral instanceof Referral) {
            return null;
        }

        return $this->qualify($referral, $invoice);
    }

    public function qualify(Referral $referral, ?Invoice $invoice = null): Referral
    {
        if (! $referral->isPending()) {
            return $referral;
        }

        $metadata = $referral->metadata ?? [];

        if ($invoice instanceof Invoice) {
            $metadata['qualified_invoice'] = $invoice->number;
        }

        $referral->forceFill([
            'status' => Referral::STATUS_QUALIFIED,
            'qualified_at' => now(),
            'metadata' => $metadata,
        ])->save();

        AuditLog::record(
            'referral.qualified',
            $referral->referrerOrganization,
            $referral,
            $referral->referred?->name,
        );

        if ($this->autoReward() && ($this->rewardAmount() > 0 || $this->refereeBonus() > 0)) {
            return $this->reward($referral);
        }

        return $referral;
    }

    /**
     * Credit the referrer (and optionally the invited customer) and close the
     * referral. Safe to call twice: a referral that is already rewarded or
     * rejected is returned untouched.
     */
    public function reward(Referral $referral, ?User $actor = null): Referral
    {
        return DB::transaction(function () use ($referral, $actor): Referral {
            $locked = Referral::query()
                ->whereKey($referral->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isRewarded() || $locked->status === Referral::STATUS_REJECTED) {
                return $locked;
            }

            $locked->loadMissing(['referrer', 'referred']);
            $currency = (string) ($locked->reward_currency ?: $this->currency());
            $amount = (float) $locked->reward_amount;

            if ($amount > 0 && $locked->referrer instanceof User) {
                $wallet = $this->walletFor($locked->referrer);

                if ($wallet instanceof Organization) {
                    $this->credits->add(
                        $wallet,
                        $amount,
                        __('ui.referrals.reward_line', ['name' => $locked->referred?->name ?? '']),
                        $locked->referrer,
                        'referral',
                        $locked->getKey(),
                    );
                }
            }

            $bonus = $this->refereeBonus();

            if ($bonus > 0 && $locked->referred instanceof User) {
                $referredWallet = $this->walletFor($locked->referred);

                if ($referredWallet instanceof Organization) {
                    $this->credits->add(
                        $referredWallet,
                        $bonus,
                        __('ui.referrals.bonus_line'),
                        $locked->referred,
                        'referral',
                        $locked->getKey(),
                    );
                }
            }

            $locked->forceFill([
                'status' => Referral::STATUS_REWARDED,
                'rewarded_at' => now(),
                'metadata' => array_merge($locked->metadata ?? [], [
                    'rewarded_by' => $actor?->getKey(),
                ]),
            ])->save();

            AuditLog::record(
                'referral.rewarded',
                $locked->referrerOrganization,
                $locked,
                (string) $amount,
            );

            if ($locked->referrer instanceof User && $amount > 0) {
                $this->notifications->send(
                    $locked->referrer,
                    'referral.rewarded',
                    __('ui.notifications.referral_reward_title'),
                    __('ui.notifications.referral_reward_body', [
                        'amount' => money($amount, $currency),
                        'name' => $locked->referred?->name ?? '',
                    ]),
                    route('dashboard.referrals'),
                    $locked->referrerOrganization,
                );
            }

            return $locked;
        });
    }

    public function reject(Referral $referral, ?User $actor = null, ?string $reason = null): Referral
    {
        if ($referral->isRewarded()) {
            return $referral;
        }

        $referral->forceFill([
            'status' => Referral::STATUS_REJECTED,
            'rejected_at' => now(),
            'metadata' => array_merge($referral->metadata ?? [], [
                'rejected_by' => $actor?->getKey(),
                'rejected_reason' => $reason,
            ]),
        ])->save();

        AuditLog::record('referral.rejected', $referral->referrerOrganization, $referral, $reason);

        return $referral;
    }

    /**
     * @return array{pending: int, qualified: int, rewarded: int, total: int}
     */
    public function stats(User $user): array
    {
        $rows = Referral::query()
            ->where('referrer_user_id', $user->getKey())
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pending = (int) ($rows[Referral::STATUS_PENDING] ?? 0);
        $qualified = (int) ($rows[Referral::STATUS_QUALIFIED] ?? 0);
        $rewarded = (int) ($rows[Referral::STATUS_REWARDED] ?? 0);

        return [
            'pending' => $pending,
            'qualified' => $qualified,
            'rewarded' => $rewarded,
            'total' => $pending + $qualified + $rewarded + (int) ($rows[Referral::STATUS_REJECTED] ?? 0),
        ];
    }

    /**
     * @return Collection<int, Referral>
     */
    public function recentFor(User $user, int $limit = 25): Collection
    {
        return Referral::query()
            ->with('referred')
            ->where('referrer_user_id', $user->getKey())
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{total: int, pending: int, qualified: int, rewarded: int, rejected: int, credited: float}
     */
    public function totals(): array
    {
        $rows = Referral::query()
            ->selectRaw('status, count(*) as aggregate, sum(reward_amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $rewarded = $rows->get(Referral::STATUS_REWARDED);

        return [
            'total' => (int) $rows->sum('aggregate'),
            'pending' => (int) ($rows->get(Referral::STATUS_PENDING)?->aggregate ?? 0),
            'qualified' => (int) ($rows->get(Referral::STATUS_QUALIFIED)?->aggregate ?? 0),
            'rewarded' => (int) ($rewarded?->aggregate ?? 0),
            'rejected' => (int) ($rows->get(Referral::STATUS_REJECTED)?->aggregate ?? 0),
            'credited' => (float) ($rewarded?->amount ?? 0),
        ];
    }

    /**
     * The wallet a user's rewards are paid into: their personal workspace,
     * falling back to any workspace they own.
     */
    public function walletFor(User $user): ?Organization
    {
        return Organization::query()
            ->where('owner_id', $user->getKey())
            ->orderByRaw('case when type = ? then 0 else 1 end', [Organization::TYPE_PERSONAL])
            ->orderBy('created_at')
            ->first();
    }
}
