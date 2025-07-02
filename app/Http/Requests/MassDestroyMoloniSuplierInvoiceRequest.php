<?php

namespace App\Http\Requests;

use App\Models\MoloniSuplierInvoice;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyMoloniSuplierInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('moloni_suplier_invoice_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:moloni_suplier_invoices,id',
        ];
    }
}
