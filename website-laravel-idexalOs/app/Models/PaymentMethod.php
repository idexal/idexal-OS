<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasUlids;

    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    public const TYPE_CARD = 'card';

    public const TYPE_CASH = 'cash';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'type',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'holder_name',
        'gateway',
        'gateway_reference',
        'is_default',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_CARD => trim(($this->brand ?? __('ui.billing.card')).' •••• '.$this->last4),
            self::TYPE_BANK_TRANSFER => __('ui.billing.bank_transfer'),
            default => __('ui.billing.cash'),
        };
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
