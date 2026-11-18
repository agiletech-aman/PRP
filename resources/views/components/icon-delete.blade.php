@props(['action', 'confirm', 'label' => 'Delete'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }})" class="icon-form">
    @csrf
    @method('DELETE')
    <button type="submit" class="icon-btn icon-btn-danger" title="{{ $label }}" aria-label="{{ $label }}">
        <x-icon name="trash" />
    </button>
</form>
