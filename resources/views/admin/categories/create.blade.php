<x-layouts.admin title="Add heading" subtitle="Choose whether this heading is common or a department KRA.">
    <a href="{{ route('admin.categories.index') }}" class="back-link"><x-icon name="arrow-left" /> All headings</a>

    <x-form-card :action="route('admin.categories.store')" submit="Save & add factors" :cancel="route('admin.categories.index')">
        @include('admin.categories._form')
    </x-form-card>
</x-layouts.admin>
