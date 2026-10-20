<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStaffRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(['all', 'owner', 'cashier', 'kitchen'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $filters = [
            'q' => trim($validated['q'] ?? ''), 'role' => $validated['role'] ?? 'all',
            'status' => $validated['status'] ?? 'all', 'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
        $query = User::query()->select(['id', 'name', 'email', 'role', 'is_active'])->orderBy('name')->orderBy('id');
        if ($filters['q'] !== '') {
            $query->where(fn (Builder $query) => $query->whereLike('name', '%'.$filters['q'].'%')->orWhereLike('email', '%'.$filters['q'].'%'));
        }
        if ($filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }
        if ($filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }
        $staff = $query->paginate($filters['per_page'])->withQueryString();

        return Inertia::render('admin/staff/Index', [
            'staff' => $staff->items(), 'filters' => $filters, 'status' => $request->session()->get('status'),
            'pagination' => [
                'current_page' => $staff->currentPage(), 'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(), 'total' => $staff->total(),
                'from' => $staff->firstItem(), 'to' => $staff->lastItem(),
                'prev_page_url' => $staff->previousPageUrl(), 'next_page_url' => $staff->nextPageUrl(),
            ],
        ]);
    }

    public function store(SaveStaffRequest $request): RedirectResponse
    {
        $user = new User;
        $user->forceFill($request->safe()->only(['name', 'email', 'password', 'role', 'is_active']))->save();

        return to_route('admin.staff')->with('status', 'Akun staf berhasil ditambahkan.');
    }

    public function update(SaveStaffRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            /** Lock staff in ID order so concurrent ownership changes use current data. */
            $accounts = User::orderBy('id')->lockForUpdate()->get();
            $actor = $accounts->find($request->user()->id);
            abort_unless($actor?->can('manage-staff'), 403);
            $staff = $accounts->find($user->id);
            abort_unless($staff !== null, 404);
            $data = $request->safe()->only(['name', 'email', 'password', 'role', 'is_active']);
            $removesOwner = $data['role'] !== 'owner' || ! $data['is_active'];
            if ($staff->id === $actor->id && $removesOwner) {
                throw ValidationException::withMessages(['role' => 'Anda tidak dapat menonaktifkan atau mengubah role akun sendiri. Minta owner lain melakukannya.']);
            }
            if ($staff->role === 'owner' && $staff->is_active && $removesOwner && $accounts->where('role', 'owner')->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Minimal satu owner aktif harus dipertahankan.']);
            }
            $changesPassword = filled($data['password'] ?? null);
            if (! $changesPassword) {
                unset($data['password']);
            }
            if ($staff->email !== $data['email']) {
                $staff->email_verified_at = null;
            }
            if ($changesPassword || ! $data['is_active']) {
                $staff->remember_token = Str::random(60);
            }
            $staff->forceFill($data)->save();
            if (($changesPassword || ! $staff->is_active) && config('session.driver') === 'database') {
                $sessions = DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $staff->id);
                if ($staff->id === $actor->id) {
                    $sessions->where('id', '!=', $request->session()->getId());
                }
                $sessions->delete();
            }
            if ($staff->id === $actor->id) {
                $request->user()->refresh();
            }
        }, attempts: 3);

        return back()->with('status', 'Perubahan akun staf berhasil disimpan.');
    }
}
