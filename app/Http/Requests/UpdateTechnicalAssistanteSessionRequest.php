<?php

namespace App\Http\Requests;

use App\Models\TechnicalAssistanteSession;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateTechnicalAssistanteSessionRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('technical_assistante_session_edit');
    }

    public function rules()
    {
        return [
            'client' => [
                'nullable',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'client_name' => [
                'string',
                'nullable',
            ],
            'nif' => [
                'string',
                'nullable',
            ],
            'email' => [
                'string',
                'nullable',
            ],
            'invoice_number' => [
                'string',
                'nullable',
            ],
            'product' => [
                'string',
                'nullable',
            ],
            'car' => [
                'string',
                'nullable',
            ],
            'comercial' => [
                'string',
                'nullable',
            ],
        ];
    }
}
