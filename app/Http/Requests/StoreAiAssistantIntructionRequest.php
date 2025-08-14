<?php

namespace App\Http\Requests;

use App\Models\AiAssistantIntruction;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreAiAssistantIntructionRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('ai_assistant_intruction_create');
    }

    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
            ],
            'ai_assistant_category_id' => [
                'required',
                'integer',
            ],
            'name' => [
                'string',
                'required',
            ],
            'instructions' => [
                'required',
            ],
        ];
    }
}
