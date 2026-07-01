<?php

namespace App\Services;

use App\Models\ZcmPendingAd;
use App\Models\ZcmPendingAdEnrichment;
use App\Models\ZcmPendingAdPipelineEvent;
use Illuminate\Support\Facades\DB;

class ZcmAdPipelineService
{
    public function __construct(
        private readonly ZcmAdResearchService $research,
        private readonly ZcmAdAiAnalysisService $analysis,
        private readonly ZcmAdSeoService $seo,
        private readonly ZcmAdImageService $images,
        private readonly ZcmAdPublishingService $publishing
    ) {
    }

    public function runStage(ZcmPendingAd $ad, string $stage, ?int $userId = null): ZcmPendingAdPipelineEvent
    {
        return match ($stage) {
            'research' => $this->execute($ad, 'research', ZcmPendingAd::PIPELINE_STATUS_RESEARCHING, function () use ($ad) {
                return $this->research->research($ad);
            }, function (ZcmPendingAd $ad, array $output) {
                $this->enrichment($ad)->update(['research' => $output]);
                $ad->update(['pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_ANALYZING]);
            }, $userId),
            'analysis' => $this->execute($ad, 'analysis', ZcmPendingAd::PIPELINE_STATUS_ANALYZING, function () use ($ad) {
                return $this->analysis->analyze($ad);
            }, function (ZcmPendingAd $ad, array $output) {
                $this->enrichment($ad)->update([
                    'ai_analysis' => $output,
                    'technical_data' => $output['technical_data'] ?? null,
                    'confidence_score' => $output['confidence_score'] ?? null,
                ]);
                $ad->update(['pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_IMAGES_PENDING]);
            }, $userId),
            'images' => $this->execute($ad, 'images', ZcmPendingAd::PIPELINE_STATUS_IMAGES_PENDING, function () use ($ad) {
                return $this->images->prepare($ad);
            }, function (ZcmPendingAd $ad, array $output) {
                $this->enrichment($ad)->update(['images' => $output]);
                $ad->update(['pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_SEO_PENDING]);
            }, $userId),
            'seo' => $this->execute($ad, 'seo', ZcmPendingAd::PIPELINE_STATUS_SEO_PENDING, function () use ($ad) {
                return $this->seo->generate($ad);
            }, function (ZcmPendingAd $ad, array $output) {
                $this->enrichment($ad)->update(['seo' => $output]);
                $ad->update([
                    'pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_HUMAN_REVIEW,
                    'pipeline_completed_at' => now(),
                ]);
            }, $userId),
            'publishing' => $this->execute($ad, 'publishing', ZcmPendingAd::PIPELINE_STATUS_READY_TO_EXPORT, function () use ($ad) {
                return $this->publishing->prepare($ad);
            }, function (ZcmPendingAd $ad, array $output) {
                $this->enrichment($ad)->update(['technical_data' => array_merge($ad->enrichment?->technical_data ?? [], [
                    'publishing' => $output,
                ])]);
            }, $userId),
            default => throw new \InvalidArgumentException("Pipeline stage {$stage} is not supported."),
        };
    }

    public function approve(ZcmPendingAd $ad, ?int $userId = null): void
    {
        DB::transaction(function () use ($ad, $userId) {
            $ad->update([
                'review_status' => ZcmPendingAd::REVIEW_STATUS_APPROVED,
                'pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_READY_TO_EXPORT,
            ]);

            $this->recordEvent($ad, 'human_review', 'approved', [], [
                'message' => 'Anuncio aprovado para exportacao.',
            ], null, $userId);
        });
    }

    public function reject(ZcmPendingAd $ad, string $reason = '', ?int $userId = null): void
    {
        DB::transaction(function () use ($ad, $reason, $userId) {
            $ad->update([
                'review_status' => ZcmPendingAd::REVIEW_STATUS_REJECTED,
                'pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_HUMAN_REVIEW,
            ]);

            $this->recordEvent($ad, 'human_review', 'rejected', ['reason' => $reason], [
                'message' => 'Anuncio rejeitado na validacao humana.',
            ], null, $userId);
        });
    }

    public function requestChanges(ZcmPendingAd $ad, string $reason = '', ?int $userId = null): void
    {
        DB::transaction(function () use ($ad, $reason, $userId) {
            $ad->update([
                'review_status' => ZcmPendingAd::REVIEW_STATUS_NEEDS_CHANGES,
                'pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_HUMAN_REVIEW,
            ]);

            $this->recordEvent($ad, 'human_review', 'needs_changes', ['reason' => $reason], [
                'message' => 'Anuncio necessita alteracoes.',
            ], null, $userId);
        });
    }

    public function recordEvent(
        ZcmPendingAd $ad,
        string $stage,
        string $status,
        ?array $input = null,
        ?array $output = null,
        ?string $error = null,
        ?int $userId = null
    ): ZcmPendingAdPipelineEvent {
        return ZcmPendingAdPipelineEvent::create([
            'zcm_pending_ad_id' => $ad->id,
            'stage' => $stage,
            'status' => $status,
            'input' => $input,
            'output' => $output,
            'error' => $error,
            'started_at' => now(),
            'finished_at' => now(),
            'created_by' => $userId,
        ]);
    }

    private function execute(
        ZcmPendingAd $ad,
        string $stage,
        string $runningStatus,
        callable $work,
        callable $onSuccess,
        ?int $userId = null
    ): ZcmPendingAdPipelineEvent {
        $ad->update([
            'pipeline_status' => $runningStatus,
            'pipeline_started_at' => $ad->pipeline_started_at ?: now(),
        ]);

        try {
            $output = $work();

            DB::transaction(function () use ($ad, $output, $onSuccess) {
                $ad->refresh()->load('enrichment');
                $onSuccess($ad, $output);
            });

            return $this->recordEvent($ad, $stage, 'success', [
                'ad_id' => $ad->id,
                'reference' => $ad->reference,
            ], $output, null, $userId);
        } catch (\Throwable $e) {
            $ad->update(['pipeline_status' => ZcmPendingAd::PIPELINE_STATUS_FAILED]);

            return $this->recordEvent($ad, $stage, 'failed', [
                'ad_id' => $ad->id,
                'reference' => $ad->reference,
            ], null, $e->getMessage(), $userId);
        }
    }

    private function enrichment(ZcmPendingAd $ad): ZcmPendingAdEnrichment
    {
        return ZcmPendingAdEnrichment::firstOrCreate([
            'zcm_pending_ad_id' => $ad->id,
        ]);
    }
}
