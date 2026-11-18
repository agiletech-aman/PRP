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
 * Sent only to the employee when HR opens an appraisal, with their Employee ID and login password.
 */
class AppraisalInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $plainPassword  A newly issued password, or null when the employee keeps their current one.
     */
    public function __construct(public Appraisal $appraisal, public ?string $plainPassword = null) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $employee = $this->appraisal->employee;

        return new Envelope(
            to: [new Address($employee->email, $employee->name)],
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
                'url' => route('appraisal.form', $this->appraisal),
                'recipient' => $this->appraisal->employee,
            ],
        );
    }
}
