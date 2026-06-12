<?php

namespace App\Mail;

use App\Models\MailTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        $tpl = MailTemplate::forType('welcome');
        $subject = $tpl?->subject ?? "Bienvenido/a a {$this->tenant->name} en Levado";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $tpl = MailTemplate::forType('welcome');

        return new Content(
            view: 'emails.welcome',
            with: [
                'userName' => $this->user->name,
                'tenantName' => $this->tenant->name,
                'loginUrl' => route('login'),
                'introText' => $tpl?->intro_text,
                'footerNote' => $tpl?->footer_note ?? 'Que tu panadería siga creciendo.',
            ],
        );
    }
}
