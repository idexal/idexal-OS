<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request for help: a question, a billing problem, or a complaint. Signed-in
 * customers open tickets from their workspace; visitors who use the contact
 * form get one too. Staff answer, hand off, and close them from the admin
 * support queue.
 */
class SupportTicket extends Model
{
    use HasUlids;

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_BILLING = 'billing';

    public const CATEGORY_TECHNICAL = 'technical';

    public const CATEGORY_COMPLAINT = 'complaint';

    public const CATEGORY_ACCOUNT = 'account';

    public const CATEGORY_OTHER = 'other';

    public const SOURCE_WEB = 'web';

    public const SOURCE_CONTACT = 'contact';

    public const SOURCE_DESKTOP = 'desktop';

    public const SOURCE_EMAIL = 'email';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'user_id',
        'organization_id',
        'contact_message_id',
        'assigned_to',
        'requester_name',
        'requester_email',
        'subject',
        'category',
        'priority',
        'status',
        'source',
        'body',
        'last_reply_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_PENDING,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function priorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_GENERAL,
            self::CATEGORY_BILLING,
            self::CATEGORY_TECHNICAL,
            self::CATEGORY_COMPLAINT,
            self::CATEGORY_ACCOUNT,
            self::CATEGORY_OTHER,
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_PENDING], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);
    }

    public function hasFirstResponse(): bool
    {
        return $this->first_response_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<ContactMessage, $this>
     */
    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<SupportTicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)->orderBy('created_at');
    }

    /**
     * The conversation the customer is allowed to see.
     *
     * @return HasMany<SupportTicketReply, $this>
     */
    public function publicReplies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)
            ->where('is_internal', false)
            ->orderBy('created_at');
    }
}
