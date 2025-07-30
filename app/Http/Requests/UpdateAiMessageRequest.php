<?php

namespace App\Http\Requests;

use App\Models\AiMessage;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateAiMessageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('ai_message_edit');
    }

    public function rules()
    {
        return [
            'client' => [
                'required',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'email' => [
                'string',
                'nullable',
            ],
            'nif' => [
                'string',
                'nullable',
            ],
            'user_id' => [
                'required',
                'integer',
            ],
            'conflict_type' => [
                'required',
            ],
            'urgency' => [
                'required',
            ],
        ];
    }
}
