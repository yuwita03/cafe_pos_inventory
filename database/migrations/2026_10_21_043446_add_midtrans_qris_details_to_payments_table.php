<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->text('qr_image_url')->nullable();
            $table->timestamp('qris_expires_at')->nullable();
            $table->timestamp('charge_requested_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['provider_transaction_id']);
            $table->dropColumn(['provider_transaction_id', 'qr_image_url', 'qris_expires_at', 'charge_requested_at']);
        });
    }
};
