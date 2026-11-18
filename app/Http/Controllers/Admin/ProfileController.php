<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\PermissionName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * The signed-in user's own profile.
     */
    public function show(Request $request): View
    {
        $user = $request->user()->load([
            'role.permissions',
            'department',
            'manager',
            'directReports' => fn ($query) => $query->orderBy('name'),
        ]);

        $permissionLabels = $user->isSuperAdmin()
            ? array_map(fn (PermissionName $permission) => $permission->label(), PermissionName::cases())
            : $user->role?->permissions->pluck('label')->all() ?? [];

        return view('admin.profile.show', [
            'user' => $user,
            'permissionLabels' => $permissionLabels,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('admin.profile.show')->with('status', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        $request->session()->regenerate();

        return redirect()->route('admin.profile.show')->with('status', 'Password changed.');
    }
}
