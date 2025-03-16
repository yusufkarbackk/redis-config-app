<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('log', function (Blueprint $table) {
            $table->string('source'); // Sumber data
            $table->string('destination'); // Tujuan data
            $table->json('data_sent'); // Data yang dikirim
            $table->json('data_received')->nullable(); // Data yang diterima
            $table->timestamp('sent_at')->nullable(); // Waktu pengiriman
            $table->timestamp('received_at')->nullable(); // Waktu diterima
            $table->dropColumn('log'); // Replace 'column_name' with the actual column name
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log', function (Blueprint $table) {
            $table->string('column_name')->nullable(); // Adjust the column type if needed
        });
    }
};
