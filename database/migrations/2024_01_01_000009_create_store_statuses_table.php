<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_open')->default(true);
            $table->boolean('is_auto')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_statuses');
    }
};
