@php
    $message = match ($submitted) {
        'employee' => ['Thank you for submitting your self-appraisal!', "Your ratings have been saved. Your reporting manager {$appraisal->manager?->name} has been notified to add their ratings."],
        'manager' => ['Thank you for rating '.$appraisal->employee->name.'!', 'Your manager ratings have been saved. The appraisal is now waiting for HR evaluation.'],
        'hr' => ['Thank you, HR remarks saved!', 'Your remarks on '.$appraisal->employee->name.'’s appraisal have been saved.'],
        default => ['Thank you!', 'Your response for this appraisal has been recorded.'],
    };
@endphp

<x-layouts.form title="Thank you" :appraisal="$appraisal">
    <div class="card form-message-card">
        <div class="form-message-icon" aria-hidden="true">✓</div>
        <h1>{{ $message[0] }}</h1>
        <p class="muted">{{ $message[1] }}</p>
        <p class="small muted">{{ $appraisal->title }} · {{ $appraisal->employee->name }} · {{ $appraisal->status->label() }}</p>

        <div class="form-message-actions">
            <a href="{{ route('appraisal.form', $appraisal) }}" class="btn btn-outline">View submitted form</a>
            <form method="POST" action="{{ route('appraisal.switch-user', $appraisal) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Sign out</button>
            </form>
        </div>
    </div>
</x-layouts.form>
