<?php

namespace App\Models;

use Database\Factories\OptionGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $name
 * @property string $selection_type
 * @property bool $is_active
 * @property-read Collection<int, Option> $options
 * @property-read Pivot $pivot
 */
#[Fillable(['name', 'selection_type', 'is_active'])]
class OptionGroup extends Model
{
    /** @use HasFactory<OptionGroupFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Option, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('id');
    }

    /** @return BelongsToMany<Product, $this, Pivot> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_option_groups')->withPivot('is_required');
    }
}
