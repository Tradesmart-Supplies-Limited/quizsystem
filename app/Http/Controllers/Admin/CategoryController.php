<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => QuestionCategory::withCount('questions')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new QuestionCategory(['color' => '#4EAADF', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        QuestionCategory::create($this->validated($request));

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(QuestionCategory $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, QuestionCategory $category)
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function toggle(QuestionCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', 'Category '.($category->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(QuestionCategory $category)
    {
        if ($category->questions()->exists()) {
            return back()->with('error', 'This category still has questions. Move or delete them first, or disable the category instead.');
        }
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?QuestionCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('question_categories', 'name')->ignore($category?->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] ??= 0;

        return $data;
    }
}
