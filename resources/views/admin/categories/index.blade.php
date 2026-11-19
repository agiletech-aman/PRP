<x-layouts.admin title="Rating Headings & KRA" subtitle="Common headings appear for every department; KRA headings only for their department.">
    <div class="toolbar">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="inline-form">
            <select name="department" onchange="this.form.submit()" aria-label="Filter headings">
                <option value="">All headings</option>
                <option value="common" @selected($filter === 'common')>Common (all departments)</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) $filter === (string) $department->id)>KRA · {{ $department->name }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.categories.create', is_numeric($filter) ? ['department' => $filter] : []) }}" class="btn btn-primary"><x-icon name="plus" /> Add heading</a>
    </div>

    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr><th>Code</th><th>Heading</th><th>Applies to</th><th>Order</th><th>Factors</th><th>Status</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td><span class="code-chip">{{ $category->code }}</span></td>
                        <td><strong>{{ $category->name }}</strong></td>
                        <td>
                            @if ($category->isCommon())
                                <span class="pill pill-gray">All departments</span>
                            @else
                                <span class="pill pill-blue">{{ $category->department?->name ?? 'Deleted department' }}</span>
                            @endif
                        </td>
                        <td>{{ $category->sort_order }}</td>
                        <td>{{ $category->factors_count }}</td>
                        <td><span @class(['pill', 'pill-green' => $category->is_active, 'pill-gray' => ! $category->is_active])>{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="actions">
                                <x-icon-link :href="route('admin.categories.factors.index', $category)" icon="list" label="Manage factors" />
                                <x-icon-link :href="route('admin.categories.edit', $category)" icon="edit" label="Edit" />
                                <x-icon-delete :action="route('admin.categories.destroy', $category)" :confirm="'Delete '.$category->name.' and all its factors?'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No headings found for this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
