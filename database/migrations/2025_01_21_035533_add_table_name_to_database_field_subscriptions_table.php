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
        Schema::table('database_field_subscriptions', function (Blueprint $table) {
            $table->string('table_name')->after('application_field_id')->nullable(); // Add table_name column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_field_subscriptions', function (Blueprint $table) {
            $table->dropColumn('table_name'); // Rollback
        });
    }
};
