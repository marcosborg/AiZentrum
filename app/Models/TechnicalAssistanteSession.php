<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicalAssistanteSession extends Model
{
    use SoftDeletes, HasFactory;

    public $table = 'technical_assistante_sessions';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'client',
        'client_name',
        'nif',
        'email',
        'invoice_number',
        'product',
        'car',
        'comercial',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function messages()
    {
        return $this->hasMany(TechnicalAssistanteMessage::class, 'technical_assistante_session_id');
    }
}
