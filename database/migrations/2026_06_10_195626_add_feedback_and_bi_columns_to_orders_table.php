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
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('rating')->nullable();
            $table->text('feedback_text')->nullable();
            $table->timestampTz('production_started_at')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->string('channel', 20)->default('delivery');
            $table->string('table_number', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'rating',
                'feedback_text',
                'production_started_at',
                'dispatched_at',
                'channel',
                'table_number',
            ]);
        });
    }
};
