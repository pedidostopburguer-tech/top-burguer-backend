<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            // Singleton por loja: cada store tem exatamente um registro de settings
            $table->foreignUuid('store_id')->unique()->constrained()->cascadeOnDelete();
            // Campos alinhados com o schema do Supabase para compatibilidade com o frontend
            $table->string('store_name')->nullable();
            $table->text('store_description')->nullable();
            $table->text('store_address')->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->text('maps_url')->nullable();
            // opening_hours: [{ day: "Segunda-feira", hours: "18:00h às 04:00h" }]
            $table->json('opening_hours')->nullable();
            // neighborhood_fees: { "centro": 5.00, "laranjeiras": 6.00, ... }
            $table->json('neighborhood_fees')->nullable();
            $table->decimal('minimum_order', 10, 2)->default(0.00);
            $table->decimal('default_delivery_fee', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
