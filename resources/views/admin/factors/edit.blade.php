<x-layouts.admin title="Edit rating factor" :subtitle="'Heading: '.$factor->category->code.'. '.$factor->category->name">
    <a href="{{ route('admin.categories.factors.index', $factor->rating_category_id) }}" class="back-link"><x-icon name="arrow-left" /> Back to factors</a>

    <x-form-card :action="route('admin.factors.update', $factor)" method="PUT" submit="Save changes" :cancel="route('admin.categories.factors.index', $factor->rating_category_id)">
        <x-form-section title="Factor details" description="Employees and managers rate each factor out of 5.">
            <x-field name="name" label="Rating factor" required full>
                <input id="name" type="text" name="name" value="{{ old('name', $factor->name) }}" required>
            </x-field>
            <x-field name="sort_order" label="Display order" required hint="Lower numbers appear first.">
                <input id="sort_order" type="number" name="sort_order" min="0" value="{{ old('sort_order', $factor->sort_order) }}" required>
            </x-field>
            <div class="field-full">
                <x-toggle name="is_active" label="Active" hint="Inactive factors are hidden from appraisal forms." :checked="old('is_active', $factor->is_active)" />
            </div>
        </x-form-section>
    </x-form-card>
</x-layouts.admin>
