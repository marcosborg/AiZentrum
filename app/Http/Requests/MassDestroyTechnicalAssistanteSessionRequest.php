<?php

namespace App\Http\Requests;

use App\Models\TechnicalAssistanteSession;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyTechnicalAssistanteSessionRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('technical_assistante_session_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:technical_assistante_sessions,id',
        ];
    }
}
