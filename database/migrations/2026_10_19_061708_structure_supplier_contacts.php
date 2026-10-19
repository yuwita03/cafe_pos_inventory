<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('suppliers')->whereNotNull('contact')->orderBy('id')->each(function (object $supplier): void {
            $values = array_values(array_filter(array_map('trim', explode("\n", $supplier->contact)), fn (string $value): bool => $value !== ''));
            $contacts = array_map(fn (string $value): array => ['type' => filter_var($value, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone', 'value' => $value], $values);
            DB::table('suppliers')->where('id', $supplier->id)->update(['contact' => $contacts === [] ? null : json_encode($contacts, JSON_THROW_ON_ERROR)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('suppliers')->whereNotNull('contact')->orderBy('id')->each(function (object $supplier): void {
            $contacts = json_decode($supplier->contact, true, 512, JSON_THROW_ON_ERROR);
            DB::table('suppliers')->where('id', $supplier->id)->update(['contact' => $contacts === [] ? null : implode("\n", array_column($contacts, 'value'))]);
        });
    }
};
