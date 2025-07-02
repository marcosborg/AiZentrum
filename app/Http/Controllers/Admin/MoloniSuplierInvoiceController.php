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
use App\Services\MoloniService;

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
        return [
            'invoice_date' => $raw['invoice_date'] ?? now()->toDateString(),
            'invoice_number' => $raw['invoice_number'] ?? 'SEM-NUMERO',

            'supplier' => [
                'name' => $raw['supplier']['name']
                    ?? ($raw['supplier'] ?? 'DESCONHECIDO'),
                'NIF' => $raw['supplier']['NIF']
                    ?? ($raw['supplier']['nif'] ?? ''),
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

    public function launchToMoloni(MoloniSuplierInvoice $moloniSuplierInvoice)
    {
        $json = json_decode($moloniSuplierInvoice->data, true);

        if (!$json) {
            return back()->withErrors('O JSON da fatura está vazio ou inválido.');
        }

        $moloni = new MoloniService();

        // 1) Procurar fornecedor na Moloni
        $supplierNif = $json['supplier']['NIF'] ?? null;
        $supplierName = is_array($json['supplier']) ? ($json['supplier']['name'] ?? null) : $json['supplier'];

        $supplier = $moloni->findSupplier($supplierNif, $supplierName);

        if (!$supplier) {
            return back()->withErrors('Fornecedor não encontrado na Moloni: ' . ($supplierNif ?: $supplierName));
        }


        // 2) Montar os items
        $items = [];
        foreach ($json['items'] as $item) {
            $product = $moloni->findProductByReference($item['reference']);
            if (!$product) {
                return back()->withErrors("Artigo não existe na Moloni: {$item['reference']}");
            }

            // Opcional: atualizar stock
            // $moloni->updateProductStock($product['product_id'], nova quantidade);

            $items[] = [
                'product_id' => $product['product_id'],
                'name' => $item['description'],
                'qty' => $this->parseEuroNumber($item['quantity']),
                'price' => $this->parseEuroNumber($item['unit_price']),
                'discount' => $this->parseEuroNumber($item['discount']),
                'exemption_reason' => '', // se precisares
            ];
        }

        // 3) Montar payload do purchase
        $payload = [
            'company_id' => config('services.moloni.company_id'),
            'date' => $json['invoice_date'],
            'expiration_date' => $json['invoice_date'],
            'document_set_id' => config('services.moloni.document_set_id'),
            'entity_id' => $supplier['entity_id'],
            'our_reference' => $json['invoice_number'],
            'items' => $items,
            'notes' => 'Fatura inserida automaticamente a partir de análise de imagem.',
        ];

        // 4) Criar fatura na Moloni
        try {
            $response = $moloni->createPurchase($payload);
        } catch (\Exception $e) {
            return back()->withErrors('Erro ao lançar fatura na Moloni: ' . $e->getMessage());
        }

        // 5) Atualizar registo com resposta da Moloni
        $moloniSuplierInvoice->update([
            'handled' => true,
            'moloni_response' => json_encode($response),
        ]);

        return redirect()->route('admin.moloni-suplier-invoices.index')
            ->with('success', 'Fatura lançada com sucesso na Moloni!');
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
                            'text' => 'Analisa esta imagem de fatura e responde apenas com JSON puro (sem texto explicativo) nos campos: invoice_date, invoice_number, supplier (nome e NIF), items, totals, taxes (opcional) e payment (opcional). NÃO escrevas mais nada além do JSON.'
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
