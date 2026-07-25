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
        Schema::table('inventories', function (Blueprint $table) {
            $table->decimal('quantity_incoming', 16, 4)->default(0.0000)->after('quantity_reserved');
            $table->decimal('quantity_outgoing', 16, 4)->default(0.0000)->after('quantity_incoming');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn(['quantity_incoming', 'quantity_outgoing']);
        });
    }
};
