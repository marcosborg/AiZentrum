<?php

namespace App\Http\Requests;

use App\Models\AiAssistantCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreAiAssistantCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('ai_assistant_category_create');
    }

    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
            ],
            'name' => [
                'string',
                'required',
            ],
        ];
    }
}
