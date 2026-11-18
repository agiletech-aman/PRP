<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RatingCategory;
use App\Models\RatingFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatingFactorController extends Controller
{
    public function index(RatingCategory $category): View
    {
        return view('admin.factors.index', [
            'category' => $category->load(['department', 'factors']),
            'nextSortOrder' => ($category->factors()->max('sort_order') ?? 0) + 1,
        ]);
    }

    public function store(Request $request, RatingCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $category->factors()->create($validated);

        return back()->with('status', 'Rating factor added.');
    }

    public function edit(RatingFactor $factor): View
    {
        return view('admin.factors.edit', ['factor' => $factor->load('category')]);
    }

    public function update(Request $request, RatingFactor $factor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $factor->update([...$validated, 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.categories.factors.index', $factor->rating_category_id)
            ->with('status', 'Rating factor updated.');
    }

    public function destroy(RatingFactor $factor): RedirectResponse
    {
        $categoryId = $factor->rating_category_id;
        $factor->delete();

        return redirect()->route('admin.categories.factors.index', $categoryId)
            ->with('status', 'Rating factor deleted.');
    }
}
