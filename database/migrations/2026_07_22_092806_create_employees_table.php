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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('display_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title')->nullable();
            $table->foreignId('manager_id')->nullable()->references('id')->on('employees')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->enum('employment_type', ['Full-Time', 'Part-Time', 'Contract', 'Temporary', 'Intern'])->default('Full-Time');
            $table->date('hire_date')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'On Leave', 'Terminated'])->default('Active');
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'employee_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
