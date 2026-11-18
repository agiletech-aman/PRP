<x-mail::message>
# {{ $appraisal->title }}

Hi {{ $appraisal->employee->name }},

Your performance appraisal is now open. Please rate yourself on each factor out of {{ \App\Models\Appraisal::MAX_RATING }}.

@if ($appraisal->manager)
Once you submit, your reporting manager **{{ $appraisal->manager->name }}** (in CC) will add their ratings on the same form.
@endif

<x-mail::button :url="$url">
Open appraisal form
</x-mail::button>

You will be asked to sign in first. Use your Employee ID or email address and your portal password.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
