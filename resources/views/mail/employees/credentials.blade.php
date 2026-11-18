<x-mail::message>
# Your login details

Hi {{ $employee->name }},

Your account on **{{ config('app.name') }}** is ready. Use these details to sign in:

<x-mail::panel>
**Employee ID:** {{ $employee->employee_code }}<br>
**Email:** {{ $employee->email }}<br>
**Password:** {{ $plainPassword }}
</x-mail::panel>

You can sign in with either your Employee ID or your email address.

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

For your security, please change this password from **My profile** after you sign in, and do not share it with anyone.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
