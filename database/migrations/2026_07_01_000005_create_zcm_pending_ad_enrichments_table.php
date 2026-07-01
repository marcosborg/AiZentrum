<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zcm_pending_ad_enrichments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zcm_pending_ad_id')->unique()->constrained('zcm_pending_ads')->cascadeOnDelete();
            $table->json('research')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->json('technical_data')->nullable();
            $table->json('seo')->nullable();
            $table->json('images')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zcm_pending_ad_enrichments');
    }
};
