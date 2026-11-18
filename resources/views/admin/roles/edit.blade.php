<x-layouts.admin :title="'Edit role · '.$role->name" subtitle="Changes apply immediately to everyone with this role.">
    <a href="{{ route('admin.roles.index') }}" class="back-link"><x-icon name="arrow-left" /> All roles</a>

    <x-form-card :action="route('admin.roles.update', $role)" method="PUT" submit="Save changes" :cancel="route('admin.roles.index')">
        @include('admin.roles._form')
    </x-form-card>
</x-layouts.admin>
