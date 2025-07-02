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
        } elseif ($moloniSuplierInvoice->photo) {
            $moloniSuplierInvoice->photo->delete();
        }

        $photoUpdated = false;

        if ($request->input('photo', false)) {
            if (! $moloniSuplierInvoice->photo || $request->input('photo') !== $moloniSuplierInvoice->photo->file_name) {
                if ($moloniSuplierInvoice->photo) {
                    $moloniSuplierInvoice->photo->delete();
                }
                $moloniSuplierInvoice->addMedia(storage_path('tmp/uploads/' . basename($request->input('photo'))))->toMediaCollection('photo');
                $photoUpdated = true;
            }
        } elseif ($moloniSuplierInvoice->photo && !$request->input('photo')) {
            $moloniSuplierInvoice->photo->delete();
            $photoUpdated = true;
        }

        // Se a imagem foi alterada, ou se quiseres sempre reprocessar, chama analyze
        if ($moloniSuplierInvoice->photo && ($photoUpdated || true)) {
            $imageUrl = $moloniSuplierInvoice->photo->getUrl();
            $json = $this->analyzeInvoiceImage($imageUrl);
            $moloniSuplierInvoice->update(['data' => json_encode($json)]);
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

    private function analyzeInvoiceImage(string $imageUrl): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'És um assistente que analisa imagens de faturas de fornecedores e devolve sempre o mesmo JSON estruturado com fornecedor, comprador, itens, totais e detalhes de pagamento.'
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Por favor, analisa esta imagem de fatura e devolve exclusivamente um JSON estruturado com os campos: invoice_date, invoice_number, supplier, buyer, items, totals, taxes (opcional) e payment (opcional). Sem texto adicional.'
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl,
                            ]
                        ]
                    ],
                ],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';

        \Log::debug('GPT response content: ' . $content);

        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('Falha ao fazer json_decode do conteúdo retornado pelo GPT.', [
                'error' => json_last_error_msg(),
                'content' => $content,
            ]);

            // Opcionalmente podes lançar exceção para parar o fluxo
            throw new \RuntimeException('A resposta do GPT não é um JSON válido.');
        }

        return $json;
    }
}
