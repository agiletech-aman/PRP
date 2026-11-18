<x-mail::message>
# {{ $appraisals->first()->title }}

Hi {{ $hr->name }},

Appraisals have been sent to the following {{ \Illuminate\Support\Str::plural('employee', $appraisals->count()) }}. You can track them and add HR remarks in the portal.

<x-mail::table>
| Employee | Employee ID | Reporting manager |
|:---------|:------------|:------------------|
@foreach ($appraisals as $appraisal)
| {{ $appraisal->employee->name }} | {{ $appraisal->employee->employee_code }} | {{ $appraisal->manager?->name ?? '—' }} |
@endforeach
</x-mail::table>

Your login details:

@include('mail.appraisals._credentials')

<x-mail::button :url="$url">
Track appraisals
</x-mail::button>

Please keep your password private and change it from **My profile** after you sign in.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
