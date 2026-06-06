<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->text('address');
            $table->string('payment_method', 100);
            $table->jsonb('items');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2);
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->string('status', 50)->default('Realizado');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
