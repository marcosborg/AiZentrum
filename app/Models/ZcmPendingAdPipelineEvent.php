<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZcmPendingAdPipelineEvent extends Model
{
    use HasFactory;

    public $table = 'zcm_pending_ad_pipeline_events';

    protected $fillable = [
        'zcm_pending_ad_id',
        'stage',
        'status',
        'input',
        'output',
        'error',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function ad()
    {
        return $this->belongsTo(ZcmPendingAd::class, 'zcm_pending_ad_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
