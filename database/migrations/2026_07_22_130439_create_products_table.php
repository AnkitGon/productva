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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('barcode', 100)->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('status')->default('Active');

            // Inventory
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->decimal('reorder_level', 14, 4)->nullable();
            $table->decimal('minimum_stock', 14, 4)->nullable();
            $table->decimal('maximum_stock', 14, 4)->nullable();
            $table->decimal('safety_stock', 14, 4)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();

            // Manufacturing
            $table->boolean('make_to_stock')->default(false);
            $table->boolean('make_to_order')->default(false);
            $table->boolean('bom_required')->default(false);
            $table->boolean('routing_required')->default(false);

            // Traceability
            $table->boolean('lot_tracking')->default(false);
            $table->boolean('serial_tracking')->default(false);
            $table->boolean('expiry_tracking')->default(false);

            // Purchasing
            $table->unsignedBigInteger('preferred_supplier_id')->nullable()->index();
            $table->string('supplier_sku', 100)->nullable();
            $table->foreignId('purchase_uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('purchase_price', 14, 4)->nullable();

            // Sales
            $table->decimal('selling_price', 14, 4)->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->decimal('weight', 14, 4)->nullable();
            $table->string('dimensions', 100)->nullable();

            // Media
            $table->string('image_path')->nullable();
            $table->string('datasheet_path')->nullable();
            $table->string('safety_sheet_path')->nullable();
            $table->string('technical_drawing_path')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'sku']);
            $table->index(['organization_id', 'barcode']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'category_id']);
            $table->index(['organization_id', 'track_inventory']);
            $table->index(['organization_id', 'lot_tracking']);
            $table->index(['organization_id', 'serial_tracking']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
