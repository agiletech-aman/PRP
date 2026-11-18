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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One summary per HR user for each batch of appraisals sent, with their own Employee ID and login password.
 */
class AppraisalHrNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Appraisal>  $appraisals
     * @param  string|null  $plainPassword  A newly issued password, or null when the HR user keeps their current one.
     */
    public function __construct(public User $hr, public Collection $appraisals, public ?string $plainPassword = null) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = $this->appraisals->count();

        return new Envelope(
            to: [new Address($this->hr->email, $this->hr->name)],
            subject: "{$this->appraisals->first()->title}: appraisals sent to {$count} ".Str::plural('employee', $count),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appraisals.hr-notice',
            with: [
                'url' => route('admin.appraisals.index'),
                'recipient' => $this->hr,
            ],
        );
    }
}
