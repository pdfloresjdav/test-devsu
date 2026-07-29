<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 (translating the code to English): renames the original tables
 * and columns (created in Spanish) to their English equivalents, instead
 * of editing the already-applied migrations -- this keeps the migration
 * history intact. It was agreed with the user to wipe the local
 * MySQL/LocalStack volumes as part of this phase (it's only test data), so
 * in practice this migration runs against a freshly created database, but
 * the explicit rename still documents the intent and keeps the code
 * correct for any environment where the table already exists under the
 * old names.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('cuentas', 'accounts');
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('cuenta_id', 'account_id');
            $table->renameColumn('saldo', 'balance');
        });

        Schema::rename('transferencias', 'transfers');
        Schema::table('transfers', function (Blueprint $table) {
            $table->renameColumn('transferencia_id', 'transfer_id');
            $table->renameColumn('cuenta_origen', 'source_account');
            $table->renameColumn('cuenta_destino', 'destination_account');
            $table->renameColumn('monto', 'amount');
            $table->renameColumn('descripcion', 'description');
            $table->renameColumn('estado', 'status');
            $table->renameColumn('motivo_falla', 'failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->renameColumn('failure_reason', 'motivo_falla');
            $table->renameColumn('status', 'estado');
            $table->renameColumn('description', 'descripcion');
            $table->renameColumn('amount', 'monto');
            $table->renameColumn('destination_account', 'cuenta_destino');
            $table->renameColumn('source_account', 'cuenta_origen');
            $table->renameColumn('transfer_id', 'transferencia_id');
        });
        Schema::rename('transfers', 'transferencias');

        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('balance', 'saldo');
            $table->renameColumn('account_id', 'cuenta_id');
        });
        Schema::rename('accounts', 'cuentas');
    }
};
