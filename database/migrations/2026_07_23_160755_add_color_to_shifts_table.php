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
        if (Schema::hasColumn('shifts', 'color')) {
            return;
        }

        Schema::table('shifts', function (Blueprint $table) {
            $table->string('color', 20)->default('blue')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('shifts', 'color')) {
            return;
        }

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
