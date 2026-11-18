<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RatingCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatingCategoryController extends Controller
{
    /**
     * List headings; filter with ?department=common or ?department={id}.
     */
    public function index(Request $request): View
    {
        $filter = $request->query('department');

        $categories = RatingCategory::query()
            ->with('department')
            ->withCount('factors')
            ->when($filter === 'common', fn ($query) => $query->whereNull('department_id'))
            ->when(is_numeric($filter), fn ($query) => $query->where('department_id', $filter))
            ->orderByRaw('department_id is not null')
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->get();

        return view('admin.categories.index', [
            'categories' => $categories,
            'departments' => Department::orderBy('name')->get(),
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.categories.create', [
            'category' => new RatingCategory([
                'department_id' => $request->integer('department') ?: null,
                'is_active' => true,
            ]),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = RatingCategory::create($this->validated($request));

        return redirect()->route('admin.categories.factors.index', $category)
            ->with('status', 'Heading added. Now add its rating factors.');
    }

    public function edit(RatingCategory $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, RatingCategory $category): RedirectResponse
    {
        $category->update($this->validated($request));

        return redirect()->route('admin.categories.index')->with('status', 'Heading updated.');
    }

    public function destroy(RatingCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Heading deleted.');
    }

    /**
     * @return array{department_id: ?int, code: string, name: string, sort_order: int, is_active: bool}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        return [...$validated, 'is_active' => $request->boolean('is_active')];
    }
}
