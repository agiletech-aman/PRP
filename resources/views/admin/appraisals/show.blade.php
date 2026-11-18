@php
    $employee = $appraisal->employee;
    $employeeAverage = $appraisal->averageRating('employee');
    $managerAverage = $appraisal->averageRating('manager');
    $editableColumn = $stage ? $stage.'_rating' : null;
@endphp

<x-layouts.admin :title="$appraisal->title" :subtitle="$employee->name.' · '.($appraisal->department?->name ?? $employee->department?->name ?? 'No department').' · ratings out of '.\App\Models\Appraisal::MAX_RATING">
    <a href="{{ auth()->user()->can(\App\PermissionName::SendAppraisals->value) ? route('admin.appraisals.index') : route('admin.appraisals.mine') }}" class="back-link">
        <x-icon name="arrow-left" /> Back to appraisals
    </a>

    <div class="stats">
        <x-stat label="Status" :value="$appraisal->status->label()" :hint="'Sent '.($appraisal->sent_at?->format('d M Y') ?? '—')" />
        <x-stat label="Employee" :value="$employee->name" :hint="$employee->employee_code.' · '.($employee->designation ?? '')" />
        <x-stat label="Reporting manager" :value="$appraisal->manager?->name ?? '—'" :hint="$appraisal->manager?->designation" />
        <x-stat label="Average (self / manager)" :value="($employeeAverage ?? '—').' / '.($managerAverage ?? '—')" />
    </div>

    @if ($stage === 'employee')
        <div class="alert alert-info">Rate yourself on every factor, then submit. Your manager will add their ratings after you.</div>
    @elseif ($stage === 'manager')
        <div class="alert alert-info">{{ $employee->name }} has submitted their self-rating. Add your manager rating for every factor, then submit.</div>
    @elseif ($appraisal->status === \App\AppraisalStatus::Pending && auth()->id() === $appraisal->manager_id)
        <div class="alert alert-info">Waiting for {{ $employee->name }} to submit their self-rating. You will get a mail when it is your turn.</div>
    @endif

    <form method="POST" action="{{ route('admin.appraisals.update', $appraisal) }}">
        @csrf
        @method('PUT')

        <div class="card card-flush">
            <table class="table appraisal-table">
                <thead>
                    <tr class="appraisal-title"><th>Sr. No.</th><th colspan="3">Rating Factors Out of {{ \App\Models\Appraisal::MAX_RATING }} Rating.</th></tr>
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
                                                @for ($value = 1; $value <= \App\Models\Appraisal::MAX_RATING; $value++)
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

    @can('addHrRemarks', $appraisal)
        <form method="POST" action="{{ route('admin.appraisals.hr-remarks', $appraisal) }}" class="card">
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
                <button type="submit" class="btn btn-outline">Save HR remarks</button>
            </div>
        </form>
    @elseif (filled($appraisal->hr_remarks))
        <div class="card">
            <div class="card-head"><h2>HR remarks</h2></div>
            <p class="comment-text">{{ $appraisal->hr_remarks }}</p>
        </div>
    @endif
</x-layouts.admin>
