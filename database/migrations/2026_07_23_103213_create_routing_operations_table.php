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
        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_header_id')->constrained('routing_headers')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('operation_id')->nullable()->constrained('operations')->nullOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->restrictOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->decimal('setup_time_minutes', 12, 2)->default(0);
            $table->decimal('run_time_per_unit', 12, 4);
            $table->decimal('labor_time', 12, 2)->default(0);
            $table->decimal('queue_time', 12, 2)->default(0);
            $table->decimal('move_time', 12, 2)->default(0);
            $table->decimal('wait_time', 12, 2)->default(0);
            $table->decimal('overlap_percent', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['routing_header_id', 'sequence']);
            $table->index(['routing_header_id', 'operation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_operations');
    }
};
