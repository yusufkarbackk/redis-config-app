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
        Schema::table('database_configs', function (Blueprint $table) {
            $table->enum('status', ['up', 'down'])
                ->default('up')
                ->after('password')
                ->comment('Status of the database connection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_configs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
