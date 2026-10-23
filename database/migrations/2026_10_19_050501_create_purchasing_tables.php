<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->text('contact')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('line_cost_idr');
            $table->timestamps();
            $table->unique(['purchase_id', 'stock_item_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE purchases
                    ADD CONSTRAINT purchases_status_valid
                        CHECK (status IN ('draft', 'received', 'cancelled')),
                    ADD CONSTRAINT purchases_receipt_valid
                        CHECK (
                            (
                                status = 'received'
                                AND received_at IS NOT NULL
                                AND received_by_user_id IS NOT NULL
                            )
                            OR (
                                status <> 'received'
                                AND received_at IS NULL
                                AND received_by_user_id IS NULL
                            )
                        )
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE purchase_items
                    ADD CONSTRAINT purchase_items_quantity_positive
                        CHECK (quantity > 0),
                    ADD CONSTRAINT purchase_items_cost_nonnegative
                        CHECK (line_cost_idr >= 0)
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
