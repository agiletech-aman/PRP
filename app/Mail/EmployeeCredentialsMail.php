<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends an employee their Employee ID and a freshly generated password.
 */
class EmployeeCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $employee, public string $plainPassword) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->employee->email, $this->employee->name)],
            subject: 'Your login details for '.config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.employees.credentials',
            with: [
                'loginUrl' => route('login'),
            ],
        );
    }
}
