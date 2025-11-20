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
            // Tambahkan kolom baru setelah 'mapped_to'
            // Dibuat nullable() karena tidak semua mapping butuh default value
            $table->string('default_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_field_subscriptions', function (Blueprint $table) {
            $table->dropColumn('default_value');
        });
    }
};
