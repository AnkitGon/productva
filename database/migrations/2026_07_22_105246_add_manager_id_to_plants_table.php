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
        Schema::table('plants', function (Blueprint $table) {
            $table->foreignId('manager_id')
                ->nullable()
                ->after('email')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn('manager_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->string('manager_name')->nullable()->after('email');
        });
    }
};
