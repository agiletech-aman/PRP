<x-mail::panel>
**Employee ID:** {{ $recipient->employee_code }}<br>
**Email:** {{ $recipient->email }}<br>
@if ($plainPassword)
**Password:** {{ $plainPassword }}
@else
**Password:** use your current portal password
@endif
</x-mail::panel>

@if ($plainPassword)
This is a new password, so any older password no longer works. Sign in with your Employee ID or email.
@else
Sign in with your Employee ID or email.
@endif
