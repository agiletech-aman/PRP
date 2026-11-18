<x-mail::message>
# {{ $appraisal->title }}

Hi {{ $appraisal->manager->name }},

**{{ $appraisal->employee->name }}** has submitted their self-appraisal. Please review it and add your manager ratings.

Your login details:

@include('mail.appraisals._credentials')

<x-mail::button :url="$url">
Rate {{ $appraisal->employee->name }}
</x-mail::button>

Please keep your password private and change it from **My profile** after you sign in.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
