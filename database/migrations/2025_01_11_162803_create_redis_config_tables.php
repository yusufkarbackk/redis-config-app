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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('api_key')->unique();
            $table->timestamps();
        });

        Schema::create('application_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('data_type', ['string', 'number', 'boolean', 'json']);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('database_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('connection_type', ['mysql', 'pgsql']);
            $table->string('host');
            $table->integer('port');
            $table->string('database_name');
            $table->string('username');
            $table->string('password')->nullable()->default('');
            $table->timestamps();
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->string('source'); // Sumber data
            $table->string('destination'); // Tujuan data
            $table->json('data_sent'); // Data yang dikirim
            $table->json('data_received')->nullable(); // Data yang diterima
            $table->timestamp('sent_at')->nullable(); // Waktu pengiriman
            $table->timestamp('received_at')->nullable(); // Waktu diterima
        });

        Schema::create('database_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_config_id')->constrained()->onDelete('cascade');
            $table->string('table_name');
            $table->timestamps();
        });

        Schema::create('application_table_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->foreignId('database_table_id')->constrained('database_tables')->onDelete('cascade');
            $table->string('consumer_group');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_fields');
        Schema::dropIfExists('database_configs');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('logs');
        Schema::dropIfExists('database_tables');
        Schema::dropIfExists('application_table_subscriptions');
    }
};
