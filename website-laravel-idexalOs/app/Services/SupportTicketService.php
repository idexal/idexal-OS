<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Organization;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The support desk. It opens tickets from the dashboard and the public contact
 * form, threads the conversation between customer and staff, and hands new
 * tickets to the AI assistant for a draft reply. Nothing here sends an AI reply
 * to a customer unless the operator asked for automatic first responses.
 */
class SupportTicketService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
        private readonly SupportAiService $ai,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->boolean('support_enabled', true);
    }

    /**
     * @param  array{subject: string, body: string, category?: string, priority?: string, requester_name?: string, requester_email?: string, contact_message_id?: string|null, source?: string}  $attributes
     */
    public function open(
        array $attributes,
        ?User $author = null,
        ?Organization $organization = null,
    ): SupportTicket {
        $ticket = SupportTicket::query()->create([
            'reference' => $this->uniqueReference(),
            'user_id' => $author?->getKey(),
            'organization_id' => $organization?->getKey(),
            'contact_message_id' => $attributes['contact_message_id'] ?? null,
            'requester_name' => $attributes['requester_name'] ?? $author?->name ?? 'Guest',
            'requester_email' => $attributes['requester_email'] ?? $author?->email ?? '',
            'subject' => $attributes['subject'],
            'category' => $attributes['category'] ?? SupportTicket::CATEGORY_GENERAL,
            'priority' => $attributes['priority'] ?? SupportTicket::PRIORITY_NORMAL,
            'status' => SupportTicket::STATUS_OPEN,
            'source' => $attributes['source'] ?? SupportTicket::SOURCE_WEB,
            'body' => $attributes['body'],
        ]);

        $this->notifyAdmins(
            $ticket,
            __('ui.notifications.support_opened_title'),
            $ticket->subject,
        );

        AuditLog::record('support.ticket.opened', $ticket->organization, $ticket, $ticket->subject);

        $this->draftFirstReply($ticket, $author);

        return $ticket;
    }

    /**
     * A message from the public contact form becomes a ticket, once. This is
     * deliberately not gated by the support toggle: a contact submission must
     * always land somewhere an operator can see it.
     */
    public function openFromContact(ContactMessage $message): SupportTicket
    {
        $existing = SupportTicket::query()->where('contact_message_id', $message->getKey())->first();

        if ($existing instanceof SupportTicket) {
            return $existing;
        }

        return $this->open([
            'subject' => $message->subject,
            'body' => $message->message,
            'category' => SupportTicket::CATEGORY_GENERAL,
            'priority' => SupportTicket::PRIORITY_NORMAL,
            'requester_name' => $message->name,
            'requester_email' => $message->email,
            'contact_message_id' => $message->getKey(),
            'source' => SupportTicket::SOURCE_CONTACT,
        ]);
    }

    /**
     * @param  array{body: string, internal?: bool}  $data
     */
    public function reply(SupportTicket $ticket, User $author, array $data, string $authorType): SupportTicketReply
    {
        $internal = $authorType === SupportTicketReply::AUTHOR_STAFF && (bool) ($data['internal'] ?? false);

        $reply = $ticket->replies()->create([
            'user_id' => $author->getKey(),
            'author_type' => $authorType,
            'author_name' => $author->name,
            'body' => $data['body'],
            'is_internal' => $internal,
            'ai_generated' => false,
        ]);

        if ($internal) {
            return $reply;
        }

        if ($authorType === SupportTicketReply::AUTHOR_STAFF) {
            $ticket->forceFill([
                'first_response_at' => $ticket->first_response_at ?? now(),
                'last_reply_at' => now(),
                'status' => $ticket->isClosed() ? $ticket->status : SupportTicket::STATUS_PENDING,
            ])->save();

            $this->notifyRequester($ticket, __('ui.notifications.support_reply_title'), $ticket->subject);

            AuditLog::record('support.ticket.replied', $ticket->organization, $ticket, $ticket->subject);
        } else {
            $ticket->forceFill([
                'last_reply_at' => now(),
                'status' => SupportTicket::STATUS_OPEN,
            ])->save();

            $this->notifyAdmins($ticket, __('ui.notifications.support_customer_reply_title'), $ticket->subject);

            AuditLog::record('support.ticket.customer_replied', $ticket->organization, $ticket, $ticket->subject);
        }

        return $reply;
    }

    /**
     * Turn an internal draft (usually one the AI wrote) into a reply the
     * customer can see.
     */
    public function publishReply(SupportTicket $ticket, SupportTicketReply $reply, ?User $staff = null): SupportTicketReply
    {
        if (! $reply->isInternal()) {
            return $reply;
        }

        $reply->forceFill([
            'is_internal' => false,
            'user_id' => $reply->author_type === SupportTicketReply::AUTHOR_AI ? $staff?->getKey() : $reply->user_id,
        ])->save();

        $ticket->forceFill([
            'first_response_at' => $ticket->first_response_at ?? now(),
            'last_reply_at' => now(),
            'status' => $ticket->isClosed() ? $ticket->status : SupportTicket::STATUS_PENDING,
        ])->save();

        $this->notifyRequester($ticket, __('ui.notifications.support_reply_title'), $ticket->subject);

        AuditLog::record('support.ticket.replied', $ticket->organization, $ticket, $ticket->subject);

        return $reply;
    }

    public function changeStatus(SupportTicket $ticket, string $status): SupportTicket
    {
        $ticket->forceFill([
            'status' => $status,
            'resolved_at' => $status === SupportTicket::STATUS_RESOLVED ? ($ticket->resolved_at ?? now()) : null,
            'closed_at' => $status === SupportTicket::STATUS_CLOSED ? ($ticket->closed_at ?? now()) : null,
        ])->save();

        AuditLog::record('support.ticket.status', $ticket->organization, $ticket, $status);

        return $ticket;
    }

    public function assign(SupportTicket $ticket, ?User $assignee): SupportTicket
    {
        $ticket->forceFill(['assigned_to' => $assignee?->getKey()])->save();

        AuditLog::record('support.ticket.assigned', $ticket->organization, $ticket, $assignee?->name);

        return $ticket;
    }

    public function setPriority(SupportTicket $ticket, string $priority): SupportTicket
    {
        $ticket->forceFill(['priority' => $priority])->save();

        AuditLog::record('support.ticket.priority', $ticket->organization, $ticket, $priority);

        return $ticket;
    }

    public function setCategory(SupportTicket $ticket, string $category): SupportTicket
    {
        $ticket->forceFill(['category' => $category])->save();

        AuditLog::record('support.ticket.category', $ticket->organization, $ticket, $category);

        return $ticket;
    }

    /**
     * Ask the AI assistant for a fresh draft on demand.
     */
    public function generateDraft(SupportTicket $ticket, ?User $actor = null): ?SupportTicketReply
    {
        $text = $this->ai->draft($ticket);

        if ($text === null) {
            return null;
        }

        $reply = $this->ai->storeDraft($ticket, $text, [
            'provider_id' => $this->ai->provider()?->getKey(),
            'model_id' => $this->ai->model()?->getKey(),
        ]);

        AuditLog::record('support.ticket.ai_drafted', $ticket->organization, $ticket, $actor?->name);

        return $reply;
    }

    /**
     * @return array{total: int, open: int, unassigned: int, awaiting: int, resolved: int}
     */
    public function stats(?Organization $organization = null): array
    {
        $query = SupportTicket::query()
            ->when($organization instanceof Organization, fn ($builder) => $builder->where('organization_id', $organization->getKey()));

        $counts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $counts->sum(),
            'open' => (int) $counts->get(SupportTicket::STATUS_OPEN, 0),
            'unassigned' => (int) (clone $query)->whereNull('assigned_to')->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_PENDING])->count(),
            'awaiting' => (int) (clone $query)->whereNull('first_response_at')->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_PENDING])->count(),
            'resolved' => (int) ($counts->get(SupportTicket::STATUS_RESOLVED, 0) + $counts->get(SupportTicket::STATUS_CLOSED, 0)),
        ];
    }

    /**
     * The tickets a signed-in customer may see: their own, plus their
     * workspace's.
     *
     * @return Collection<int, SupportTicket>
     */
    public function forUser(User $user): Collection
    {
        $organizationIds = $user->memberships()
            ->where('status', 'active')
            ->pluck('organization_id')
            ->all();

        return SupportTicket::query()
            ->where(function ($query) use ($user, $organizationIds): void {
                $query->where('user_id', $user->getKey());

                if ($organizationIds !== []) {
                    $query->orWhereIn('organization_id', $organizationIds);
                }
            })
            ->latest('created_at')
            ->get();
    }

    /**
     * Whether a signed-in customer may open this ticket.
     */
    public function visibleTo(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->user_id === $user->getKey()) {
            return true;
        }

        if ($ticket->organization_id === null) {
            return false;
        }

        return $user->memberships()
            ->where('organization_id', $ticket->organization_id)
            ->where('status', 'active')
            ->exists();
    }

    private function draftFirstReply(SupportTicket $ticket, ?User $actor): void
    {
        if (! $this->ai->enabled()) {
            return;
        }

        $reply = $this->generateDraft($ticket, $actor);

        if ($reply instanceof SupportTicketReply && $this->ai->autoReply()) {
            $this->publishReply($ticket, $reply, $actor);
        }
    }

    private function notifyAdmins(SupportTicket $ticket, string $title, string $body): void
    {
        User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->each(function (User $admin) use ($ticket, $title, $body): void {
                $this->notifications->send(
                    $admin,
                    'support.ticket',
                    $title,
                    $body,
                    route('admin.support.show', $ticket),
                );
            });
    }

    private function notifyRequester(SupportTicket $ticket, string $title, string $body): void
    {
        $user = $ticket->user;

        if (! $user instanceof User) {
            return;
        }

        $this->notifications->send(
            $user,
            'support.reply',
            $title,
            $body,
            route('dashboard.support.show', $ticket),
            $ticket->organization,
        );
    }

    private function uniqueReference(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = 'T-'.Str::upper(Str::random(8));

            if (! SupportTicket::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        return 'T-'.Str::upper((string) Str::ulid());
    }
}
