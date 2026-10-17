<?php

namespace App\Http\Controllers;

use App\Models\CafeTable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class GuestMenuController extends Controller
{
    public function index(Request $request): Response
    {
        $validator = Validator::make(['table' => $request->query('table')], ['table' => ['required', 'integer', 'min:1', 'max:9007199254740991']]);
        $table = $validator->fails() ? null : CafeTable::whereKey((int) $validator->validated()['table'])->where('is_active', true)->first();
        if (! $table) {
            return response('Meja tidak ditemukan atau sedang nonaktif. Silakan hubungi staf café.', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }

        return response('Menu pelanggan belum tersedia. Silakan pesan melalui kasir.', 503, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
