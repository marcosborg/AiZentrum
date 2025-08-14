<?php

namespace App\Http\Requests;

use App\Models\AiAssistantCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyAiAssistantCategoryRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('ai_assistant_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:ai_assistant_categories,id',
        ];
    }
}
