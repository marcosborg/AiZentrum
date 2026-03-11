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
                        <div class="col-md-3">
                            
                            <div class="form-group">
                                <label for="invoice_date" class="required">Data da fatura</label>
                                @php
                                    try {
                                        $formattedDate = \Carbon\Carbon::createFromFormat('d/m/Y', $data->invoice_date)->format('Y-m-d');
                                    } catch (\Exception $e) {
                                        try {
                                            $formattedDate = \Carbon\Carbon::parse($data->invoice_date)->format('Y-m-d');
                                        } catch (\Exception $e) {
                                            $formattedDate = '';
                                        }
                                    }
                                @endphp

                                <input class="form-control" type="date" name="invoice_date" id="invoice_date"
                                    value="{{ $formattedDate }}"
                                    required>

                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="invoice_number" class="required">Número da fatura</label>
                                <input class="form-control" type="text" name="invoice_number" id="invoice_number"
                                    value="{{ $data->invoice_number ?? '' }}" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="supplier_id" class="required">Fornecedor</label>
                                <select name="supplier_id" id="supplier_id" class="form-control select2" required>
                                    @if (isset($supplier))
                                        <option value="{{ $supplier['supplier_id'] }}" selected>{{ $supplier['name'] }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="d-block">&nbsp;</label>
                                <button type="button" class="btn btn-outline-primary btn-block" data-toggle="modal"
                                    data-target="#createSupplierModal">Criar empresa</button>
                            </div>
                        </div>
                    </div>
                    <h5>Itens da Fatura</h5>
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Referência</th>
                                <th>Descrição</th>
                                <th>Qtd</th>
                                <th>Preço Unitário</th>
                                <th>IVA (%)</th>
                                <th>Total</th>
                                <th>Unidade</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data->items ?? [] as $index => $item)
                                <tr>
                                    <td><input type="text" name="items[{{ $index }}][reference]"
                                            class="form-control" value="{{ $item->reference ?? '' }}"></td>
                                    <td><input type="text" name="items[{{ $index }}][description] ?? ''"
                                            class="form-control" value="{{ $item->description }} ?? ''"></td>
                                    <td><input type="number" name="items[{{ $index }}][quantity] ?? ''"
                                            class="form-control" value="{{ $item->quantity }} ?? ''" step="0.01"></td>
                                    <td><input type="number" name="items[{{ $index }}][unit_price] ?? ''"
                                            class="form-control" value="{{ $item->unit_price }} ?? ''" step="0.01"></td>
                                    <td><input type="number" name="items[{{ $index }}][vat] ?? ''" class="form-control"
                                            value="{{ $item->vat ?? '' }}" step="0.01" ?? ''></td>
                                    <td><input type="number" name="items[{{ $index }}][total] ?? ''" class="form-control"
                                            value="{{ $item->total }}" step="0.01"></td>
                                    <td>
                                        <select name="items[{{ $index }}][unit_id]" class="form-control">
                                            <option value="86267" selected>Unidade (Uni.)</option>
                                            <option value="86272">Horas (Hrs)</option>
                                            <option value="86271">Litro (Lts.)</option>
                                            <option value="86270">Metro Cúbico (m3)</option>
                                            <option value="86268">Metro Linear (m)</option>
                                            <option value="86269">Metro Quadrado (m2)</option>
                                            <option value="89429">Quilograma (Kg)</option>
                                            <option value="89466">Tonelada (Ton)</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">🗑</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-success btn-sm my-2" id="addRow">Adicionar item</button><br>
                    <button type="button" id="syncMoloniBtn" class="btn btn-primary mt-3">
                        Sincronizar com Moloni
                    </button>
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
                    @if ($moloni_suplier_invoice->photos->count())
                        <img src="{{ $moloni_suplier_invoice->photos->first()->getUrl() }}" class="img-thumbnail"
                            style="cursor:pointer;" data-toggle="modal" data-target="#imageModal">
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    JSON
                </div>
                <div class="card-body">
                    <pre>{{ json_encode(json_decode($moloni_suplier_invoice->data, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de visualização da imagem -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-0">
                    @foreach($moloni_suplier_invoice->photos as $photo)
                        <img src="{{ $photo->getUrl() }}" class="img-fluid w-100 mb-2" alt="Fatura">
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Criar Empresa -->
    <div class="modal fade" id="createSupplierModal" tabindex="-1" role="dialog"
        aria-labelledby="createSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form id="createSupplierForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Criar nova empresa</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="supplier_name">Nome da empresa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="supplier_name" name="name" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="supplier_vat">NIF</label>
                                <input type="text" class="form-control" id="supplier_vat" name="vat">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="supplier_address">Endereço</label>
                                <input type="text" class="form-control" id="supplier_address" name="address">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="supplier_zip_code">Código Postal</label>
                                <input type="text" class="form-control" id="supplier_zip_code" name="zip_code">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="supplier_city">Cidade</label>
                                <input type="text" class="form-control" id="supplier_city" name="city">
                            </div>
                            <div class="form-group col-md-6">
                                <div class="form-group">
                                    <label for="supplier_country_id">País <span class="text-danger">*</span></label>
                                    <select id="supplier_country_id" name="country_id" class="form-control" required>
                                        <option value="">-- Escolher país --</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country['country_id'] }}">{{ $country['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar fornecedor</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            let selectedSupplier = @json($supplier ?? null);

            $('#supplier_id').select2({
                placeholder: 'Procurar fornecedor...',
                ajax: {
                    url: '{{ route('admin.moloni.suppliers') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            term: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2
            });

            if (selectedSupplier) {
                let option = new Option(selectedSupplier.name, selectedSupplier.supplier_id, true, true);
                $('#supplier_id').append(option).trigger('change');
            }
        });
    </script>
    <script>
        let itemIndex = {{ count($data->items ?? []) }};

        $('#addRow').on('click', function() {
            const row = `
        <tr>
            <td><input type="text" name="items[${itemIndex}][reference]" class="form-control" /></td>
            <td><input type="text" name="items[${itemIndex}][description]" class="form-control" /></td>
            <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control" step="0.01" /></td>
            <td><input type="number" name="items[${itemIndex}][unit_price]" class="form-control" step="0.01" /></td>
            <td><input type="number" name="items[${itemIndex}][vat]" class="form-control" step="0.01" /></td>
            <td><input type="number" name="items[${itemIndex}][total]" class="form-control" step="0.01" /></td>
            <td>
                <select name="items[${itemIndex}][unit_id]" class="form-control">
                    <option value="86267" selected>Unidade (Uni.)</option>
                    <option value="86272">Horas (Hrs)</option>
                    <option value="86271">Litro (Lts.)</option>
                    <option value="86270">Metro Cúbico (m3)</option>
                    <option value="86268">Metro Linear (m)</option>
                    <option value="86269">Metro Quadrado (m2)</option>
                    <option value="89429">Quilograma (Kg)</option>
                    <option value="89466">Tonelada (Ton)</option>
                </select>
            </td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row">🗑</button></td>
        </tr>
    `;
            $('#itemsTable tbody').append(row);
            itemIndex++;
        });

        $(document).on('click', '.remove-row', function() {
            $(this).closest('tr').remove();
        });
    </script>
    <script>
        $('#createSupplierForm').on('submit', function(e) {
            e.preventDefault();

            const $form = $(this);
            const formData = $form.serialize();

            $.post('{{ route('admin.moloni.suppliers.create') }}', formData, function(response) {
                // Garante que recebemos os dados corretamente
                if (response.supplier_id && response.name) {
                    // Limpa todos os options existentes
                    $('#supplier_id').empty();

                    // Adiciona o novo fornecedor como único selecionado
                    const newOption = new Option(response.name, response.supplier_id, true, true);
                    $('#supplier_id').append(newOption).trigger('change');
                }

                // Fecha o modal e remove o fundo escuro
                $('#createSupplierModal').modal('hide');
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open'); // evita scroll bloqueado
                $form[0].reset();
            }).fail(function(xhr) {
                alert('Erro ao criar fornecedor: ' + (xhr.responseJSON?.error || xhr.responseText));
            });
        });
    </script>
    <script>
        $('#syncMoloniBtn').on('click', function() {
            const invoiceData = {
                invoice_date: $('#invoice_date').val(),
                invoice_number: $('#invoice_number').val(),
                supplier_id: $('#supplier_id').val(),
                items: []
            };

            $('#itemsTable tbody tr').each(function() {
                const row = $(this);
                invoiceData.items.push({
                    reference: row.find('input[name*="[reference]"]').val(),
                    description: row.find('input[name*="[description]"]').val(),
                    quantity: row.find('input[name*="[quantity]"]').val(),
                    unit_price: row.find('input[name*="[unit_price]"]').val(),
                    vat: row.find('input[name*="[vat]"]').val(),
                    total: row.find('input[name*="[total]"]').val()
                });
            });

            $.ajax({
                url: '{{ route('admin.moloni.syncInvoice') }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    moloni_suplier_invoice_id: '{{ $moloni_suplier_invoice->id }}',
                    data: invoiceData
                },
                success: function(response) {
                    alert('Dados enviados com sucesso!');
                    window.location.href = '/admin/moloni-suplier-invoices';
                },
                error: function(xhr) {
                    alert('Erro ao sincronizar: ' + (xhr.responseJSON?.message || xhr.statusText));
                }
            });

        });
    </script>
@endsection
@section('styles')
    <style>
        .select2-container--default .select2-selection--single {
            height: calc(1.5em + .75rem + 2px);
            /* igual a .form-control */
            padding: .375rem .75rem;
            font-size: 1rem;
            line-height: 1.5;
            border: 1px solid #ced4da;
            border-radius: .25rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + .75rem + 2px);
            right: 10px;
        }

        /* Corrigir a largura do container do select2 */
        .select2-container {
            width: 100% !important;
        }
    </style>
@endsection
