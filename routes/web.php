<?php

use App\Http\Controllers\Admin\CashOrderController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\OptionGroupController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\GuestMenuController;
use App\Http\Controllers\GuestOrderController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

if (app()->environment('local', 'testing')) {
    Route::get('/menu', [GuestMenuController::class, 'index'])->name('guest.menu');
    Route::get('/menu/cart', [GuestMenuController::class, 'index'])->defaults('screen', 'cart')->name('guest.menu.cart');
    Route::get('/menu/products/{product}', [GuestMenuController::class, 'index'])->whereNumber('product')->defaults('screen', 'product')->name('guest.menu.product');
    Route::get('/menu/products/{product}/image', [GuestMenuController::class, 'productImage'])->name('guest.menu.product-image');
    Route::post('/menu/orders', [GuestOrderController::class, 'store'])->middleware('throttle:10,1')->name('guest.orders.store');
    Route::get('/menu/orders/{order:public_token}', [GuestOrderController::class, 'show'])->name('guest.orders.show');
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [SessionController::class, 'create'])->name('login');
        Route::post('/login', [SessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::inertia('/admin/dashboard', 'admin/Dashboard')->name('admin.dashboard');
        Route::get('/admin/inventory', [InventoryController::class, 'index'])->defaults('section', 'stock')->name('admin.inventory.index');
        Route::get('/admin/inventory/recipes', [InventoryController::class, 'index'])->defaults('section', 'recipes')->name('admin.inventory.recipes');
        Route::get('/admin/inventory/history', [InventoryController::class, 'index'])->defaults('section', 'history')->name('admin.inventory.history');
        Route::post('/admin/inventory/items', [InventoryController::class, 'store'])->name('admin.inventory.items.store');
        Route::put('/admin/inventory/items/{stockItem}', [InventoryController::class, 'update'])->name('admin.inventory.items.update');
        Route::patch('/admin/inventory/items/{stockItem}/quick-add', [InventoryController::class, 'quickAdd'])->name('admin.inventory.items.quickAdd');
        Route::post('/admin/inventory/adjustments', [InventoryController::class, 'adjust'])->name('admin.inventory.adjustments');
        Route::put('/admin/inventory/recipes/{variant}', [InventoryController::class, 'saveRecipe'])->name('admin.inventory.recipe');
        Route::put('/admin/inventory/option-ingredients/{option}', [InventoryController::class, 'saveOption'])->name('admin.inventory.option');
        Route::post('/admin/inventory/requirements', [InventoryController::class, 'requirements'])->name('admin.inventory.requirements');

        Route::get('/admin/pos', [CashOrderController::class, 'pos'])->name('admin.pos');
        Route::post('/admin/pos/checkout', [CashOrderController::class, 'checkout'])->name('admin.pos.checkout');
        Route::get('/admin/orders', [CashOrderController::class, 'orders'])->name('admin.orders');
        Route::post('/admin/orders/{order}/confirm-cash', [CashOrderController::class, 'confirmCash'])->name('admin.orders.confirm-cash');
        Route::get('/admin/kitchen', [CashOrderController::class, 'kitchen'])->name('admin.kitchen');
        Route::post('/admin/kitchen/orders/{order}/advance', [CashOrderController::class, 'advance'])->name('admin.kitchen.advance');

        Route::get('/admin/tables', [TableController::class, 'index'])->name('admin.tables');
        Route::post('/admin/tables', [TableController::class, 'store'])->name('admin.tables.store');
        Route::put('/admin/tables/{table}', [TableController::class, 'update'])->name('admin.tables.update');
        Route::get('/admin/tables/{table}/qr.svg', [TableController::class, 'qr'])->name('admin.tables.qr');
        Route::get('/admin/tables/{table}/print', [TableController::class, 'print'])->name('admin.tables.print');

        foreach (['suppliers', 'purchases', 'staff'] as $module) {
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
    });
}
