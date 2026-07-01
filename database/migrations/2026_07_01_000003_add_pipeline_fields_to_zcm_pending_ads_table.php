<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zcm_pending_ads', function (Blueprint $table) {
            $table->string('pipeline_status')->default('received')->after('sync_status');
            $table->string('review_status')->default('pending')->after('pipeline_status');
            $table->timestamp('pipeline_started_at')->nullable()->after('review_status');
            $table->timestamp('pipeline_completed_at')->nullable()->after('pipeline_started_at');
            $table->timestamp('exported_at')->nullable()->after('pipeline_completed_at');

            $table->index('pipeline_status');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('zcm_pending_ads', function (Blueprint $table) {
            $table->dropIndex(['pipeline_status']);
            $table->dropIndex(['review_status']);
            $table->dropColumn([
                'pipeline_status',
                'review_status',
                'pipeline_started_at',
                'pipeline_completed_at',
                'exported_at',
            ]);
        });
    }
};
