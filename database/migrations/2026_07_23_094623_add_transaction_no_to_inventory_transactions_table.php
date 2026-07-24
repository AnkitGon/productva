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
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('transaction_no', 30)->nullable()->after('serial_number');
            $table->index(['organization_id', 'transaction_no']);
            $table->index('transaction_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'transaction_no']);
            $table->dropIndex(['transaction_no']);
            $table->dropColumn('transaction_no');
        });
    }
};
