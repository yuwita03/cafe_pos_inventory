<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedBigInteger('price_idr');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });
        Schema::create('option_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('selection_type', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedBigInteger('price_delta_idr')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['option_group_id', 'name']);
        });
        Schema::create('product_option_groups', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('option_group_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required')->default(false);
            $table->primary(['product_id', 'option_group_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE product_variants
                    ADD CONSTRAINT product_variants_price_nonnegative
                        CHECK (price_idr >= 0)
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE options
                    ADD CONSTRAINT options_price_nonnegative
                        CHECK (price_delta_idr >= 0)
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE option_groups
                    ADD CONSTRAINT option_groups_selection_valid
                        CHECK (selection_type IN ('single', 'multiple'))
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_groups');
        Schema::dropIfExists('options');
        Schema::dropIfExists('option_groups');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
