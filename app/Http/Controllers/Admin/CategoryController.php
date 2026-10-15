<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }
}
