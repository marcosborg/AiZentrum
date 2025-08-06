<?php

namespace App\Http\Requests;

use App\Models\TechnicalAssistanteMessage;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateTechnicalAssistanteMessageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('technical_assistante_message_edit');
    }

    public function rules()
    {
        return [];
    }
}
