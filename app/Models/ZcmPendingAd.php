<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZcmPendingAd extends Model
{
    use SoftDeletes, HasFactory;

    public const SYNC_STATUS_IMPORTED = 'imported';
    public const SYNC_STATUS_SENT = 'sent';
    public const SYNC_STATUS_MARK_FAILED = 'mark_failed';

    public const PIPELINE_STATUS_RECEIVED = 'received';
    public const PIPELINE_STATUS_RESEARCHING = 'researching';
    public const PIPELINE_STATUS_ANALYZING = 'analyzing';
    public const PIPELINE_STATUS_ENRICHING = 'enriching';
    public const PIPELINE_STATUS_IMAGES_PENDING = 'images_pending';
    public const PIPELINE_STATUS_IMAGE_GENERATION_PENDING = 'image_generation_pending';
    public const PIPELINE_STATUS_SEO_PENDING = 'seo_pending';
    public const PIPELINE_STATUS_HUMAN_REVIEW = 'human_review';
    public const PIPELINE_STATUS_READY_TO_EXPORT = 'ready_to_export';
    public const PIPELINE_STATUS_EXPORTED = 'exported';
    public const PIPELINE_STATUS_FAILED = 'failed';

    public const REVIEW_STATUS_PENDING = 'pending';
    public const REVIEW_STATUS_APPROVED = 'approved';
    public const REVIEW_STATUS_REJECTED = 'rejected';
    public const REVIEW_STATUS_NEEDS_CHANGES = 'needs_changes';

    public const PIPELINE_STATUS_LABELS = [
        self::PIPELINE_STATUS_RECEIVED => 'Recebido',
        self::PIPELINE_STATUS_RESEARCHING => 'Pesquisa',
        self::PIPELINE_STATUS_ANALYZING => 'Analise IA',
        self::PIPELINE_STATUS_ENRICHING => 'Enriquecimento',
        self::PIPELINE_STATUS_IMAGES_PENDING => 'Imagens',
        self::PIPELINE_STATUS_IMAGE_GENERATION_PENDING => 'Imagem IA',
        self::PIPELINE_STATUS_SEO_PENDING => 'SEO',
        self::PIPELINE_STATUS_HUMAN_REVIEW => 'Validacao humana',
        self::PIPELINE_STATUS_READY_TO_EXPORT => 'Pronto para exportacao',
        self::PIPELINE_STATUS_EXPORTED => 'Exportado',
        self::PIPELINE_STATUS_FAILED => 'Falhado',
    ];

    public const REVIEW_STATUS_LABELS = [
        self::REVIEW_STATUS_PENDING => 'Pendente',
        self::REVIEW_STATUS_APPROVED => 'Aprovado',
        self::REVIEW_STATUS_REJECTED => 'Rejeitado',
        self::REVIEW_STATUS_NEEDS_CHANGES => 'Alteracoes necessarias',
    ];

    public $table = 'zcm_pending_ads';

    protected $fillable = [
        'zcmanager_ad_id',
        'reference',
        'title',
        'description',
        'price',
        'category',
        'brand_model',
        'images',
        'requested_by',
        'status',
        'zcmanager_created_at',
        'zcmanager_updated_at',
        'raw_payload',
        'sync_status',
        'pipeline_status',
        'review_status',
        'pipeline_started_at',
        'pipeline_completed_at',
        'exported_at',
        'synced_to_zcmanager_at',
    ];

    protected $casts = [
        'images' => 'array',
        'raw_payload' => 'array',
        'price' => 'decimal:2',
        'zcmanager_created_at' => 'datetime',
        'zcmanager_updated_at' => 'datetime',
        'synced_to_zcmanager_at' => 'datetime',
        'pipeline_started_at' => 'datetime',
        'pipeline_completed_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function enrichment()
    {
        return $this->hasOne(ZcmPendingAdEnrichment::class, 'zcm_pending_ad_id');
    }

    public function pipelineEvents()
    {
        return $this->hasMany(ZcmPendingAdPipelineEvent::class, 'zcm_pending_ad_id')->latest();
    }

    public function getPipelineStatusLabelAttribute(): string
    {
        return self::PIPELINE_STATUS_LABELS[$this->pipeline_status] ?? (string) $this->pipeline_status;
    }

    public function getReviewStatusLabelAttribute(): string
    {
        return self::REVIEW_STATUS_LABELS[$this->review_status] ?? (string) $this->review_status;
    }

    public function getBrandModelDataAttribute(): array
    {
        return $this->structuredValue('brand_model');
    }

    public function getRequestedByDataAttribute(): array
    {
        return $this->structuredValue('requested_by');
    }

    private function structuredValue(string $key): array
    {
        $raw = data_get($this->raw_payload, $key);

        if (is_array($raw)) {
            return $raw;
        }

        $value = $this->{$key};

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : ['value' => $value];
        }

        return [];
    }
}
