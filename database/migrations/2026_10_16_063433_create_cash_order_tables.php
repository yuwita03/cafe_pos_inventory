<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_id')->nullable()->constrained('cafe_tables')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('customer_name', 120);
            $table->string('source', 20);
            $table->string('fulfillment_type', 20);
            $table->string('status', 30);
            $table->string('public_token', 64)->unique();
            $table->uuid('checkout_key')->unique();
            $table->char('checkout_fingerprint', 64);
            $table->unsignedBigInteger('total_idr');
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['created_by_user_id', 'created_at']);
            $table->index(['table_id', 'created_at']);
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot', 120);
            $table->string('variant_name_snapshot', 100);
            $table->unsignedBigInteger('unit_price_idr');
            $table->unsignedInteger('quantity');
        });
        Schema::create('order_item_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('option_id')->constrained()->restrictOnDelete();
            $table->string('option_name_snapshot', 100);
            $table->unsignedBigInteger('price_delta_idr');
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('method', 10);
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->unsignedBigInteger('amount_idr');
            $table->string('status', 15);
            $table->unsignedBigInteger('cash_received_idr')->nullable();
            $table->unsignedBigInteger('change_idr')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'status']);
        });
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('status', 10);
            $table->timestamp('expires_at')->nullable();
            $table->unique(['order_id', 'stock_item_id']);
            $table->index(['stock_item_id', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('awaiting_payment','queued','preparing','ready','completed','cancelled','payment_review')), ADD CONSTRAINT orders_total_positive CHECK (total_idr > 0), ADD CONSTRAINT orders_source_valid CHECK (source IN ('cashier','table_qr')), ADD CONSTRAINT orders_fulfillment_valid CHECK ((fulfillment_type = 'dine_in' AND table_id IS NOT NULL) OR (fulfillment_type = 'takeaway' AND table_id IS NULL)), ADD CONSTRAINT orders_actor_valid CHECK ((source = 'cashier' AND created_by_user_id IS NOT NULL) OR (source = 'table_qr' AND created_by_user_id IS NULL AND fulfillment_type = 'dine_in'))");
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0), ADD CONSTRAINT order_items_price_nonnegative CHECK (unit_price_idr >= 0)');
            DB::statement('ALTER TABLE order_item_options ADD CONSTRAINT order_item_options_price_nonnegative CHECK (price_delta_idr >= 0)');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_valid CHECK (method IN ('cash','qris')), ADD CONSTRAINT payments_status_valid CHECK (status IN ('pending','paid','failed','expired','refunded')), ADD CONSTRAINT payments_amount_positive CHECK (amount_idr > 0), ADD CONSTRAINT payments_cash_valid CHECK (method <> 'cash' OR (cash_received_idr IS NOT NULL AND cash_received_idr >= amount_idr AND change_idr IS NOT NULL AND change_idr = cash_received_idr - amount_idr))");
            DB::statement("CREATE UNIQUE INDEX payments_one_paid_per_order ON payments (order_id) WHERE status = 'paid'");
            DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT reservations_quantity_positive CHECK (quantity > 0), ADD CONSTRAINT reservations_status_valid CHECK (status IN ('active','consumed','released'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
