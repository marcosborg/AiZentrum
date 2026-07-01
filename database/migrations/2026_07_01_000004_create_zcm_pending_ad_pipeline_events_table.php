<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zcm_pending_ad_pipeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zcm_pending_ad_id')->constrained('zcm_pending_ads')->cascadeOnDelete();
            $table->string('stage');
            $table->string('status');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['zcm_pending_ad_id', 'stage']);
            $table->index(['stage', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zcm_pending_ad_pipeline_events');
    }
};
