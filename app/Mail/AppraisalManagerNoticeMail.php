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
 * Sent only to the reporting manager when an appraisal opens, with their own Employee ID and login password.
 */
class AppraisalManagerNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $plainPassword  A newly issued password, or null when the manager keeps their current one.
     */
    public function __construct(public Appraisal $appraisal, public ?string $plainPassword = null) {}

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
            with: [
                'url' => route('appraisal.form', $this->appraisal),
                'recipient' => $this->appraisal->manager,
            ],
        );
    }
}
