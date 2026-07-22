<?php

use App\Models\Department;
use App\Models\Plant;
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
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('plant_id')
                ->nullable()
                ->after('organization_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        Department::query()->each(function (Department $department): void {
            $plantId = Plant::query()
                ->where('organization_id', $department->organization_id)
                ->where('is_default', true)
                ->value('id')
                ?? Plant::query()
                    ->where('organization_id', $department->organization_id)
                    ->value('id');

            if ($plantId) {
                $department->update(['plant_id' => $plantId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plant_id');
        });
    }
};
