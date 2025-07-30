<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiMessage extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'ai_messages';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public const URGENCY_RADIO = [
        'Baixa'   => 'Baixa',
        'Média'   => 'Média',
        'Alta'    => 'Alta',
        'Crítica' => 'Crítica',
    ];

    protected $fillable = [
        'client',
        'parent_id',
        'email',
        'nif',
        'user_id',
        'context',
        'ai_response',
        'conflict_type',
        'urgency',
        'resolved',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public const CONFLICT_TYPE_RADIO = [
        'Problema de faturação'        => 'Problema de faturação',
        'Pedido de reembolso'          => 'Pedido de reembolso',
        'Problema com produto'         => 'Problema com produto',
        'Problema de entrega'          => 'Problema de entrega',
        'Reclamação sobre atendimento' => 'Reclamação sobre atendimento',
        'Reclamação genérica'          => 'Reclamação genérica',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
