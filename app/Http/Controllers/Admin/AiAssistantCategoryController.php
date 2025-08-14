<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyAiAssistantCategoryRequest;
use App\Http\Requests\StoreAiAssistantCategoryRequest;
use App\Http\Requests\UpdateAiAssistantCategoryRequest;
use App\Models\AiAssistantCategory;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiAssistantCategoryController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('ai_assistant_category_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiAssistantCategories = AiAssistantCategory::with(['user'])->get();

        return view('admin.aiAssistantCategories.index', compact('aiAssistantCategories'));
    }

    public function create()
    {
        abort_if(Gate::denies('ai_assistant_category_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.aiAssistantCategories.create', compact('users'));
    }

    public function store(StoreAiAssistantCategoryRequest $request)
    {
        $aiAssistantCategory = AiAssistantCategory::create($request->all());

        return redirect()->route('admin.ai-assistant-categories.index');
    }

    public function edit(AiAssistantCategory $aiAssistantCategory)
    {
        abort_if(Gate::denies('ai_assistant_category_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $aiAssistantCategory->load('user');

        return view('admin.aiAssistantCategories.edit', compact('aiAssistantCategory', 'users'));
    }

    public function update(UpdateAiAssistantCategoryRequest $request, AiAssistantCategory $aiAssistantCategory)
    {
        $aiAssistantCategory->update($request->all());

        return redirect()->route('admin.ai-assistant-categories.index');
    }

    public function show(AiAssistantCategory $aiAssistantCategory)
    {
        abort_if(Gate::denies('ai_assistant_category_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiAssistantCategory->load('user');

        return view('admin.aiAssistantCategories.show', compact('aiAssistantCategory'));
    }

    public function destroy(AiAssistantCategory $aiAssistantCategory)
    {
        abort_if(Gate::denies('ai_assistant_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aiAssistantCategory->delete();

        return back();
    }

    public function massDestroy(MassDestroyAiAssistantCategoryRequest $request)
    {
        $aiAssistantCategories = AiAssistantCategory::find(request('ids'));

        foreach ($aiAssistantCategories as $aiAssistantCategory) {
            $aiAssistantCategory->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
