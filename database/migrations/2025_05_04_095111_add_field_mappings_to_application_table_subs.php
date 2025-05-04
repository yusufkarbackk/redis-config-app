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
        Schema::table('application_table_subscriptions', function (Blueprint $table) {
            $table->json('field_mappings')->nullable(); // JSON field mapping
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_table_subscriptions', function (Blueprint $table) {
            $table->dropColumn('field_mappings'); // Remove the field mapping column
        });
    }
};
