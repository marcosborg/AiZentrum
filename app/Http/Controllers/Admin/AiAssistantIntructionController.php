<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyAiAssistantIntructionRequest;
use App\Http\Requests\StoreAiAssistantIntructionRequest;
use App\Http\Requests\UpdateAiAssistantIntructionRequest;
use App\Models\AiAssistantCategory;
use App\Models\AiAssistantIntruction;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class AiAssistantIntructionController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('ai_assistant_intruction_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiAssistantIntructions = AiAssistantIntruction::with(['user', 'ai_assistant_category'])->get();

        return view('admin.aiAssistantIntructions.index', compact('aiAssistantIntructions'));
    }

    public function create()
    {
        abort_if(Gate::denies('ai_assistant_intruction_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $ai_assistant_categories = AiAssistantCategory::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.aiAssistantIntructions.create', compact('ai_assistant_categories', 'users'));
    }

    public function store(StoreAiAssistantIntructionRequest $request)
    {
        $aiAssistantIntruction = AiAssistantIntruction::create($request->all());

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $aiAssistantIntruction->id]);
        }

        return redirect()->route('admin.ai-assistant-intructions.index');
    }

    public function edit(AiAssistantIntruction $aiAssistantIntruction)
    {
        abort_if(Gate::denies('ai_assistant_intruction_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $ai_assistant_categories = AiAssistantCategory::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $aiAssistantIntruction->load('user', 'ai_assistant_category');

        return view('admin.aiAssistantIntructions.edit', compact('aiAssistantIntruction', 'ai_assistant_categories', 'users'));
    }

    public function update(UpdateAiAssistantIntructionRequest $request, AiAssistantIntruction $aiAssistantIntruction)
    {
        $aiAssistantIntruction->update($request->all());

        return redirect()->route('admin.ai-assistant-intructions.index');
    }

    public function show(AiAssistantIntruction $aiAssistantIntruction)
    {
        abort_if(Gate::denies('ai_assistant_intruction_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiAssistantIntruction->load('user', 'ai_assistant_category');

        return view('admin.aiAssistantIntructions.show', compact('aiAssistantIntruction'));
    }

    public function destroy(AiAssistantIntruction $aiAssistantIntruction)
    {
        abort_if(Gate::denies('ai_assistant_intruction_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiAssistantIntruction->delete();

        return back();
    }

    public function massDestroy(MassDestroyAiAssistantIntructionRequest $request)
    {
        $aiAssistantIntructions = AiAssistantIntruction::find(request('ids'));

        foreach ($aiAssistantIntructions as $aiAssistantIntruction) {
            $aiAssistantIntruction->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('ai_assistant_intruction_create') && Gate::denies('ai_assistant_intruction_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new AiAssistantIntruction();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
