<?php

use Database\Support\SoftDeleteAwareUnique;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Active-only unique indexes must be created after all Schema::table alters.
     * SQLite recreates tables on alter and would strip partial unique indexes.
     */
    public function up(): void
    {
        SoftDeleteAwareUnique::create('plants', ['organization_id', 'code'], 'plants_code_unique');
        SoftDeleteAwareUnique::create('plants', ['organization_id', 'slug'], 'plants_slug_unique');
        SoftDeleteAwareUnique::create('departments', ['organization_id', 'plant_id', 'code'], 'departments_code_unique');
        SoftDeleteAwareUnique::create('employees', ['organization_id', 'plant_id', 'employee_code'], 'employees_code_unique');
        SoftDeleteAwareUnique::create('employees', ['user_id'], 'employees_user_unique');
        SoftDeleteAwareUnique::create('warehouse_locations', ['warehouse_id', 'code'], 'warehouse_locations_code_unique');
        SoftDeleteAwareUnique::create('operations', ['organization_id', 'code'], 'operations_code_unique');
        SoftDeleteAwareUnique::create('bom_headers', ['organization_id', 'product_id', 'version'], 'bom_headers_version_unique');
        SoftDeleteAwareUnique::create(
            'routing_headers',
            ['organization_id', 'plant_id', 'product_id', 'version'],
            'routing_headers_version_unique'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
