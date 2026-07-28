<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias', function (Blueprint $table) {
            $table->uuid('transferencia_id')->primary();
            $table->string('idempotency_key')->unique();
            $table->string('cuenta_origen');
            $table->string('cuenta_destino');
            $table->decimal('monto', 15, 2);
            $table->string('descripcion')->nullable();
            $table->string('estado');
            $table->string('motivo_falla')->nullable();
            $table->timestamps();

            $table->index('cuenta_origen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias');
    }
};
