<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zcm_pending_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zcmanager_ad_id')->nullable()->unique();
            $table->string('reference')->nullable()->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('category')->nullable();
            $table->string('brand_model')->nullable();
            $table->json('images')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('zcmanager_created_at')->nullable();
            $table->timestamp('zcmanager_updated_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('sync_status')->default('imported');
            $table->timestamp('synced_to_zcmanager_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zcm_pending_ads');
    }
};
