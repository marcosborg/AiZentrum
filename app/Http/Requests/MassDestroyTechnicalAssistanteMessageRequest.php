<?php

namespace App\Http\Requests;

use App\Models\TechnicalAssistanteMessage;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyTechnicalAssistanteMessageRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('technical_assistante_message_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:technical_assistante_messages,id',
        ];
    }
}
