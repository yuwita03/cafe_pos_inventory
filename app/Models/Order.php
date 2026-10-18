<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $table_id
 * @property int|null $created_by_user_id
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string $source
 * @property string $fulfillment_type
 * @property string $status
 * @property string $checkout_key
 * @property string $public_token
 * @property string $checkout_fingerprint
 * @property int $total_idr
 * @property Carbon $created_at
 */
#[Fillable(['table_id', 'created_by_user_id', 'customer_name', 'customer_email', 'source', 'fulfillment_type', 'status', 'public_token', 'checkout_key', 'checkout_fingerprint', 'total_idr'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['total_idr' => 'integer'];
    }
}
