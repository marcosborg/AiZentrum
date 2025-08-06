<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicalAssistanteMessage extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'technical_assistante_messages';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public const ROLE_RADIO = [
        'user'      => 'User',
        'assistant' => 'Assistant',
        'system' => 'Sistema'
    ];

    protected $fillable = [
        'technical_assistante_session_id',
        'role',
        'content',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function technical_assistante_session()
    {
        return $this->belongsTo(TechnicalAssistanteSession::class, 'technical_assistante_session_id');
    }
}
