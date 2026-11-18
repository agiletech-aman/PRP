<x-mail::message>
# {{ $appraisal->title }}

Hi {{ $appraisal->employee->name }},

Your performance appraisal is now open. Please rate yourself on each factor out of {{ \App\Models\Appraisal::MAX_RATING }}.

@if ($appraisal->manager)
Once you submit, your reporting manager **{{ $appraisal->manager->name }}** will add their ratings on the same form.
@endif

Your login details:

@include('mail.appraisals._credentials')

<x-mail::button :url="$url">
Open appraisal form
</x-mail::button>

Please keep your password private and change it from **My profile** after you sign in.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
