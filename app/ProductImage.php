<?php

namespace App;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductImage
{
    public function store(UploadedFile $upload): string
    {
        $extension = match ($upload->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['image' => 'Pilih JPG, JPEG, PNG, atau WebP.']),
        };
        $contents = $upload->getContent();
        $path = 'products/'.Str::uuid().'.'.$extension;
        try {
            if (! Storage::disk('public')->put($path, $contents)) {
                throw new RuntimeException('Product image write failed.');
            }
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            report($exception);
            throw ValidationException::withMessages(['image' => 'Foto tidak dapat disimpan. Coba lagi.']);
        }

        return $path;
    }
}
