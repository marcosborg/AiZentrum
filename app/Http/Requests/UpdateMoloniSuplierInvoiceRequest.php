<?php

namespace App\Http\Requests;

use App\Models\MoloniSuplierInvoice;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateMoloniSuplierInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('moloni_suplier_invoice_edit');
    }

    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
            ],
            'photo' => [
                'nullable',
                'string',
            ],
            'photos' => [
                'required_without:photo',
                'array',
                'min:1',
            ],
            'photos.*' => [
                'string',
            ],
        ];
    }
}
