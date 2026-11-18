<x-layouts.admin title="Edit heading" :subtitle="$category->code.'. '.$category->name">
    <a href="{{ route('admin.categories.index') }}" class="back-link"><x-icon name="arrow-left" /> All headings</a>

    <x-form-card :action="route('admin.categories.update', $category)" method="PUT" submit="Save changes" :cancel="route('admin.categories.index')">
        @include('admin.categories._form')
    </x-form-card>
</x-layouts.admin>
