@extends('layouts.admin')
@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    Dados da fatura para o Moloni
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="invoice_date" class="required">Data da fatura</label>
                                <input class="form-control" type="date" name="invoice_date" id="invoice_date" value="{{ $data->invoice_date ?? '' }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="invoice_number" class="required">Data da fatura</label>
                                <input class="form-control" type="text" name="invoice_number" id="invoice_number" value="{{ $data->invoice_number ?? '' }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="supplier_id" class="required">Fornecedor</label>
                                <select name="supplier_id" id="supplier_id" class="form-control select2" required>
                                    @if(isset($supplier))
                                        <option value="{{ $supplier['entity_id'] }}" selected>{{ $supplier['name'] }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    Original
                </div>
                <div class="card-body">
                    <!-- Trigger da imagem -->
                    @if($moloni_suplier_invoice->photo)
                        <img src="{{ $moloni_suplier_invoice->photo->getUrl() }}" class="img-thumbnail" style="cursor:pointer;" data-toggle="modal" data-target="#imageModal">
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de visualização da imagem -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
        <div class="modal-body p-0">
            <img src="{{ $moloni_suplier_invoice->photo->getUrl() }}" class="img-fluid w-100" alt="Fatura">
        </div>
        </div>
    </div>
    </div>
@endsection
@section('scripts')
<script>
    $(document).ready(function () {
        $('#supplier_id').select2({
            placeholder: 'Procurar fornecedor...',
            ajax: {
                url: '{{ route('admin.moloni.suppliers') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { term: params.term };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true
            },
            minimumInputLength: 2
        });
    });
</script>
@endsection
<script>
    console.log({
        moloni_suplier_invoice: {!! $moloni_suplier_invoice !!},
        data: {!! json_encode($data) !!}
    })
</script>