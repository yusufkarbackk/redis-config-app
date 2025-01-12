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
            $table->enum('connection_type', ['mysql', 'postgres']);
            $table->string('host');
            $table->integer('port');
            $table->string('database_name');
            $table->string('username');
            $table->string('password');
            $table->string('consumer_group')->unique();
            $table->timestamps();
        });

        Schema::create('database_field_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_config_id')->constrained()->onDelete('cascade');
            $table->foreignId('application_field_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate subscriptions
            $table->unique(['database_config_id', 'application_field_id'], 'unique_subscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_field_subscriptions');
        Schema::dropIfExists('application_fields');
        Schema::dropIfExists('database_configs');
        Schema::dropIfExists('applications');    }
};
