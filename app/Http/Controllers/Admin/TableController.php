<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTableRequest;
use App\Models\CafeTable;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TableController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()?->role === 'owner', 403);

        return Inertia::render('admin/tables/Index', [
            'tables' => CafeTable::orderBy('label')->orderBy('id')->get()->map(fn (CafeTable $table): array => [
                'id' => $table->id, 'label' => $table->label, 'active' => $table->is_active, 'menuUrl' => $this->menuUrl($table),
            ]),
        ]);
    }

    public function store(SaveTableRequest $request): RedirectResponse
    {
        try {
            CafeTable::create($request->validated());
        } catch (QueryException $exception) {
            $this->handleDuplicateLabel($exception);
        }

        return back()->with('success', 'Meja berhasil ditambahkan.');
    }

    public function update(SaveTableRequest $request, CafeTable $table): RedirectResponse
    {
        try {
            $table->update($request->validated());
        } catch (QueryException $exception) {
            $this->handleDuplicateLabel($exception);
        }

        return back()->with('success', 'Meja berhasil diperbarui.');
    }

    public function qr(Request $request, CafeTable $table): Response
    {
        abort_unless($request->user()?->role === 'owner', 403);
        abort_unless($table->is_active, 404);
        $request->validate(['download' => ['sometimes', 'boolean']]);
        $writer = new Writer(new ImageRenderer(new RendererStyle(400, 4), new SvgImageBackEnd));

        return response($writer->writeString($this->menuUrl($table)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="meja-'.$table->id.'.svg"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function print(Request $request, CafeTable $table): InertiaResponse
    {
        abort_unless($request->user()?->role === 'owner', 403);
        abort_unless($table->is_active, 404);

        return Inertia::render('admin/tables/Print', [
            'table' => ['id' => $table->id, 'label' => $table->label, 'menuUrl' => $this->menuUrl($table)],
        ]);
    }

    private function menuUrl(CafeTable $table): string
    {
        return rtrim(config('app.url'), '/').route('guest.menu', ['table' => $table->id], false);
    }

    private function handleDuplicateLabel(QueryException $exception): never
    {
        if ($exception->getCode() === '23505') {
            throw ValidationException::withMessages(['label' => 'Label meja sudah digunakan.']);
        }

        throw $exception;
    }
}
