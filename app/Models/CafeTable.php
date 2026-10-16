<?php

namespace App\Models;

use Database\Factories\CafeTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $label
 * @property bool $is_active
 */
#[Fillable(['label', 'qr_token', 'is_active'])]
class CafeTable extends Model
{
    /** @use HasFactory<CafeTableFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
