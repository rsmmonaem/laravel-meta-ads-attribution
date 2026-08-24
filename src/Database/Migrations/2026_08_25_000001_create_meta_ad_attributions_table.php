<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_attributions', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('fbclid')->nullable()->index();
            $table->string('fbc')->nullable();
            $table->string('fbp')->nullable();
            $table->string('utm_source')->nullable()->index();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable()->index();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('campaign_id')->nullable()->index();
            $table->string('adset_id')->nullable();
            $table->string('ad_id')->nullable();
            $table->text('landing_page')->nullable();
            $table->text('referrer')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('first_touch_at')->nullable();
            $table->timestamp('last_touch_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_attributions');
    }
};
