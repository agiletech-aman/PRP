@php
    $employee = $appraisal->employee;
    $maxRating = \App\Models\Appraisal::MAX_RATING;
    $employeeAverage = $appraisal->averageRating('employee');
    $managerAverage = $appraisal->averageRating('manager');
    $editableColumn = $stage ? $stage.'_rating' : null;
    $canAddHrRemarks = auth()->user()->can('addHrRemarks', $appraisal);
    $roleLabel = match ($role) {
        'employee' => 'Employee (self-rating)',
        'manager' => 'Reporting manager',
        'hr' => auth()->user()->isSuperAdmin() ? 'Admin (view only)' : 'HR',
        default => 'Viewer',
    };
@endphp

<x-layouts.form :title="$appraisal->title" :appraisal="$appraisal">
    <div class="form-page-head">
        <div>
            <h1 class="page-title">{{ $appraisal->title }}</h1>
            <p class="page-subtitle">
                {{ $employee->name }} ({{ $employee->employee_code }})
                · {{ $appraisal->department?->name ?? $employee->department?->name ?? 'No department' }}
                · Manager: {{ $appraisal->manager?->name ?? '—' }}
            </p>
        </div>
        <div class="form-page-role">
            <span class="muted small">Signed in as</span>
            <span class="pill pill-blue">{{ $roleLabel }}</span>
            <span class="pill {{ $appraisal->status->pillClass() }}">{{ $appraisal->status->label() }}</span>
        </div>
    </div>

    @if ($stage === 'employee')
        <div class="alert alert-info">Rate yourself on every factor from 1 to {{ $maxRating }}, add remarks if you like, then submit. Your manager will add their ratings after you.</div>
    @elseif ($stage === 'manager')
        <div class="alert alert-info">{{ $employee->name }} has submitted their self-rating. Add your manager rating for every factor, then submit.</div>
    @elseif ($role === 'manager' && $appraisal->status === \App\AppraisalStatus::Pending)
        <div class="alert alert-info">Waiting for {{ $employee->name }} to submit their self-rating. You will get a mail when it is your turn to rate.</div>
    @elseif ($role === 'employee' && $appraisal->status === \App\AppraisalStatus::EmployeeSubmitted)
        <div class="alert alert-info">You have submitted your self-rating. Your manager will add their ratings next.</div>
    @elseif ($canAddHrRemarks)
        <div class="alert alert-info">You can add optional HR remarks at the bottom of this form at any stage.</div>
    @endif

    <form method="POST" action="{{ route('appraisal.submit', $appraisal) }}">
        @csrf
        @method('PUT')

        <div class="card card-flush">
            <table class="table appraisal-table">
                <thead>
                    <tr class="appraisal-title"><th>Sr. No.</th><th colspan="3">Rating Factors Out of {{ $maxRating }} Rating.</th></tr>
                </thead>
                @php($serial = 0)
                @forelse ($ratingsByCategory as $ratings)
                    <tbody>
                        <tr class="appraisal-heading">
                            <th>{{ $ratings->first()->category_code }}.</th>
                            <th>{{ $ratings->first()->category_name }}:</th>
                            <th>Employee Rating</th>
                            <th>Manager Rating</th>
                        </tr>
                        @foreach ($ratings as $rating)
                            <tr>
                                <td class="center">{{ ++$serial }}</td>
                                <td>{{ $rating->factor_name }}</td>
                                @foreach (['employee_rating', 'manager_rating'] as $column)
                                    <td @class(['center', 'has-error' => $column === $editableColumn && $errors->has("ratings.{$rating->id}")])>
                                        @if ($column === $editableColumn)
                                            <select name="ratings[{{ $rating->id }}]" class="rating-select" required aria-label="Rating for {{ $rating->factor_name }}">
                                                <option value="">Select</option>
                                                @for ($value = 1; $value <= $maxRating; $value++)
                                                    <option value="{{ $value }}" @selected((string) old("ratings.{$rating->id}") === (string) $value)>{{ $value }}</option>
                                                @endfor
                                            </select>
                                        @else
                                            <span class="rating-value">{{ $rating->{$column} ?? '—' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody><tr><td colspan="4" class="muted">This appraisal has no rating factors.</td></tr></tbody>
                @endforelse
                @if ($ratingsByCategory->isNotEmpty())
                    <tfoot>
                        <tr class="appraisal-total">
                            <td></td>
                            <td>Average rating</td>
                            <td class="center">{{ $employeeAverage ?? '—' }}</td>
                            <td class="center">{{ $managerAverage ?? '—' }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="card">
            <div class="card-head"><h2>Remarks</h2><span class="muted small">Optional</span></div>
            <div class="field-grid">
                @foreach (['employee' => 'Employee remarks', 'manager' => 'Manager remarks'] as $commentStage => $label)
                    @if ($stage === $commentStage)
                        <x-field name="comments" :label="$label">
                            <textarea id="comments" name="comments" rows="4" maxlength="2000" placeholder="Optional: add your remarks">{{ old('comments') }}</textarea>
                        </x-field>
                    @else
                        <div class="field">
                            <label>{{ $label }}</label>
                            <p @class(['comment-text', 'muted' => blank($appraisal->{$commentStage.'_comments'})])>{{ $appraisal->{$commentStage.'_comments'} ?: 'No remarks.' }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if ($stage)
            <div class="form-actions sticky-actions">
                <span class="muted">Ratings cannot be changed after submitting.</span>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Submit your ratings? You cannot change them afterwards.')">
                    Submit {{ $stage === 'employee' ? 'self-rating' : 'manager rating' }}
                </button>
            </div>
        @endif
    </form>

    @if ($canAddHrRemarks)
        <form method="POST" action="{{ route('appraisal.hr-remarks', $appraisal) }}" class="card">
            @csrf
            @method('PUT')
            <div class="card-head">
                <h2>HR remarks</h2>
                <span class="muted small">
                    Optional · can be updated at any stage
                    @if ($appraisal->hr_remarked_at)
                        · last saved by {{ $appraisal->hrRemarksAuthor?->name ?? 'HR' }} on {{ $appraisal->hr_remarked_at->format('d M Y') }}
                    @endif
                </span>
            </div>
            <x-field name="hr_remarks" label="Remarks" for="hr_remarks">
                <textarea id="hr_remarks" name="hr_remarks" rows="4" maxlength="2000" placeholder="Optional: add HR remarks">{{ old('hr_remarks', $appraisal->hr_remarks) }}</textarea>
            </x-field>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save HR remarks</button>
            </div>
        </form>
    @elseif (filled($appraisal->hr_remarks))
        <div class="card">
            <div class="card-head"><h2>HR remarks</h2></div>
            <p class="comment-text">{{ $appraisal->hr_remarks }}</p>
        </div>
    @endif
</x-layouts.form>
