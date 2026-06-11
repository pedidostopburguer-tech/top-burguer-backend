<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // user_id vincula ao Sanctum users para autenticação JWT
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // store_id nulo = role de plataforma (super_admin, saas_support)
            $table->foreignUuid('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
