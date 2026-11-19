<x-layouts.admin
    :title="$category->code.'. '.$category->name"
    :subtitle="$category->isCommon() ? 'Common heading · shown to all departments' : 'KRA heading · only for '.($category->department?->name ?? 'a deleted department')"
>
    <a href="{{ route('admin.categories.index') }}" class="back-link"><x-icon name="arrow-left" /> All headings</a>

    <div class="card">
        <form method="POST" action="{{ route('admin.categories.factors.store', $category) }}" class="inline-form">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="New rating factor, e.g. Follows instruction" required>
            <input type="number" name="sort_order" value="{{ old('sort_order', $nextSortOrder) }}" min="0" class="narrow" title="Display order" aria-label="Display order" required>
            <button type="submit" class="btn btn-primary"><x-icon name="plus" /> Add factor</button>
        </form>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
        @error('sort_order')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr><th>Order</th><th>Rating factor</th><th>Status</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($category->factors as $factor)
                    <tr>
                        <td>{{ $factor->sort_order }}</td>
                        <td>{{ $factor->name }}</td>
                        <td><span @class(['pill', 'pill-green' => $factor->is_active, 'pill-gray' => ! $factor->is_active])>{{ $factor->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div class="actions">
                                <x-icon-link :href="route('admin.factors.edit', $factor)" icon="edit" label="Edit" />
                                <x-icon-delete :action="route('admin.factors.destroy', $factor)" :confirm="'Delete \''.$factor->name.'\'?'" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No factors yet. Add the first one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
