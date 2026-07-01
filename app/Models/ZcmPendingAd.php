<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZcmPendingAd extends Model
{
    use SoftDeletes, HasFactory;

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
        'synced_to_zcmanager_at',
    ];

    protected $casts = [
        'images' => 'array',
        'raw_payload' => 'array',
        'price' => 'decimal:2',
        'zcmanager_created_at' => 'datetime',
        'zcmanager_updated_at' => 'datetime',
        'synced_to_zcmanager_at' => 'datetime',
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
}
