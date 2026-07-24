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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->constrained('warehouse_locations')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('lot_number', 100)->default('');
            $table->string('serial_number', 100)->default('');
            $table->decimal('quantity_on_hand', 16, 4)->default(0);
            $table->decimal('quantity_reserved', 16, 4)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['plant_id', 'warehouse_id', 'warehouse_location_id', 'product_id', 'lot_number', 'serial_number'],
                'inventories_balance_unique'
            );
            $table->index(['organization_id', 'plant_id']);
            $table->index(['plant_id', 'product_id']);
            $table->index(['warehouse_id']);
            $table->index(['warehouse_location_id']);
            $table->index(['lot_number']);
            $table->index(['serial_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
