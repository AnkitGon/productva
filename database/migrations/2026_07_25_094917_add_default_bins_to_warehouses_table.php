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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('default_receiving_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('default_picking_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['default_receiving_location_id']);
            $table->dropColumn('default_receiving_location_id');
            $table->dropForeign(['default_picking_location_id']);
            $table->dropColumn('default_picking_location_id');
        });
    }
};
