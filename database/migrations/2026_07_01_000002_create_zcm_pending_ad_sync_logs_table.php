<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zcm_pending_ad_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ran_at');
            $table->unsignedInteger('total_received')->default(0);
            $table->unsignedInteger('total_imported')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->json('imported_ids')->nullable();
            $table->json('failed_items')->nullable();
            $table->json('errors')->nullable();
            $table->boolean('mark_as_sent_success')->default(false);
            $table->json('mark_as_sent_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zcm_pending_ad_sync_logs');
    }
};
