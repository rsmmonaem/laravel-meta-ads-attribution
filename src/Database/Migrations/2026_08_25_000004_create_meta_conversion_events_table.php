<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_conversion_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('event_id')->unique()->index(); // e.g. purchase_10025
            $table->string('event_name')->default('Purchase')->index();
            $table->string('action_source')->default('website');
            $table->string('status')->default('pending')->index(); // pending, sent, failed
            $table->integer('http_status')->nullable();
            $table->json('user_data_hashed')->nullable();
            $table->json('custom_data')->nullable();
            $table->json('meta_response')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('first_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_conversion_events');
    }
};
