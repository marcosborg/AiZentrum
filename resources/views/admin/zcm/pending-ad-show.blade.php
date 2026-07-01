@extends('layouts.admin')

@section('content')
<div class="content">
  <div id="zcm-image-ajax-alert" class="alert d-none" role="alert"></div>

  @if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <div class="mb-3">
    <a href="{{ route('admin.zcm.pending-ads.index') }}" class="btn btn-sm btn-outline-secondary">Voltar</a>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>An&uacute;ncio {{ $pendingAd->reference ?: $pendingAd->id }}</strong>
      <div>
        <span class="badge badge-light">ZCM: {{ $pendingAd->status }}</span>
        <span class="badge badge-{{ $pendingAd->sync_status === 'sent' ? 'success' : 'secondary' }}">Sync: {{ $pendingAd->sync_status }}</span>
        <span class="badge badge-info">Pipeline: {{ $pendingAd->pipeline_status_label }}</span>
        <span class="badge badge-secondary">Review: {{ $pendingAd->review_status_label }}</span>
      </div>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-sm table-bordered">
            <tr><th>ID ZCM</th><td>{{ $pendingAd->zcmanager_ad_id }}</td></tr>
            <tr><th>Reference</th><td>{{ $pendingAd->reference }}</td></tr>
            <tr><th>Title</th><td>{{ $pendingAd->title }}</td></tr>
            <tr><th>Price</th><td>{{ $pendingAd->price }}</td></tr>
            <tr><th>Category</th><td>{{ $pendingAd->category }}</td></tr>
            <tr><th>Requested by</th><td>{{ data_get($pendingAd->requested_by_data, 'name') ?: $pendingAd->requested_by }}</td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <table class="table table-sm table-bordered">
            <tr><th>Brand reference</th><td>{{ data_get($pendingAd->brand_model_data, 'brand_reference') }}</td></tr>
            <tr><th>Manufacturer</th><td>{{ data_get($pendingAd->brand_model_data, 'manufacturer') }}</td></tr>
            <tr><th>Manufacturer reference</th><td>{{ data_get($pendingAd->brand_model_data, 'manufacturer_reference') }}</td></tr>
            <tr><th>Car model</th><td>{{ data_get($pendingAd->brand_model_data, 'car_model') }}</td></tr>
            <tr><th>Criado ZCM</th><td>{{ optional($pendingAd->zcmanager_created_at)->format('Y-m-d H:i') }}</td></tr>
            <tr><th>Atualizado ZCM</th><td>{{ optional($pendingAd->zcmanager_updated_at)->format('Y-m-d H:i') }}</td></tr>
          </table>
        </div>
      </div>

      <div class="form-group">
        <label>Description</label>
        <div class="border rounded p-2 bg-light">{{ $pendingAd->description ?: '-' }}</div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><strong>Pipeline IA</strong></div>
    <div class="card-body">
      <div class="btn-group mb-3" role="group">
        @foreach(['research' => 'Pesquisa interna', 'analysis' => 'An&aacute;lise IA', 'images' => 'Imagens', 'seo' => 'SEO', 'publishing' => 'Preparar exporta&ccedil;&atilde;o'] as $stage => $label)
          <form method="post" action="{{ route('admin.zcm.pending-ads.run-stage', $pendingAd) }}" class="mr-2">
            @csrf
            <input type="hidden" name="stage" value="{{ $stage }}">
            <button class="btn btn-sm btn-outline-primary">{!! $label !!}</button>
          </form>
        @endforeach
      </div>

      @php
        $researchBestMatch = data_get($pendingAd->enrichment?->research, 'prestashop.best_match');
        $webResearch = $pendingAd->enrichment?->research['web'] ?? [];
        $analysis = $pendingAd->enrichment?->ai_analysis ?? [];
        $evidenceSources = data_get($analysis, 'evidence_sources', []);
        $finalImages = data_get($pendingAd->enrichment?->images, 'final_images', []);
        $candidateImages = data_get($pendingAd->enrichment?->images, 'candidate_images', []);
        $displayImages = !empty($finalImages) ? $finalImages : $candidateImages;
        $pricingSummary = data_get($pendingAd->enrichment?->research, 'pricing_summary');
        $imageExtractionNote = data_get($pendingAd->enrichment?->images, 'extraction_note');
        $prestashopDraft = data_get($pendingAd->enrichment?->technical_data, 'prestashop_draft', []);
        $prestashopLanguages = data_get($prestashopDraft, 'prestashop_languages', []);
        $prestashopCategories = data_get($prestashopDraft, 'prestashop_categories', []);
        $prestashopTranslations = data_get($prestashopDraft, 'translations', []);
      @endphp

      @if($researchBestMatch || $analysis)
        <div class="row mb-3">
          <div class="col-md-6">
            <div class="pipeline-summary">
              <strong>Melhor resultado interno</strong>
              <div>{{ data_get($researchBestMatch, 'name', 'Ainda sem resultado relevante.') }}</div>
              @if($researchBestMatch)
                <small>Score {{ data_get($researchBestMatch, 'match_score') }} - {{ data_get($researchBestMatch, 'match_quality') }}</small>
              @endif
              @if(data_get($webResearch, 'found'))
                <div><small>Web: {{ count(data_get($webResearch, 'items', [])) }} resultado(s), {{ count(data_get($webResearch, 'sources', [])) }} fonte(s)</small></div>
              @endif
            </div>
          </div>
          <div class="col-md-6">
            <div class="pipeline-summary">
              <strong>Resumo IA</strong>
              <div>{{ data_get($analysis, 'part_type', '-') }} / {{ data_get($analysis, 'brand', '-') }} / {{ data_get($analysis, 'model', '-') }}</div>
              @if(data_get($analysis, 'confidence_score') !== null)
                <small>Confian&ccedil;a {{ data_get($analysis, 'confidence_score') }}%</small>
              @endif
            </div>
          </div>
        </div>
      @endif

      @if(!empty($evidenceSources))
        <div class="mb-3">
          <h6>Fontes usadas pela IA</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered pipeline-sources">
              <thead>
                <tr>
                  <th>Origem</th>
                  <th>T&iacute;tulo</th>
                  <th>Refer&ecirc;ncia</th>
                  <th>Score</th>
                  <th>Fonte</th>
                </tr>
              </thead>
              <tbody>
                @foreach($evidenceSources as $source)
                  <tr>
                    <td>
                      @if(data_get($source, 'origin') === 'internal_prestashop')
                        <span class="badge badge-secondary">Prestashop interno</span>
                      @elseif(data_get($source, 'origin') === 'web_openai')
                        <span class="badge badge-primary">Web / OpenAI</span>
                      @else
                        <span class="badge badge-light">{{ data_get($source, 'origin', '-') }}</span>
                      @endif
                    </td>
                    <td>
                      <div>{{ data_get($source, 'title', '-') }}</div>
                      @if(data_get($source, 'match_reason'))
                        <small>{{ data_get($source, 'match_reason') }}</small>
                      @endif
                    </td>
                    <td>{{ data_get($source, 'reference', '-') }}</td>
                    <td>{{ data_get($source, 'confidence_score', '-') }}</td>
                    <td>
                      @if(data_get($source, 'url'))
                        <a href="{{ data_get($source, 'url') }}" target="_blank" rel="noopener noreferrer">{{ parse_url(data_get($source, 'url'), PHP_URL_HOST) ?: data_get($source, 'url') }}</a>
                      @else
                        <span class="text-muted">{{ data_get($source, 'origin') === 'internal_prestashop' ? 'Prestashop interno' : 'Sem URL' }}</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif

      @if(data_get($pricingSummary, 'has_prices'))
        <div class="mb-3">
          <h6>Pre&ccedil;os para aprova&ccedil;&atilde;o</h6>
          <div class="pipeline-price-note alert {{ data_get($pricingSummary, 'is_market_range') ? 'alert-info' : 'alert-warning' }}">
            {{ data_get($pricingSummary, 'note') }}
            <strong>{{ data_get($pricingSummary, 'count') }} fonte(s) com pre&ccedil;o.</strong>
            <strong>{{ data_get($pricingSummary, 'confirmed_count', 0) }} confirmada(s).</strong>
            @if(data_get($pricingSummary, 'unverified_count'))
              <strong>{{ data_get($pricingSummary, 'unverified_count') }} indicativa(s).</strong>
            @endif
          </div>
          <div class="pipeline-price-grid">
            <div class="pipeline-price-card">
              <small>M&iacute;nimo observado</small>
              <strong>{{ number_format(data_get($pricingSummary, 'min'), 2, ',', '.') }} {{ data_get($pricingSummary, 'currency') }}</strong>
            </div>
            <div class="pipeline-price-card">
              <small>M&eacute;dio observado</small>
              <strong>{{ number_format(data_get($pricingSummary, 'average'), 2, ',', '.') }} {{ data_get($pricingSummary, 'currency') }}</strong>
            </div>
            <div class="pipeline-price-card">
              <small>M&aacute;ximo observado</small>
              <strong>{{ number_format(data_get($pricingSummary, 'max'), 2, ',', '.') }} {{ data_get($pricingSummary, 'currency') }}</strong>
            </div>
          </div>
          <div class="table-responsive mt-2">
            <table class="table table-sm table-bordered pipeline-sources">
              <thead>
                <tr>
                  <th>Origem</th>
                  <th>Proveni&ecirc;ncia</th>
                  <th>Fonte</th>
                  <th>Refer&ecirc;ncia</th>
                  <th>Pre&ccedil;o</th>
                  <th>Confirma&ccedil;&atilde;o</th>
                  <th>Score</th>
                </tr>
              </thead>
              <tbody>
                @foreach(data_get($pricingSummary, 'sources', []) as $source)
                  <tr>
                    <td>
                      @if(data_get($source, 'origin') === 'internal_prestashop')
                        <span class="badge badge-secondary">Prestashop interno</span>
                      @elseif(data_get($source, 'origin') === 'web_page')
                        <span class="badge badge-info">P&aacute;gina externa</span>
                      @else
                        <span class="badge badge-primary">Web / OpenAI</span>
                      @endif
                    </td>
                    <td>
                      <strong>{{ data_get($source, 'provenance', '-') }}</strong>
                      @if(data_get($source, 'price_source'))
                        <small>{{ data_get($source, 'price_source') }}</small>
                      @endif
                      @if(data_get($source, 'match_reason'))
                        <small>{{ data_get($source, 'match_reason') }}</small>
                      @endif
                    </td>
                    <td>
                      @if(data_get($source, 'url'))
                        <a href="{{ data_get($source, 'url') }}" target="_blank" rel="noopener noreferrer">{{ data_get($source, 'title', 'Abrir fonte') }}</a>
                      @else
                        {{ data_get($source, 'title', '-') }}
                      @endif
                    </td>
                    <td>{{ data_get($source, 'reference', '-') }}</td>
                    <td>{{ number_format(data_get($source, 'price'), 2, ',', '.') }} {{ data_get($source, 'currency') }}</td>
                    <td>
                      @if(data_get($source, 'is_confirmed'))
                        <span class="badge badge-success">Confirmado</span>
                      @else
                        <span class="badge badge-warning">Indicativo</span>
                      @endif
                    </td>
                    <td>{{ data_get($source, 'confidence_score', '-') }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif

      @if(!empty($finalImages) || !empty($candidateImages))
        <div class="mb-3">
          <h6>Imagens obtidas</h6>
          <div class="pipeline-image-grid" id="zcm-image-grid">
            @foreach($displayImages as $image)
              @php
                $imageUrl = data_get($image, 'url');
                if (data_get($image, 'source') === 'ai_generated' && data_get($image, 'storage_path')) {
                  $imageUrl = route('admin.zcm.pending-ads.generated-image', [$pendingAd, basename(data_get($image, 'storage_path'))]);
                }
              @endphp
              <div class="pipeline-image-item">
                <a href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer">
                  <img src="{{ $imageUrl }}" alt="{{ data_get($image, 'page_title', 'Imagem do anuncio') }}">
                </a>
                <div class="pipeline-image-meta">
                  <span class="badge badge-{{ data_get($image, 'source') === 'ai_generated' ? 'success' : (data_get($image, 'source') === 'zcmanager' ? 'secondary' : 'primary') }}">
                    @if(data_get($image, 'source') === 'ai_generated')
                      IA gerada
                    @elseif(data_get($image, 'source') === 'zcmanager')
                      ZCManager
                    @else
                      Web / OpenAI
                    @endif
                  </span>
                  @if(data_get($image, 'confidence_score') !== null)
                    <span>Score {{ data_get($image, 'confidence_score') }}</span>
                  @endif
                </div>
                @if(data_get($image, 'original_url'))
                  <a class="pipeline-image-source" href="{{ data_get($image, 'original_url') }}" target="_blank" rel="noopener noreferrer">Original</a>
                @endif
                @if(data_get($image, 'page_url'))
                  <a class="pipeline-image-source" href="{{ data_get($image, 'page_url') }}" target="_blank" rel="noopener noreferrer">Fonte</a>
                @endif
                @if(data_get($image, 'source') !== 'ai_generated' && data_get($image, 'url'))
                  <form method="post" action="{{ route('admin.zcm.pending-ads.recreate-image', $pendingAd) }}" class="mt-2 js-ai-image-form">
                    @csrf
                    <input type="hidden" name="image_url" value="{{ data_get($image, 'url') }}">
                    <button class="btn btn-sm btn-outline-success btn-block" type="submit">Recriar com IA</button>
                  </form>
                @elseif(data_get($image, 'source') === 'ai_generated')
                  <form method="post" action="{{ route('admin.zcm.pending-ads.delete-generated-image', $pendingAd) }}" class="mt-2 js-delete-ai-image-form">
                    @csrf
                    <input type="hidden" name="image_url" value="{{ data_get($image, 'url') }}">
                    <button class="btn btn-sm btn-outline-danger btn-block" type="submit">Eliminar</button>
                  </form>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      @elseif($imageExtractionNote)
        <div class="mb-3">
          <h6>Imagens obtidas</h6>
          <div class="alert alert-warning pipeline-price-note">{{ $imageExtractionNote }}</div>
        </div>
      @endif

      <div class="row">
        <div class="col-md-6 mb-3">
          <h6>Pesquisa</h6>
          <pre class="pipeline-json">{{ json_encode($pendingAd->enrichment?->research, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '-' }}</pre>
        </div>
        <div class="col-md-6 mb-3">
          <h6>Analise IA</h6>
          <pre class="pipeline-json">{{ json_encode($pendingAd->enrichment?->ai_analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '-' }}</pre>
        </div>
        <div class="col-md-6 mb-3">
          <h6>Imagens</h6>
          <pre class="pipeline-json">{{ json_encode($pendingAd->enrichment?->images, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '-' }}</pre>
        </div>
        <div class="col-md-6 mb-3">
          <h6>SEO</h6>
          <pre class="pipeline-json">{{ json_encode($pendingAd->enrichment?->seo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '-' }}</pre>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Rascunho Prestashop</strong>
      <form method="post" action="{{ route('admin.zcm.pending-ads.prestashop-draft.generate', $pendingAd) }}" class="mb-0">
        @csrf
        <button class="btn btn-sm btn-outline-primary" type="submit">
          {{ $prestashopDraft ? 'Regenerar rascunho' : 'Gerar rascunho Prestashop' }}
        </button>
      </form>
    </div>
    <div class="card-body">
      @if(!$prestashopDraft)
        <div class="alert alert-info pipeline-price-note">
          Ainda nao existe rascunho Prestashop. Gere o rascunho depois de ter pesquisa, imagens e SEO suficientes para o poder editar antes da sincronizacao.
        </div>
      @else
        <form method="post" action="{{ route('admin.zcm.pending-ads.prestashop-draft.save', $pendingAd) }}">
          @csrf
          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label>Nome do produto</label>
                <input class="form-control" name="name" value="{{ old('name', data_get($prestashopDraft, 'name')) }}" maxlength="128" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Refer&ecirc;ncia</label>
                <input class="form-control" name="reference" value="{{ old('reference', data_get($prestashopDraft, 'reference')) }}" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Pre&ccedil;o</label>
                <input class="form-control" name="price" type="number" min="0" step="0.01" value="{{ old('price', data_get($prestashopDraft, 'price')) }}">
                <small class="text-muted">{{ data_get($prestashopDraft, 'price_source', 'Preco editavel antes de exportar.') }}</small>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Moeda</label>
                <input class="form-control" name="currency" value="{{ old('currency', data_get($prestashopDraft, 'currency', 'EUR')) }}">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Quantidade</label>
                <input class="form-control" name="quantity" type="number" min="0" step="1" value="{{ old('quantity', data_get($prestashopDraft, 'quantity', 1)) }}">
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Estado</label>
                <select class="form-control" name="condition">
                  @foreach(['used' => 'Usado', 'reconditioned' => 'Recondicionado', 'new' => 'Novo'] as $value => $label)
                    <option value="{{ $value }}" {{ old('condition', data_get($prestashopDraft, 'condition')) === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Categoria</label>
                <input class="form-control" name="category" value="{{ old('category', data_get($prestashopDraft, 'category')) }}">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Categoria Prestashop</label>
                <select class="form-control" name="prestashop_category_id">
                  <option value="">Selecionar categoria</option>
                  @foreach($prestashopCategories as $category)
                    <option value="{{ data_get($category, 'id') }}" {{ (string) old('prestashop_category_id', data_get($prestashopDraft, 'prestashop_category_id')) === (string) data_get($category, 'id') ? 'selected' : '' }}>
                      #{{ data_get($category, 'id') }} - {{ str_repeat('- ', max(0, (int) data_get($category, 'level_depth') - 2)) }}{{ data_get($category, 'name') }}
                    </option>
                  @endforeach
                </select>
                @if(data_get($prestashopDraft, 'prestashop_category_name'))
                  <small class="text-muted">
                    Sugerida: {{ data_get($prestashopDraft, 'prestashop_category_name') }}
                    @if(data_get($prestashopDraft, 'prestashop_category_score'))
                      (score {{ data_get($prestashopDraft, 'prestashop_category_score') }})
                    @endif
                  </small>
                @else
                  <small class="text-muted">Regenera o rascunho para consultar categorias Prestashop.</small>
                @endif
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Marca</label>
                <input class="form-control" name="manufacturer" value="{{ old('manufacturer', data_get($prestashopDraft, 'manufacturer')) }}">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Modelo</label>
                <input class="form-control" name="model" value="{{ old('model', data_get($prestashopDraft, 'model')) }}">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Slug Prestashop</label>
                <input class="form-control" name="link_rewrite" value="{{ old('link_rewrite', data_get($prestashopDraft, 'link_rewrite')) }}">
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>Descri&ccedil;&atilde;o curta</label>
                <textarea class="form-control" name="short_description" rows="3">{{ old('short_description', data_get($prestashopDraft, 'short_description')) }}</textarea>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>Descri&ccedil;&atilde;o completa</label>
                <textarea class="form-control" name="description" rows="8">{{ old('description', data_get($prestashopDraft, 'description')) }}</textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Meta title</label>
                <input class="form-control" name="meta_title" value="{{ old('meta_title', data_get($prestashopDraft, 'meta_title')) }}" maxlength="70">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Meta description</label>
                <input class="form-control" name="meta_description" value="{{ old('meta_description', data_get($prestashopDraft, 'meta_description')) }}" maxlength="170">
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>Notas para aprova&ccedil;&atilde;o</label>
                <textarea class="form-control" name="approval_notes" rows="3">{{ old('approval_notes', data_get($prestashopDraft, 'approval_notes')) }}</textarea>
              </div>
            </div>
          </div>

          @if(!empty(data_get($prestashopDraft, 'compatibilities', [])) || !empty(data_get($prestashopDraft, 'technical_references', [])))
            <div class="pipeline-draft-meta mb-3">
              @if(!empty(data_get($prestashopDraft, 'technical_references', [])))
                <div><strong>Refer&ecirc;ncias t&eacute;cnicas:</strong> {{ implode(', ', data_get($prestashopDraft, 'technical_references', [])) }}</div>
              @endif
              @if(!empty(data_get($prestashopDraft, 'compatibilities', [])))
                <div><strong>Compatibilidades:</strong> {{ implode(', ', data_get($prestashopDraft, 'compatibilities', [])) }}</div>
              @endif
            </div>
          @endif

          @if(!empty($prestashopTranslations))
            <div class="mb-3">
              <h6>Tradu&ccedil;&otilde;es Prestashop</h6>
              <div class="pipeline-translation-grid">
                @foreach($prestashopTranslations as $languageId => $translation)
                  <div class="pipeline-translation-panel">
                    <div class="pipeline-translation-title">
                      <strong>{{ data_get($translation, 'language_name', 'Idioma ' . $languageId) }}</strong>
                      <span class="badge badge-light">{{ data_get($translation, 'iso_code') }}</span>
                    </div>
                    <div class="form-group">
                      <label>Nome</label>
                      <input class="form-control" name="translations[{{ $languageId }}][name]" value="{{ old('translations.' . $languageId . '.name', data_get($translation, 'name')) }}" maxlength="128">
                    </div>
                    <div class="form-group">
                      <label>Descri&ccedil;&atilde;o curta</label>
                      <textarea class="form-control" name="translations[{{ $languageId }}][short_description]" rows="2">{{ old('translations.' . $languageId . '.short_description', data_get($translation, 'short_description')) }}</textarea>
                    </div>
                    <div class="form-group">
                      <label>Descri&ccedil;&atilde;o</label>
                      <textarea class="form-control" name="translations[{{ $languageId }}][description]" rows="5">{{ old('translations.' . $languageId . '.description', data_get($translation, 'description')) }}</textarea>
                    </div>
                    <div class="form-group">
                      <label>Meta title</label>
                      <input class="form-control" name="translations[{{ $languageId }}][meta_title]" value="{{ old('translations.' . $languageId . '.meta_title', data_get($translation, 'meta_title')) }}" maxlength="70">
                    </div>
                    <div class="form-group">
                      <label>Meta description</label>
                      <input class="form-control" name="translations[{{ $languageId }}][meta_description]" value="{{ old('translations.' . $languageId . '.meta_description', data_get($translation, 'meta_description')) }}" maxlength="170">
                    </div>
                    <div class="form-group mb-0">
                      <label>Slug</label>
                      <input class="form-control" name="translations[{{ $languageId }}][link_rewrite]" value="{{ old('translations.' . $languageId . '.link_rewrite', data_get($translation, 'link_rewrite')) }}">
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          @endif

          @if(!empty(data_get($prestashopDraft, 'images', [])))
            <div class="mb-3">
              <h6>Imagens que seguem no rascunho</h6>
              <div class="pipeline-image-grid pipeline-draft-images">
                @foreach(data_get($prestashopDraft, 'images', []) as $image)
                  @php
                    $draftImageUrl = data_get($image, 'url');
                    if (data_get($image, 'source') === 'ai_generated' && data_get($image, 'storage_path')) {
                      $draftImageUrl = route('admin.zcm.pending-ads.generated-image', [$pendingAd, basename(data_get($image, 'storage_path'))]);
                    }
                  @endphp
                  <div class="pipeline-image-item">
                    <a href="{{ $draftImageUrl }}" target="_blank" rel="noopener noreferrer">
                      <img src="{{ $draftImageUrl }}" alt="Imagem do rascunho Prestashop">
                    </a>
                    <div class="pipeline-image-meta">
                      <span class="badge badge-{{ data_get($image, 'source') === 'ai_generated' ? 'success' : 'primary' }}">
                        {{ data_get($image, 'source') === 'ai_generated' ? 'IA gerada' : 'Fonte externa' }}
                      </span>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          @endif

          <button class="btn btn-primary btn-sm" type="submit">Guardar rascunho</button>
          <span class="text-muted ml-2">Este passo nao sincroniza com Prestashop.</span>
        </form>
      @endif
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><strong>Validacao humana</strong></div>
    <div class="card-body">
      <form method="post" action="{{ route('admin.zcm.pending-ads.approve', $pendingAd) }}" class="d-inline">
        @csrf
        <button class="btn btn-success btn-sm">Aprovar</button>
      </form>
      <form method="post" action="{{ route('admin.zcm.pending-ads.request-changes', $pendingAd) }}" class="d-inline">
        @csrf
        <input type="hidden" name="reason" value="Alteracoes pedidas pela validacao humana.">
        <button class="btn btn-warning btn-sm">Pedir alteracoes</button>
      </form>
      <form method="post" action="{{ route('admin.zcm.pending-ads.reject', $pendingAd) }}" class="d-inline">
        @csrf
        <input type="hidden" name="reason" value="Rejeitado pela validacao humana.">
        <button class="btn btn-danger btn-sm">Rejeitar</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Eventos do pipeline</strong></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
          <thead>
            <tr>
              <th>Data</th>
              <th>Etapa</th>
              <th>Status</th>
              <th>Utilizador</th>
              <th>Erro</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pendingAd->pipelineEvents as $event)
              <tr>
                <td>{{ optional($event->created_at)->format('Y-m-d H:i:s') }}</td>
                <td>{{ $event->stage }}</td>
                <td><span class="badge badge-{{ $event->status === 'success' || $event->status === 'approved' ? 'success' : ($event->status === 'failed' || $event->status === 'rejected' ? 'danger' : 'secondary') }}">{{ $event->status }}</span></td>
                <td>{{ $event->creator?->name }}</td>
                <td>{{ $event->error }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted">Sem eventos registados.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('styles')
@parent
<style>
  .pipeline-json {
    background: #ffffff;
    border: 1px solid #cfd8e3;
    border-radius: 4px;
    color: #24324a;
    font-size: 12px;
    line-height: 1.5;
    max-height: 360px;
    min-height: 120px;
    overflow: auto;
    padding: 12px;
    white-space: pre;
  }

  .pipeline-summary {
    background: #f8fafc;
    border: 1px solid #cfd8e3;
    border-radius: 4px;
    color: #24324a;
    min-height: 74px;
    padding: 12px;
  }

  .pipeline-summary small {
    color: #52647d;
  }

  .pipeline-sources {
    background: #ffffff;
    color: #24324a;
  }

  .pipeline-sources small {
    color: #52647d;
    display: block;
  }

  .pipeline-price-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  }

  .pipeline-price-note {
    font-size: 13px;
    margin-bottom: 10px;
    padding: 10px 12px;
  }

  .pipeline-price-card {
    background: #f8fafc;
    border: 1px solid #cfd8e3;
    border-radius: 4px;
    color: #24324a;
    padding: 12px;
  }

  .pipeline-price-card small {
    color: #52647d;
    display: block;
    margin-bottom: 4px;
  }

  .pipeline-price-card strong {
    font-size: 18px;
  }

  .pipeline-image-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  }

  .pipeline-image-item {
    background: #ffffff;
    border: 1px solid #cfd8e3;
    border-radius: 4px;
    padding: 8px;
  }

  .pipeline-image-item img {
    aspect-ratio: 4 / 3;
    background: #f8fafc;
    border: 1px solid #e1e7ef;
    display: block;
    object-fit: contain;
    width: 100%;
  }

  .pipeline-image-meta {
    align-items: center;
    color: #52647d;
    display: flex;
    font-size: 12px;
    gap: 8px;
    margin-top: 8px;
  }

  .pipeline-image-source {
    display: inline-block;
    font-size: 12px;
    margin-top: 4px;
  }

  .pipeline-image-working {
    opacity: .65;
  }

  .pipeline-image-item-new {
    border-color: #2eb85c;
  }

  .pipeline-translation-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  }

  .pipeline-translation-panel {
    background: #f8fafc;
    border: 1px solid #cfd8e3;
    border-radius: 4px;
    padding: 12px;
  }

  .pipeline-translation-title {
    align-items: center;
    color: #24324a;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    margin-bottom: 10px;
  }
</style>
@endsection

@section('scripts')
@parent
<script>
  $(function () {
    const alertBox = $('#zcm-image-ajax-alert');
    const grid = $('#zcm-image-grid');

    function showAjaxMessage(type, message) {
      alertBox
        .removeClass('d-none alert-success alert-danger')
        .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
        .text(message);
    }

    function escapeHtml(value) {
      return $('<div>').text(value || '').html();
    }

    function generatedImageCard(image) {
      const url = escapeHtml(image.url);
      const originalUrl = escapeHtml(image.original_url);
      const model = escapeHtml(image.model || 'IA');
      const deleteUrl = '{{ route('admin.zcm.pending-ads.delete-generated-image', $pendingAd) }}';
      const csrf = '{{ csrf_token() }}';

      return `
        <div class="pipeline-image-item pipeline-image-item-new">
          <a href="${url}" target="_blank" rel="noopener noreferrer">
            <img src="${url}" alt="Imagem gerada por IA">
          </a>
          <div class="pipeline-image-meta">
            <span class="badge badge-success">IA gerada</span>
            <span>${model}</span>
          </div>
          ${originalUrl ? `<a class="pipeline-image-source" href="${originalUrl}" target="_blank" rel="noopener noreferrer">Original</a>` : ''}
          <form method="post" action="${deleteUrl}" class="mt-2 js-delete-ai-image-form">
            <input type="hidden" name="_token" value="${csrf}">
            <input type="hidden" name="image_url" value="${url}">
            <button class="btn btn-sm btn-outline-danger btn-block" type="submit">Eliminar</button>
          </form>
        </div>
      `;
    }

    function prependGeneratedImage(image) {
      const loader = new Image();
      let inserted = false;
      const insert = function () {
        if (inserted) {
          return;
        }

        inserted = true;
        grid.prepend(generatedImageCard(image));
      };
      const fallback = window.setTimeout(function () {
        insert();
      }, 5000);

      loader.onload = function () {
        window.clearTimeout(fallback);
        insert();
      };

      loader.onerror = function () {
        window.clearTimeout(fallback);
        insert();
      };

      loader.src = image.url;
    }

    $(document).on('submit', '.js-ai-image-form', function (event) {
      event.preventDefault();

      const form = $(this);
      const button = form.find('button[type="submit"]');
      const originalText = button.text();

      button.prop('disabled', true).text('A gerar...');
      form.closest('.pipeline-image-item').addClass('pipeline-image-working');

      $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: form.serialize(),
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        success: function (response) {
          if (response.image) {
            prependGeneratedImage(response.image);
          }

          showAjaxMessage('success', response.message || 'Imagem recriada com IA.');
        },
        error: function (xhr) {
          const message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Nao foi possivel gerar a imagem.';

          showAjaxMessage('error', message);
        },
        complete: function () {
          button.prop('disabled', false).text(originalText);
          form.closest('.pipeline-image-item').removeClass('pipeline-image-working');
        }
      });
    });

    $(document).on('submit', '.js-delete-ai-image-form', function (event) {
      event.preventDefault();

      if (!confirm('Eliminar esta imagem gerada por IA?')) {
        return;
      }

      const form = $(this);
      const card = form.closest('.pipeline-image-item');
      const button = form.find('button[type="submit"]');
      const originalText = button.text();

      button.prop('disabled', true).text('A eliminar...');
      card.addClass('pipeline-image-working');

      $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: form.serialize(),
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        success: function (response) {
          card.remove();
          showAjaxMessage('success', response.message || 'Imagem IA eliminada.');
        },
        error: function (xhr) {
          const message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Nao foi possivel eliminar a imagem.';

          showAjaxMessage('error', message);
          button.prop('disabled', false).text(originalText);
          card.removeClass('pipeline-image-working');
        }
      });
    });
  });
</script>
@endsection
