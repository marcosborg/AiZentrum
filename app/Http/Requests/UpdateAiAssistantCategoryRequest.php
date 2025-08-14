<?php

namespace App\Http\Requests;

use App\Models\AiAssistantCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateAiAssistantCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('ai_assistant_category_edit');
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
