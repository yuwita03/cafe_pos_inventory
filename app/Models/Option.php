<?php

namespace App\Models;

use Database\Factories\OptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $option_group_id
 * @property string $name
 * @property int $price_delta_idr
 * @property bool $is_active
 * @property-read Collection<int, OptionIngredient> $ingredients
 */
#[Fillable(['option_group_id', 'name', 'price_delta_idr', 'is_active'])]
class Option extends Model
{
    /** @use HasFactory<OptionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price_delta_idr' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<OptionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    /** @return HasMany<OptionIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(OptionIngredient::class);
    }
}
