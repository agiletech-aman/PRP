@php
    $employee = $appraisal->employee;
    $maxRating = \App\Models\Appraisal::MAX_RATING;
    $employeeAverage = $appraisal->averageRating('employee');
    $managerAverage = $appraisal->averageRating('manager');
    $editableColumn = $stage ? $stage.'_rating' : null;
    $visibleRatingStages = match ($role) {
        'employee' => ['employee' => 'Employee Rating'],
        'manager', 'hierarchy' => ['employee' => 'Employee Rating', 'manager' => 'Manager Rating'],
        default => ['employee' => 'Employee Rating', 'manager' => 'Manager Rating'],
    };
    $ratingAverages = ['employee' => $employeeAverage, 'manager' => $managerAverage];
    $tableColumnCount = 2 + count($visibleRatingStages);
    $canAddHrRemarks = auth()->user()->can('addHrRemarks', $appraisal);
    $roleLabel = match ($role) {
        'employee' => 'Employee (self-rating)',
        'manager' => 'Reporting manager',
        'hierarchy' => 'Reporting hierarchy (view only)',
        'evaluator' => 'HR evaluator',
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
        <div class="alert alert-info">Rate yourself on every factor from 1 to {{ $maxRating }}, add remarks if you like, then submit.</div>
    @elseif ($stage === 'manager')
        <div class="alert alert-info">{{ $employee->name }} has submitted their self-rating. Add your manager rating for every factor, then submit.</div>
    @elseif ($role === 'manager' && $appraisal->status === \App\AppraisalStatus::Pending)
        <div class="alert alert-info">Waiting for {{ $employee->name }} to submit their self-rating. You will get a mail when it is your turn to rate.</div>
    @elseif ($role === 'employee' && $appraisal->status === \App\AppraisalStatus::EmployeeSubmitted)
        <div class="alert alert-info">You have submitted your self-rating. Your manager will add their ratings next.</div>
    @elseif ($canAddHrRemarks)
        <div class="alert alert-info">
            @if ($appraisal->status === \App\AppraisalStatus::AwaitingHr)
                Add your HR evaluation remarks and submit to complete this appraisal.
            @else
                You can update HR remarks at the bottom of this form.
            @endif
        </div>
    @endif

    <form id="appraisal-ratings-form" method="POST" action="{{ route('appraisal.submit', $appraisal) }}">
        @csrf
        @method('PUT')

        <div class="card card-flush">
            <table class="table appraisal-table">
                <thead>
                    <tr class="appraisal-title"><th>Sr. No.</th><th colspan="{{ $tableColumnCount - 1 }}">Rating Factors Out of {{ $maxRating }} Rating.</th></tr>
                </thead>
                @php($serial = 0)
                @forelse ($ratingsByCategory as $ratings)
                    <tbody>
                        <tr class="appraisal-heading">
                            <th>{{ $ratings->first()->category_code }}.</th>
                            <th>{{ $ratings->first()->category_name }}:</th>
                            @foreach ($visibleRatingStages as $columnLabel)
                                <th>{{ $columnLabel }}</th>
                            @endforeach
                        </tr>
                        @foreach ($ratings as $rating)
                            <tr>
                                <td class="center">{{ ++$serial }}</td>
                                <td>{{ $rating->factor_name }}</td>
                                @foreach (array_keys($visibleRatingStages) as $ratingStage)
                                    @php($column = $ratingStage.'_rating')
                                    <td @class(['center', 'has-error' => $column === $editableColumn && $errors->has("ratings.{$rating->id}")])>
                                        @if ($column === $editableColumn)
                                            <div class="star-rating" role="radiogroup" aria-label="Rating for {{ $rating->factor_name }}">
                                                @for ($value = $maxRating; $value >= 1; $value--)
                                                    <input type="radio" id="rating-{{ $rating->id }}-{{ $value }}" name="ratings[{{ $rating->id }}]" value="{{ $value }}" required @checked((string) old("ratings.{$rating->id}") === (string) $value)>
                                                    <label for="rating-{{ $rating->id }}-{{ $value }}" title="{{ $value }} out of {{ $maxRating }}">&#9733;</label>
                                                @endfor
                                            </div>
                                        @elseif (filled($rating->{$column}))
                                            <span class="star-display" title="{{ $rating->{$column} }} out of {{ $maxRating }}" aria-label="{{ $rating->{$column} }} out of {{ $maxRating }}">
                                                @for ($value = 1; $value <= $maxRating; $value++)
                                                    <span @class(['filled' => $value <= $rating->{$column}])>&#9733;</span>
                                                @endfor
                                            </span>
                                        @else
                                            <span class="rating-value">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody><tr><td colspan="{{ $tableColumnCount }}" class="muted">This appraisal has no rating factors.</td></tr></tbody>
                @endforelse
                @if ($ratingsByCategory->isNotEmpty())
                    <tfoot>
                        <tr class="appraisal-total">
                            <td></td>
                            <td>Average rating</td>
                            @foreach (array_keys($visibleRatingStages) as $ratingStage)
                                <td class="center">{{ $ratingAverages[$ratingStage] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="card">
            <div class="card-head"><h2>Remarks</h2><span class="muted small">Optional</span></div>
            @php($visibleRemarkStages = array_intersect_key(['employee' => 'Employee remarks', 'manager' => 'Manager remarks'], $visibleRatingStages))
            @php($remarkPlaceholders = [
                'employee' => 'Describe how you worked on your projects this period: projects / tasks handled, your key contributions, achievements, challenges you solved and what you learned.',
                'manager' => 'Share your feedback on the employee\'s work: quality of project delivery, strengths, areas to improve and goals for the next period.',
            ])
            <div @class(['field-grid', 'field-grid-single' => count($visibleRemarkStages) === 1])>
                @foreach ($visibleRemarkStages as $commentStage => $label)
                    @if ($stage === $commentStage)
                        <x-field name="comments" :label="$label">
                            <textarea id="comments" name="comments" rows="5" maxlength="2000" placeholder="{{ $remarkPlaceholders[$commentStage] }}">{{ old('comments') }}</textarea>
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
                <button type="submit" class="btn btn-primary">
                    Submit {{ $stage === 'employee' ? 'self-rating' : 'manager rating' }}
                </button>
            </div>
        @endif
    </form>

    @if ($stage)
        <dialog class="confirmation-modal" id="rating-confirmation" aria-labelledby="rating-confirmation-title">
            <h2 id="rating-confirmation-title">Submit your ratings?</h2>
            <p>You cannot change your ratings after submitting.</p>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" id="cancel-rating-submission" autofocus>Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-rating-submission">Confirm submission</button>
            </div>
        </dialog>

        <script>
            const ratingConfirmation = document.getElementById('rating-confirmation');
            const cancelRatingSubmission = document.getElementById('cancel-rating-submission');
            const confirmRatingSubmission = document.getElementById('confirm-rating-submission');
            const appraisalRatingsForm = document.getElementById('appraisal-ratings-form');
            let ratingSubmissionConfirmed = false;

            if (ratingConfirmation && cancelRatingSubmission && confirmRatingSubmission && appraisalRatingsForm) {
                appraisalRatingsForm.addEventListener('submit', (event) => {
                    if (ratingSubmissionConfirmed) {
                        ratingSubmissionConfirmed = false;

                        return;
                    }

                    event.preventDefault();

                    if (appraisalRatingsForm.reportValidity()) {
                        ratingConfirmation.showModal();
                    }
                });
                cancelRatingSubmission.addEventListener('click', () => ratingConfirmation.close());
                confirmRatingSubmission.addEventListener('click', () => {
                    ratingSubmissionConfirmed = true;
                    appraisalRatingsForm.requestSubmit();
                });
            }
        </script>
    @endif

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
                <button type="submit" class="btn btn-primary">
                    {{ $appraisal->status === \App\AppraisalStatus::AwaitingHr ? 'Submit HR evaluation' : 'Save HR remarks' }}
                </button>
            </div>
        </form>
    @elseif (filled($appraisal->hr_remarks))
        <div class="card">
            <div class="card-head"><h2>HR remarks</h2></div>
            <p class="comment-text">{{ $appraisal->hr_remarks }}</p>
        </div>
    @endif
</x-layouts.form>
