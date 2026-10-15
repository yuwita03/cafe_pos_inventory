<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OptionGroupController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

if (app()->environment('local', 'testing')) {
    Route::inertia('/admin/dashboard', 'admin/Dashboard')->name('admin.dashboard');
    Route::get('/admin/inventory', [InventoryController::class, 'index'])->defaults('section', 'stock')->name('admin.inventory.index');
    Route::get('/admin/inventory/recipes', [InventoryController::class, 'index'])->defaults('section', 'recipes')->name('admin.inventory.recipes');
    Route::get('/admin/inventory/history', [InventoryController::class, 'index'])->defaults('section', 'history')->name('admin.inventory.history');
    Route::post('/admin/inventory/items', [InventoryController::class, 'store'])->name('admin.inventory.items.store');
    Route::put('/admin/inventory/items/{stockItem}', [InventoryController::class, 'update'])->name('admin.inventory.items.update');
    Route::post('/admin/inventory/adjustments', [InventoryController::class, 'adjust'])->name('admin.inventory.adjustments');
    Route::put('/admin/inventory/recipes/{variant}', [InventoryController::class, 'saveRecipe'])->name('admin.inventory.recipe');
    Route::put('/admin/inventory/option-ingredients/{option}', [InventoryController::class, 'saveOption'])->name('admin.inventory.option');
    Route::post('/admin/inventory/requirements', [InventoryController::class, 'requirements'])->name('admin.inventory.requirements');

    foreach (['suppliers', 'purchases', 'tables', 'orders', 'staff', 'pos', 'kitchen'] as $module) {
        Route::inertia('/admin/'.$module, 'admin/Workflow', ['module' => $module])->name('admin.'.$module);
    }

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
