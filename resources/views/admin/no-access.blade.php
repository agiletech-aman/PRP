<x-layouts.admin title="No access">
    <div class="card card-narrow">
        <h2 class="mb">You don't have access to any section yet</h2>
        <p class="muted mb">Your role does not include any permissions. Please ask HR or an administrator to update your role.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Logout</button>
        </form>
    </div>
</x-layouts.admin>
