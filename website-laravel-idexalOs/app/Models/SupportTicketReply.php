<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a support conversation. A reply is written by the customer, a
 * staff member, or an AI assistant. AI replies start as internal drafts the
 * team can review before they become visible to the customer.
 */
class SupportTicketReply extends Model
{
    use HasUlids;

    public const AUTHOR_CUSTOMER = 'customer';

    public const AUTHOR_STAFF = 'staff';

    public const AUTHOR_AI = 'ai';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'author_type',
        'author_name',
        'body',
        'is_internal',
        'ai_generated',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'ai_generated' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    public function isAiGenerated(): bool
    {
        return $this->ai_generated;
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
