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
        Schema::table('routing_headers', function (Blueprint $table) {
            $table->boolean('is_primary')->default(true)->after('version');
            $table->string('routing_name', 100)->nullable()->after('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routing_headers', function (Blueprint $table) {
            $table->dropColumn(['is_primary', 'routing_name']);
        });
    }
};
