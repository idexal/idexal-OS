<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    public const GATEWAY_MANUAL = 'manual';

    public const GATEWAY_STRIPE = 'stripe';

    public const GATEWAY_PAYPAL = 'paypal';

    public const GATEWAY_BANK_TRANSFER = 'bank_transfer';

    public const GATEWAY_WALLET = 'wallet';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * Every gateway a payment row can name, for admin filters and validation.
     *
     * @return list<string>
     */
    public static function gateways(): array
    {
        return [
            self::GATEWAY_STRIPE,
            self::GATEWAY_PAYPAL,
            self::GATEWAY_BANK_TRANSFER,
            self::GATEWAY_MANUAL,
            self::GATEWAY_WALLET,
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function gatewayLabel(): string
    {
        return match ($this->gateway) {
            self::GATEWAY_STRIPE => 'Stripe',
            self::GATEWAY_PAYPAL => 'PayPal',
            self::GATEWAY_BANK_TRANSFER => __('ui.billing.bank_transfer'),
            self::GATEWAY_MANUAL => __('ui.billing.manual'),
            self::GATEWAY_WALLET => __('ui.billing.wallet'),
            default => ucfirst($this->gateway),
        };
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'organization_id',
        'user_id',
        'gateway',
        'gateway_reference',
        'amount',
        'currency',
        'status',
        'paid_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
