<x-layouts.admin title="Add employee" subtitle="Create an account and assign a department, role and manager.">
    <a href="{{ route('admin.employees.index') }}" class="back-link"><x-icon name="arrow-left" /> All employees</a>

    <x-form-card :action="route('admin.employees.store')" submit="Save employee" :cancel="route('admin.employees.index')">
        @include('admin.employees._form')
    </x-form-card>
</x-layouts.admin>
