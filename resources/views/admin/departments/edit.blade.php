<x-layouts.admin title="Edit department" :subtitle="$department->name">
    <a href="{{ route('admin.departments.index') }}" class="back-link"><x-icon name="arrow-left" /> All departments</a>

    <x-form-card :action="route('admin.departments.update', $department)" method="PUT" submit="Save changes" :cancel="route('admin.departments.index')">
        <x-form-section title="Department details" description="Renaming keeps all employees and KRA headings linked.">
            <x-field name="name" label="Department name" required full>
                <input id="name" type="text" name="name" value="{{ old('name', $department->name) }}" required>
            </x-field>
            <div class="field-full">
                <x-toggle name="is_active" label="Active" hint="Inactive departments stay in the system but are marked as not in use." :checked="old('is_active', $department->is_active)" />
            </div>
        </x-form-section>
    </x-form-card>
</x-layouts.admin>
