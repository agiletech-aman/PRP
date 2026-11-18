<x-layouts.admin title="Add role" subtitle="Name the role and tick the permissions it should have.">
    <a href="{{ route('admin.roles.index') }}" class="back-link"><x-icon name="arrow-left" /> All roles</a>

    <x-form-card :action="route('admin.roles.store')" submit="Save role" :cancel="route('admin.roles.index')">
        @include('admin.roles._form')
    </x-form-card>
</x-layouts.admin>
