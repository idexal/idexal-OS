<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public Invitation $invitation,
        public string $token,
        public ?User $inviter = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('ui.emails.team_invite_subject', ['organization' => $this->organization->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'organization' => $this->organization,
                'invitation' => $this->invitation,
                'inviter' => $this->inviter,
                'url' => route('invitations.show', $this->token),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
