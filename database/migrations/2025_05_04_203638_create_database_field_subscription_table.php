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
        Schema::create('database_field_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_table_subscription_id');
            $table->foreign('application_table_subscription_id', 'fk_app_table_sub')
                ->references('id')
                ->on('application_table_subscriptions')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreignId('application_field_id')
                ->constrained('application_fields')
                ->onDelete('cascade');
            $table->string('mapped_to');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_field_subscription');
    }
};
