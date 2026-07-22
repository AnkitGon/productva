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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_type_id')->constrained('warehouse_types')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->string('status')->default('Active');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'plant_id']);
            $table->index(['plant_id', 'code']);
            $table->index(['plant_id', 'status']);
            $table->index(['plant_id', 'is_default']);
            $table->index(['warehouse_type_id']);
            $table->index(['manager_employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
