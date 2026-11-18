<?php

namespace App\Mail;

use App\Models\Appraisal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the reporting manager once the employee has submitted their self-rating.
 */
class AppraisalReadyForManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appraisal $appraisal) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $manager = $this->appraisal->manager;

        return new Envelope(
            to: [new Address($manager->email, $manager->name)],
            subject: "{$this->appraisal->title}: {$this->appraisal->employee->name} is ready for your rating",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appraisals.ready-for-manager',
            with: [
                'url' => route('appraisal.form', $this->appraisal),
            ],
        );
    }
}
