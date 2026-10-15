<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OptionGroupController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

if (app()->environment('local', 'testing')) {
    Route::inertia('/admin/dashboard', 'admin/Dashboard')->name('admin.dashboard');
    Route::get('/admin/menu', [MenuController::class, 'index'])->defaults('section', 'products')->name('admin.menu');
    Route::get('/admin/categories', [MenuController::class, 'index'])->defaults('section', 'categories')->name('admin.categories');
    Route::get('/admin/options', [MenuController::class, 'index'])->defaults('section', 'options')->name('admin.options');
    Route::post('/admin/menu/price', [MenuController::class, 'price'])->name('admin.menu.price');
    Route::post('/admin/products', [ProductController::class, 'store'])->name('admin.products.store');
    Route::put('/admin/products/{product}', [ProductController::class, 'update'])->name('admin.products.update');
    Route::patch('/admin/products/{product}/status', [ProductController::class, 'status'])->name('admin.products.status');
    Route::get('/admin/products/{product}/image', [ProductController::class, 'image'])->name('admin.products.image');
    Route::post('/admin/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::post('/admin/options', [OptionGroupController::class, 'store'])->name('admin.options.store');
    Route::put('/admin/options/{optionGroup}', [OptionGroupController::class, 'update'])->name('admin.options.update');
}
