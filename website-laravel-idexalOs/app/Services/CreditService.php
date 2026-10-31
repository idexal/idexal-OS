<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditService
{
    public function add(
        Organization $organization,
        float $amount,
        string $description,
        ?User $user = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($organization, $amount, $description, $user, $referenceType, $referenceId): CreditTransaction {
            $locked = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $balance = (float) $locked->credit_balance + $amount;
            $locked->forceFill(['credit_balance' => $balance])->save();

            return CreditTransaction::query()->create([
                'organization_id' => $locked->getKey(),
                'user_id' => $user?->getKey(),
                'type' => CreditTransaction::TYPE_CREDIT,
                'amount' => $amount,
                'balance_after' => $balance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    public function debit(
        Organization $organization,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($organization, $amount, $description, $referenceType, $referenceId): CreditTransaction {
            $locked = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $balance = (float) $locked->credit_balance;

            if ($balance + 0.000001 < $amount) {
                throw new InvalidArgumentException('Insufficient credit balance.');
            }

            $newBalance = $balance - $amount;
            $locked->forceFill(['credit_balance' => $newBalance])->save();

            return CreditTransaction::query()->create([
                'organization_id' => $locked->getKey(),
                'user_id' => null,
                'type' => CreditTransaction::TYPE_DEBIT,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }
}
