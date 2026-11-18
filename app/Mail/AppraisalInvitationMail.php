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
 * Sent to the employee (with their reporting manager in CC) when HR opens an appraisal.
 */
class AppraisalInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appraisal $appraisal) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $employee = $this->appraisal->employee;
        $manager = $this->appraisal->manager;

        return new Envelope(
            to: [new Address($employee->email, $employee->name)],
            cc: $manager ? [new Address($manager->email, $manager->name)] : [],
            subject: "{$this->appraisal->title}: please complete your self-appraisal",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appraisals.invitation',
            with: [
                'url' => route('admin.appraisals.show', $this->appraisal),
            ],
        );
    }
}
