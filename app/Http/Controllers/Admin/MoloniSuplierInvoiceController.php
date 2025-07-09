<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyMoloniSuplierInvoiceRequest;
use App\Http\Requests\StoreMoloniSuplierInvoiceRequest;
use App\Http\Requests\UpdateMoloniSuplierInvoiceRequest;
use App\Models\MoloniSuplierInvoice;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;
use OpenAI\Laravel\Facades\OpenAI;

class MoloniSuplierInvoiceController extends Controller
{
    use MediaUploadingTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = MoloniSuplierInvoice::with(['user'])->select(sprintf('%s.*', (new MoloniSuplierInvoice)->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate      = 'moloni_suplier_invoice_show';
                $editGate      = 'moloni_suplier_invoice_edit';
                $deleteGate    = 'moloni_suplier_invoice_delete';
                $crudRoutePart = 'moloni-suplier-invoices';

                return view('partials.datatablesActions', compact(
                    'viewGate',
                    'editGate',
                    'deleteGate',
                    'crudRoutePart',
                    'row'
                ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->addColumn('user_name', function ($row) {
                return $row->user ? $row->user->name : '';
            });

            $table->editColumn('category', function ($row) {
                return $row->category ? MoloniSuplierInvoice::CATEGORY_RADIO[$row->category] : '';
            });
            $table->editColumn('photo', function ($row) {
                if ($photo = $row->photo) {
                    return sprintf(
                        '<a href="%s" target="_blank"><img src="%s" width="50px" height="50px"></a>',
                        $photo->url,
                        $photo->thumbnail
                    );
                }

                return '';
            });
            $table->editColumn('data', function ($row) {
                return $row->data ? $row->data : '';
            });
            $table->editColumn('handled', function ($row) {
                return '<input type="checkbox" disabled ' . ($row->handled ? 'checked' : null) . '>';
            });

            $table->rawColumns(['actions', 'placeholder', 'user', 'photo', 'handled']);

            return $table->make(true);
        }

        return view('admin.moloniSuplierInvoices.index');
    }

    public function create()
    {
        abort_if(Gate::denies('moloni_suplier_invoice_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.moloniSuplierInvoices.create', compact('users'));
    }

    public function store(StoreMoloniSuplierInvoiceRequest $request)
    {
        $moloniSuplierInvoice = MoloniSuplierInvoice::create($request->all());

        if ($request->input('photo', false)) {
            $moloniSuplierInvoice->addMedia(storage_path('tmp/uploads/' . basename($request->input('photo'))))->toMediaCollection('photo');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $moloniSuplierInvoice->id]);
        }

        // Obter a imagem da fatura
        $photo = $moloniSuplierInvoice->photo;
        if ($photo) {
            // Gerar URL pública
            $imageUrl = $photo->getUrl();

            // Analisar a imagem com GPT-4o visão
            $json = $this->analyzeInvoiceImage($imageUrl);

            // Guardar o JSON no campo data
            $moloniSuplierInvoice->update(['data' => json_encode($json)]);
        }

        return redirect()->route('admin.moloni-suplier-invoices.edit', [$moloniSuplierInvoice->id]);
    }

    public function edit(MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $moloniSuplierInvoice->load('user');

        return view('admin.moloniSuplierInvoices.edit', compact('moloniSuplierInvoice', 'users'));
    }

    public function update(UpdateMoloniSuplierInvoiceRequest $request, MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        $moloniSuplierInvoice->update($request->all());

        if ($request->input('photo', false)) {
            if (! $moloniSuplierInvoice->photo || $request->input('photo') !== $moloniSuplierInvoice->photo->file_name) {
                if ($moloniSuplierInvoice->photo) {
                    $moloniSuplierInvoice->photo->delete();
                }
                $moloniSuplierInvoice->addMedia(storage_path('tmp/uploads/' . basename($request->input('photo'))))->toMediaCollection('photo');
            }
        } elseif ($moloniSuplierInvoice->photo && !$request->input('photo')) {
            $moloniSuplierInvoice->photo->delete();
        }

        if ($moloniSuplierInvoice->photo) {
            $imageUrl = $moloniSuplierInvoice->photo->getUrl();
            $json = $this->analyzeInvoiceImage($imageUrl);
            $normalizedJson = $this->normalizeInvoiceJson($json);
            $moloniSuplierInvoice->update(['data' => json_encode($normalizedJson)]);
        }

        return redirect()->route('admin.moloni-suplier-invoices.edit', [$moloniSuplierInvoice->id]);
    }


    public function show(MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $moloniSuplierInvoice->load('user');

        return view('admin.moloniSuplierInvoices.show', compact('moloniSuplierInvoice'));
    }

    public function destroy(MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $moloniSuplierInvoice->delete();

        return back();
    }

    public function massDestroy(MassDestroyMoloniSuplierInvoiceRequest $request)
    {
        $moloniSuplierInvoices = MoloniSuplierInvoice::find(request('ids'));

        foreach ($moloniSuplierInvoices as $moloniSuplierInvoice) {
            $moloniSuplierInvoice->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('moloni_suplier_invoice_create') && Gate::denies('moloni_suplier_invoice_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new MoloniSuplierInvoice();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }

    private function normalizeInvoiceJson(array $raw): array
    {
        // Inicializa valores default
        $supplierName = 'DESCONHECIDO';
        $supplierNif = '';

        if (isset($raw['supplier'])) {
            $supplier = $raw['supplier'];

            // supplier como string
            if (is_string($supplier)) {
                $supplierName = $supplier;
            }
            // supplier como array
            elseif (is_array($supplier)) {
                // se supplier.name existe
                if (isset($supplier['name'])) {
                    // supplier.name como string
                    if (is_string($supplier['name'])) {
                        $supplierName = $supplier['name'];
                    }
                    // supplier.name como array (caso específico do teu dump)
                    elseif (is_array($supplier['name'])) {
                        $supplierName = $supplier['name']['name'] ?? $supplierName;
                        $supplierNif = $supplier['name']['NIF'] ?? $supplierNif;
                    }
                }

                // supplier.NIF/Nif (fora do name)
                if (isset($supplier['NIF'])) {
                    $supplierNif = $supplier['NIF'];
                }
                if (isset($supplier['nif'])) {
                    $supplierNif = $supplier['nif'];
                }
            }
        }

        return [
            'invoice_date' => $raw['invoice_date'] ?? now()->toDateString(),
            'invoice_number' => $raw['invoice_number'] ?? 'SEM-NUMERO',

            'supplier' => [
                'name' => $supplierName,
                'NIF' => $supplierNif,
            ],

            'buyer' => [
                'name' => 'AIRBAGS-ZENTRUM, LDA',
                'NIF' => '508263069',
            ],

            'items' => collect($raw['items'] ?? [])->map(function ($item) {
                return [
                    'description' => $item['description'] ?? '',
                    'brand' => $item['brand'] ?? '',
                    'reference' => $item['reference'] ?? '',
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'vat' => (float) ($item['vat'] ?? 23),
                    'total' => (float) ($item['total'] ?? 0),
                ];
            })->toArray(),

            'totals' => [
                'subtotal' => (float) ($raw['totals']['subtotal'] ?? 0),
                'tax' => (float) ($raw['totals']['tax'] ?? 0),
                'total' => (float) ($raw['totals']['total'] ?? 0),
            ],

            'payment' => [
                'terms' => $raw['payment']['terms'] ?? '30 dias',
            ],
        ];
    }

    function parseEuroNumber(string $number): float
    {
        // Remove espaços, troca vírgula por ponto, e converte para float
        return (float) str_replace(',', '.', str_replace(' ', '', $number));
    }

    public function launchToMoloni($moloni_suplier_invoice_id)
    {
        
        

    }

    private function analyzeInvoiceImage(string $imageUrl): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Analisa esta imagem de fatura de fornecedor e responde exclusivamente com um objeto JSON (sem qualquer texto explicativo). O JSON deve conter os seguintes campos: invoice_date, invoice_number, supplier (com name e nif), buyer (com name e nif), items, totals, taxes (opcional) e payment (opcional). O fornecedor está geralmente à esquerda e o comprador à direita — mas tem atenção: em muitos casos o NIF à esquerda pertence ao comprador (neste caso, 508263069). Por isso, se encontrares este NIF (508263069), considera que é o comprador (buyer). Se não conseguires identificar com clareza o NIF do fornecedor, define o campo supplier.nif como 999999990. Extrai corretamente os produtos, com os campos: reference, description, brand, quantity, unit_price, vat e total. Nunca preenchas campos com valores vazios ou a zero, a não ser que essa informação não exista. Se não conseguires extrair um item completo, omite-o. O campo totals deve conter subtotal, tax, discount (se aplicável) e total, com os valores reais da fatura. Nunca preenchas estes campos com zeros se os valores puderem ser calculados ou extraídos. Tem especial atenção à leitura correta de dígitos em campos numéricos como o NIF — evita confundir o número 0 com o 6 ou outros. Se o NIF tiver 9 dígitos e não for válido, revê a imagem com mais cuidado antes de devolver. Não escrevas absolutamente mais nada além do JSON.'
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => $imageUrl]
                        ]
                    ],
                ],
            ],
        ]);


        $content = $response->choices[0]->message->content ?? '{}';

        \Log::debug('GPT response content: ' . $content);

        // Tenta extrair o JSON com regex
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('Falha ao fazer json_decode.', [
                'error' => json_last_error_msg(),
                'content' => $content,
            ]);

            throw new \RuntimeException('A resposta do GPT não é um JSON válido.');
        }

        return $json;
    }
}
