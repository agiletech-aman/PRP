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
 * Sent only to the reporting manager when an appraisal opens. It carries no login details: those come with
 * {@see AppraisalReadyForManagerMail} once the employee has submitted and it is the manager's turn.
 */
class AppraisalManagerNoticeMail extends Mailable
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
            subject: "{$this->appraisal->title}: appraisal started for {$this->appraisal->employee->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appraisals.manager-notice',
        );
    }
}
