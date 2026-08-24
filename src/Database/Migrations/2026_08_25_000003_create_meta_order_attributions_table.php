<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_order_attributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique()->index();
            $table->string('order_number')->nullable()->index();
            $table->string('visitor_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('attribution_source')->default('direct')->index(); // meta, google, direct, etc.
            $table->string('attribution_medium')->nullable();
            $table->string('campaign')->nullable()->index();
            $table->string('campaign_id')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('ad_id')->nullable();
            $table->string('fbclid')->nullable()->index();
            $table->string('fbc')->nullable();
            $table->string('fbp')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->decimal('order_amount', 12, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->string('attribution_model')->default('first_paid_touch');
            $table->timestamp('first_touch_at')->nullable();
            $table->timestamp('last_touch_at')->nullable();
            $table->timestamp('attributed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_order_attributions');
    }
};
