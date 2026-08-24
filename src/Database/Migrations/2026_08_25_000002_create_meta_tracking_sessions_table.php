<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('visitor_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('fbclid')->nullable();
            $table->string('fbc')->nullable();
            $table->string('fbp')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->text('current_url')->nullable();
            $table->text('referrer')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_tracking_sessions');
    }
};
