<x-mail::message>
# Appraisal completed

**{{ $appraisal->employee->name }}** has completed HR evaluation.

- Appraisal: {{ $appraisal->title }}
- Employee: {{ $appraisal->employee->name }} ({{ $appraisal->employee->employee_code }})
- Manager: {{ $appraisal->manager?->name ?? '—' }}
- Status: {{ $appraisal->status->label() }}
- HR rating: {{ $appraisal->averageRating('hr') ?? '—' }}

The completed appraisal report CSV is attached.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
