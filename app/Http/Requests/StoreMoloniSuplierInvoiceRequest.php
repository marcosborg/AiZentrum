<?php

namespace App\Http\Requests;

use App\Models\MoloniSuplierInvoice;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreMoloniSuplierInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('moloni_suplier_invoice_create');
    }

    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
            ],
            'file' => [
                'required',
            ],
            'photo' => [
                'required',
            ],
        ];
    }
}
