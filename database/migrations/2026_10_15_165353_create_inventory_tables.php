<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('base_unit', 3);
            $table->decimal('on_hand_qty', 12, 3)->default(0);
            $table->decimal('reorder_point', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->foreignId('stock_item_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('recipe_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
            $table->unique(['product_variant_id', 'stock_item_id']);
        });
        Schema::create('option_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('option_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('additional_qty', 12, 3);
            $table->timestamps();
            $table->unique(['option_id', 'stock_item_id']);
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_delta', 12, 3);
            $table->string('reason', 500);
            $table->uuid('source_key')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['stock_item_id', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE stock_items ADD CONSTRAINT stock_items_unit_valid CHECK (base_unit IN ('g', 'ml', 'pcs')), ADD CONSTRAINT stock_items_on_hand_nonnegative CHECK (on_hand_qty >= 0), ADD CONSTRAINT stock_items_reorder_nonnegative CHECK (reorder_point >= 0)");
            DB::statement('ALTER TABLE recipe_items ADD CONSTRAINT recipe_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE option_ingredients ADD CONSTRAINT option_ingredients_quantity_nonnegative CHECK (additional_qty >= 0)');
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_nonzero CHECK (quantity_delta <> 0), ADD CONSTRAINT stock_movements_reason_required CHECK (length(trim(reason)) > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('option_ingredients');
        Schema::dropIfExists('recipe_items');
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_item_id');
        });
        Schema::dropIfExists('stock_items');
    }
};
