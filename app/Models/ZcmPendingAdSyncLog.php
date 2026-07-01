<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZcmPendingAdSyncLog extends Model
{
    use HasFactory;

    public $table = 'zcm_pending_ad_sync_logs';

    protected $fillable = [
        'ran_at',
        'total_received',
        'total_imported',
        'total_failed',
        'imported_ids',
        'failed_items',
        'errors',
        'mark_as_sent_success',
        'mark_as_sent_response',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
        'imported_ids' => 'array',
        'failed_items' => 'array',
        'errors' => 'array',
        'mark_as_sent_success' => 'boolean',
        'mark_as_sent_response' => 'array',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
