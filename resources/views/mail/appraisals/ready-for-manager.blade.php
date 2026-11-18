<x-mail::message>
# {{ $appraisal->title }}

Hi {{ $appraisal->manager->name }},

**{{ $appraisal->employee->name }}** has submitted their self-appraisal. Please review it and add your manager ratings.

<x-mail::button :url="$url">
Rate {{ $appraisal->employee->name }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
