<?php

namespace App\Mail;

use App\Models\Appraisal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppraisalReadyForHrMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appraisal $appraisal, public User $recipient, public ?string $plainPassword = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->recipient->email, $this->recipient->name)],
            subject: "{$this->appraisal->title}: {$this->appraisal->employee->name} is ready for HR evaluation",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appraisals.ready-for-hr',
            with: [
                'url' => route('appraisal.form', $this->appraisal),
                'recipient' => $this->recipient,
            ],
        );
    }
}
