<x-layouts.admin title="My Appraisals" subtitle="Your own appraisals and the ones you review as a manager">
    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr><th>Appraisal</th><th>Employee</th><th>Your part</th><th>Sent</th><th>Status</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($appraisals as $appraisal)
                    @php($stage = $appraisal->ratingStageFor(auth()->user()))
                    <tr>
                        <td><strong>{{ $appraisal->title }}</strong></td>
                        <td>{{ $appraisal->employee->name }}</td>
                        <td>{{ auth()->id() === $appraisal->employee_id ? 'Self-rating' : 'Manager rating' }}</td>
                        <td>{{ $appraisal->sent_at?->format('d M Y') ?? '—' }}</td>
                        <td>
                            @if ($stage)
                                <span class="pill pill-warn">Your action needed</span>
                            @else
                                <span class="pill {{ $appraisal->status->pillClass() }}">{{ $appraisal->status->label() }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                @if ($stage)
                                    <a href="{{ route('admin.appraisals.show', $appraisal) }}" class="btn btn-primary btn-sm">Fill form</a>
                                @else
                                    <x-icon-link :href="route('admin.appraisals.show', $appraisal)" icon="eye" label="View" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">You have no appraisals yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
