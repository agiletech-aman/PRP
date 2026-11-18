<x-layouts.admin :title="'Appraisal form · '.$department->name" subtitle="Common headings plus this department's KRA · ratings out of 5">
    <a href="{{ route('admin.departments.index') }}" class="back-link"><x-icon name="arrow-left" /> All departments</a>

    <div class="card card-flush">
        <table class="table appraisal-table">
            <thead>
                <tr class="appraisal-title"><th>Sr. No.</th><th colspan="3">Rating Factors Out of 5 Rating.</th></tr>
            </thead>
            @php($serial = 0)
            @forelse ($categories as $category)
                <tbody>
                    <tr class="appraisal-heading">
                        <th>{{ $category->code }}.</th>
                        <th>
                            {{ $category->name }}:
                            @unless ($category->isCommon())<span class="badge badge-blue">{{ $department->name }}</span>@endunless
                        </th>
                        <th>Employee Rating</th>
                        <th>Manager Rating</th>
                    </tr>
                    @forelse ($category->factors as $factor)
                        <tr>
                            <td class="center">{{ ++$serial }}</td>
                            <td>{{ $factor->name }}</td>
                            <td></td>
                            <td></td>
                        </tr>
                    @empty
                        <tr><td></td><td colspan="3" class="muted">No factors in this heading yet.</td></tr>
                    @endforelse
                </tbody>
            @empty
                <tbody><tr><td colspan="4" class="muted">No headings configured.</td></tr></tbody>
            @endforelse
        </table>
    </div>
</x-layouts.admin>
