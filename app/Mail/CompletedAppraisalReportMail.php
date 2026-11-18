<?php

namespace App\Mail;

use App\Models\Appraisal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompletedAppraisalReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appraisal $appraisal,
        public array $csvRecipients,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: array_map(
                fn (string $email): Address => new Address($email),
                $this->csvRecipients,
            ),
            subject: "{$this->appraisal->title}: completed appraisal report for {$this->appraisal->employee->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appraisals.completed-report',
            with: ['appraisal' => $this->appraisal],
        );
    }

    public function attachments(): array
    {
        $appraisal = $this->appraisal->load(['employee.department', 'manager', 'department', 'ratings']);
        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [
            'Employee Name', 'Employee Code', 'Department', 'Manager',
            'Appraisal', 'Status', 'Self Rating', 'Manager Rating',
            'HR Rating', 'HR Remarks', 'Sent Date', 'Completed Date',
        ]);
        fputcsv($output, [
            $appraisal->employee->name,
            $appraisal->employee->employee_code,
            $appraisal->department?->name ?? $appraisal->employee->department?->name,
            $appraisal->manager?->name,
            $appraisal->title,
            $appraisal->status->label(),
            $appraisal->averageRating('employee'),
            $appraisal->averageRating('manager'),
            $appraisal->averageRating('hr'),
            $appraisal->hr_remarks,
            $appraisal->sent_at?->format('Y-m-d'),
            $appraisal->hr_submitted_at?->format('Y-m-d'),
        ]);
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return [
            Attachment::fromData(
                fn (): string => $csv,
                'appraisal-'.$appraisal->employee->employee_code.'-'.$appraisal->id.'.csv',
            )->withMime('text/csv'),
        ];
    }
}
