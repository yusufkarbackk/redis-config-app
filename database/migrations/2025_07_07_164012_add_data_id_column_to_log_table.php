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
        Schema::table('logs', function (Blueprint $table) {
            // Tambahkan kolom data_id untuk menyimpan ID data terkait
            $table->string('data_id')->nullable()->after('id');

            // Tambahkan index untuk kolom data_id agar query lebih cepat
            $table->index('data_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            // Hapus index dari kolom data_id
            $table->dropIndex(['data_id']);

            // Hapus kolom data_id
            $table->dropColumn('data_id');
        });
    }
};
