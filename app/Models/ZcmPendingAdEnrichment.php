<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZcmPendingAdEnrichment extends Model
{
    use HasFactory;

    public $table = 'zcm_pending_ad_enrichments';

    protected $fillable = [
        'zcm_pending_ad_id',
        'research',
        'ai_analysis',
        'technical_data',
        'seo',
        'images',
        'confidence_score',
    ];

    protected $casts = [
        'research' => 'array',
        'ai_analysis' => 'array',
        'technical_data' => 'array',
        'seo' => 'array',
        'images' => 'array',
        'confidence_score' => 'integer',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function ad()
    {
        return $this->belongsTo(ZcmPendingAd::class, 'zcm_pending_ad_id');
    }
}
