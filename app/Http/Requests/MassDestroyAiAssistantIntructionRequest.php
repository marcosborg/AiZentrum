<?php

namespace App\Http\Requests;

use App\Models\AiAssistantIntruction;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyAiAssistantIntructionRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('ai_assistant_intruction_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:ai_assistant_intructions,id',
        ];
    }
}
