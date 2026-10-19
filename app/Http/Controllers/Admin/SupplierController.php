<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/suppliers/Index', [
            'suppliers' => Supplier::orderBy('name')->orderBy('id')->get()->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id, 'name' => $supplier->name, 'contacts' => $supplier->contact ?? [], 'is_active' => $supplier->is_active,
            ]),
        ]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->supplierData());

        return back();
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->supplierData());

        return back();
    }
}
