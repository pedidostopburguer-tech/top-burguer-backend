<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('number', 10);
            $table->string('qr_token', 32)->unique();
            $table->integer('capacity')->nullable();
            $table->string('status', 20)->default('livre');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Índice parcial único: impede duas mesas ATIVAS com o mesmo número na mesma loja.
        // Mesas desativadas (is_active = false) não bloqueiam o reuso do número — ao
        // desativar uma mesa, uma nova mesa pode ser criada com o mesmo `number`,
        // preservando o histórico (orders.table_number) da mesa antiga.
        DB::statement('
            CREATE UNIQUE INDEX idx_unique_active_table_number_per_store
            ON tables (store_id, number)
            WHERE is_active = true
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_unique_active_table_number_per_store');
        Schema::dropIfExists('tables');
    }
};
