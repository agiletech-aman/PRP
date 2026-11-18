<x-mail::message>
# {{ $appraisal->title }}

Hi {{ $appraisal->manager->name }},

An appraisal has been started for **{{ $appraisal->employee->name }}** ({{ $appraisal->employee->employee_code }}), who reports to you.

They will rate themselves first. You will get another mail when it is your turn to add your manager ratings.

Your login details:

@include('mail.appraisals._credentials')

<x-mail::button :url="$url">
View appraisal
</x-mail::button>

Please keep your password private and change it from **My profile** after you sign in.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
