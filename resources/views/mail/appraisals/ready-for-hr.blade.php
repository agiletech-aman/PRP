<x-mail::message>
# {{ $appraisal->title }}

Hi {{ $recipient->name }},

**{{ $appraisal->employee->name }}** has completed the employee and manager rating stages. Please evaluate the appraisal and complete the HR evaluation.

Your login details:

@include('mail.appraisals._credentials')

<x-mail::button :url="$url">
Evaluate appraisal
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
