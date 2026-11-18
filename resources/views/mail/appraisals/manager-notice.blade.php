<x-mail::message>
# {{ $appraisal->title }}

Hi {{ $appraisal->manager->name }},

An appraisal has been started for **{{ $appraisal->employee->name }}** ({{ $appraisal->employee->employee_code }}), who reports to you.

They will rate themselves first. You will get another mail with your login details when it is your turn to add your manager ratings.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
