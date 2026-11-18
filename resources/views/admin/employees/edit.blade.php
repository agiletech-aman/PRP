<x-layouts.admin :title="'Edit · '.$employee->name" :subtitle="$employee->employee_code.' · '.$employee->email">
    <a href="{{ route('admin.employees.index') }}" class="back-link"><x-icon name="arrow-left" /> All employees</a>

    <x-form-card :action="route('admin.employees.update', $employee)" method="PUT" submit="Save changes" :cancel="route('admin.employees.index')">
        @include('admin.employees._form')
    </x-form-card>
</x-layouts.admin>
