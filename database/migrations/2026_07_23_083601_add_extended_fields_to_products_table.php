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
        Schema::table('products', function (Blueprint $table) {
            // Inventory
            $table->string('inventory_valuation_method', 30)->default('FIFO')->after('lead_time_days');
            $table->foreignId('default_warehouse_id')->nullable()->after('inventory_valuation_method')->constrained('warehouses')->nullOnDelete();
            $table->decimal('economic_order_quantity', 14, 4)->nullable()->after('default_warehouse_id');

            // Traceability
            $table->unsignedInteger('shelf_life_days')->nullable()->after('expiry_tracking');

            // Manufacturing
            $table->boolean('backflush_material')->default(false)->after('routing_required');
            $table->foreignId('manufacturing_uom_id')->nullable()->after('backflush_material')->constrained('units_of_measure')->nullOnDelete();

            // Sales
            $table->foreignId('sales_uom_id')->nullable()->after('selling_price')->constrained('units_of_measure')->nullOnDelete();
            $table->string('tax_class', 50)->nullable()->after('sales_uom_id');
            $table->string('hsn_sac_code', 30)->nullable()->after('tax_class');
            $table->decimal('default_discount', 8, 4)->nullable()->after('hsn_sac_code');

            // Additional
            $table->string('brand', 100)->nullable()->after('technical_drawing_path');
            $table->string('manufacturer', 150)->nullable()->after('brand');
            $table->string('country_of_origin', 100)->nullable()->after('manufacturer');
            $table->string('abc_classification', 1)->nullable()->after('country_of_origin');
            $table->string('xyz_classification', 1)->nullable()->after('abc_classification');
            $table->text('notes')->nullable()->after('xyz_classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_warehouse_id');
            $table->dropConstrainedForeignId('manufacturing_uom_id');
            $table->dropConstrainedForeignId('sales_uom_id');

            $table->dropColumn([
                'inventory_valuation_method',
                'economic_order_quantity',
                'shelf_life_days',
                'backflush_material',
                'tax_class',
                'hsn_sac_code',
                'default_discount',
                'brand',
                'manufacturer',
                'country_of_origin',
                'abc_classification',
                'xyz_classification',
                'notes',
            ]);
        });
    }
};
