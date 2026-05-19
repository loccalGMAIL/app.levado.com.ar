<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitación a {$this->invitation->tenant->name} en Levado",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'acceptUrl' => route('invitations.accept', $this->invitation->token),
                'tenantName' => $this->invitation->tenant->name,
                'role' => $this->invitation->role->label(),
                'expiresAt' => $this->invitation->expires_at->format('d/m/Y H:i'),
                'customMessage' => $this->invitation->tenant->getSetting('invitation_message'),
            ],
        );
    }
}
