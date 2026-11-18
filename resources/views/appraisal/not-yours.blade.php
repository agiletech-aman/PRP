<x-layouts.form title="Not your appraisal" :appraisal="$appraisal">
    <div class="card form-message-card">
        <div class="form-message-icon form-message-icon-warn" aria-hidden="true">!</div>
        <h1>This appraisal form is not for you</h1>
        <p class="muted">
            You are signed in as <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->employee_code }}),
            who is not part of “{{ $appraisal->title }}”. Sign in with the details from the mail this link came in.
        </p>
        <form method="POST" action="{{ route('appraisal.switch-user', $appraisal) }}">
            @csrf
            <button type="submit" class="btn btn-primary">Sign in as a different user</button>
        </form>
    </div>
</x-layouts.form>
