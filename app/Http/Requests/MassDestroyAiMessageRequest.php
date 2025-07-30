<?php

namespace App\Http\Requests;

use App\Models\AiMessage;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyAiMessageRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('ai_message_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:ai_messages,id',
        ];
    }
}
