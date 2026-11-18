<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::query()
                ->withCount(['ratingCategories', 'users'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
        ]);

        Department::create($validated);

        return back()->with('status', 'Department added.');
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.edit', ['department' => $department]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department)],
        ]);

        $department->update([...$validated, 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.departments.index')->with('status', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()->route('admin.departments.index')->with('status', 'Department deleted.');
    }

    /**
     * Preview the full appraisal form (common headings + this department's KRA).
     */
    public function form(Department $department): View
    {
        return view('admin.departments.form', [
            'department' => $department,
            'categories' => $department->appraisalFormCategories()->get(),
        ]);
    }
}
