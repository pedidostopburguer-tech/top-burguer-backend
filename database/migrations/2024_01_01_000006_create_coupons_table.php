<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2)->default(0.00);
            $table->decimal('min_order_value', 10, 2)->default(0.00);
            $table->integer('max_uses')->nullable();
            $table->integer('current_uses')->default(0);
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['store_id', 'code', 'is_active']);
        });

        // Índice parcial único: impede código duplicado entre campanhas ativas da mesma loja.
        // Permite reutilização sazonal — o mesmo código pode existir em campanhas antigas
        // inativas ou esgotadas, preservando histórico de relatórios.
        DB::statement('
            CREATE UNIQUE INDEX idx_unique_active_coupon_per_store
            ON coupons (store_id, code)
            WHERE is_active = true AND (max_uses IS NULL OR current_uses < max_uses)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_unique_active_coupon_per_store');
        Schema::dropIfExists('coupons');
    }
};
