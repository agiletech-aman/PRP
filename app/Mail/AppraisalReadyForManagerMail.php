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
 * Sent to the reporting manager once the employee has submitted their self-rating, with their Employee ID
 * and login password.
 */
class AppraisalReadyForManagerMail extends Mailable
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
                'recipient' => $this->appraisal->manager,
            ],
        );
    }
}
