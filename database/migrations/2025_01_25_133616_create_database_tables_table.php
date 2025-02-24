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
        Schema::create('database_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_config_id')->constrained();
            $table->string('table_name');
            $table->foreignId('application_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_tables');
    }
};
