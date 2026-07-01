<?php

use App\Http\Controllers\Admin\MoloniSuplierInvoiceController;
use App\Http\Controllers\Admin\ZcmDashboardController;
use App\Http\Controllers\Admin\ZcmPendingAdController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::redirect('/', '/login');

Route::get('/home', function () {
    if (session('status')) {
        return redirect()->route('admin.home')->with('status', session('status'));
    }

    return redirect()->route('admin.home');
});

Auth::routes(['register' => false]);

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'namespace' => 'Admin', 'middleware' => ['auth']], function () {
    Route::get('/', 'HomeController@index')->name('home');
    // Permissions
    Route::delete('permissions/destroy', 'PermissionsController@massDestroy')->name('permissions.massDestroy');
    Route::resource('permissions', 'PermissionsController');

    // Roles
    Route::delete('roles/destroy', 'RolesController@massDestroy')->name('roles.massDestroy');
    Route::resource('roles', 'RolesController');

    // Users
    Route::delete('users/destroy', 'UsersController@massDestroy')->name('users.massDestroy');
    Route::resource('users', 'UsersController');

    // Assistant
    Route::delete('assistants/destroy', 'AssistantController@massDestroy')->name('assistants.massDestroy');
    Route::resource('assistants', 'AssistantController');

    // Openai
    Route::delete('openais/destroy', 'OpenaiController@massDestroy')->name('openais.massDestroy');
    Route::resource('openais', 'OpenaiController');

    // Instruction
    Route::delete('instructions/destroy', 'InstructionController@massDestroy')->name('instructions.massDestroy');
    Route::resource('instructions', 'InstructionController');

    // Training
    Route::prefix('trainings')->group(function () {
        Route::get('/', 'TrainingController@index')->name('trainings.index');
        Route::get('assistant/{assistant_id}', 'TrainingController@assistant');
        Route::prefix('instructions')->group(function () {
            Route::post('create', 'TrainingController@instructionsCreate');
            Route::get('load/{assistant_id}', 'TrainingController@instructionsLoad');
            Route::get('delete/{instruction_id}', 'TrainingController@instructionDelete');
            Route::post('sync-assistant', 'TrainingController@syncAssistant');
        });
        Route::prefix('chat')->group(function () {
            Route::post('create-thread-and-run', 'TrainingController@chatCreateThreadAndRun');
            Route::get('list-run-steps/{thread_id}/{run_id}', 'TrainingController@chatListRunSteps');
            Route::get('get-run-status/{thread_id}/{run_id}', 'TrainingController@getRunStatus');
            Route::get('get-messages/{thread_id}', 'TrainingController@getMessages');
            Route::post('add-message', 'TrainingController@addMessage');
            Route::get('run-the-thread/{assistant_id}/{thread_id}', 'TrainingController@runTheThread');
            Route::post('submit-tool-outputs-to-run', 'TrainingController@chatSubmitToolOutputsToRun');
            Route::post('send-email', 'TrainingController@sendEmail');
            Route::post('log', 'TrainingController@log');
        });
        Route::prefix('api')->group(function () {
            Route::get('search/{assistant_id}/{search}', 'TrainingController@apiSearch');
        });
    });

    // Project
    Route::delete('projects/destroy', 'ProjectController@massDestroy')->name('projects.massDestroy');
    Route::resource('projects', 'ProjectController');

    // Log
    Route::delete('logs/destroy', 'LogController@massDestroy')->name('logs.massDestroy');
    Route::resource('logs', 'LogController');

    // Log Message
    Route::delete('log-messages/destroy', 'LogMessageController@massDestroy')->name('log-messages.massDestroy');
    Route::resource('log-messages', 'LogMessageController');

    // Log History
    Route::prefix('log-histories')->group(function () {
        Route::get('/', 'LogHistoryController@index')->name('log-histories.index');
        Route::get('history/{log_id}', 'LogHistoryController@history');
    });

    // Form
    Route::delete('forms/destroy', 'FormController@massDestroy')->name('forms.massDestroy');
    Route::post('forms/media', 'FormController@storeMedia')->name('forms.storeMedia');
    Route::post('forms/ckmedia', 'FormController@storeCKEditorImages')->name('forms.storeCKEditorImages');
    Route::resource('forms', 'FormController');

    // Form Fields
    Route::delete('form-fields/destroy', 'FormFieldsController@massDestroy')->name('form-fields.massDestroy');
    Route::resource('form-fields', 'FormFieldsController');

    // Form Data
    Route::delete('form-datas/destroy', 'FormDataController@massDestroy')->name('form-datas.massDestroy');
    Route::resource('form-datas', 'FormDataController');

    // Forms Assembly
    Route::prefix('forms-assemblies')->group(function () {
        Route::get('/{form_id?}', 'FormsAssemblyController@index')->name('forms-assemblies.index');
        Route::get('change-project-id/{project_id}', 'FormsAssemblyController@changeProjectId');
        Route::post('create-form-field', 'FormsAssemblyController@createFormField');
        Route::get('form-ajax/{form_id}', 'FormsAssemblyController@formAjax');
        Route::post('update-positions', 'FormsAssemblyController@updatePositions');
        Route::post('new-form', 'FormsAssemblyController@newForm');
        Route::post('new-form/media', 'FormsAssemblyController@storeMedia');
        Route::post('new-form/ckmedia', 'FormsAssemblyController@storeCKEditorImages')->name('forms-assemblies.new-form.storeCKEditorImages');
        Route::get('delete-field/{form_field_id}', 'FormsAssemblyController@deleteField');
        Route::get('delete-form/{form_id}', 'FormsAssemblyController@deleteForm');
        Route::post('form-send', 'FormsAssemblyController@formSend');
    });

    // Forms Inbox
    Route::prefix('forms-inboxes')->group(function () {
        Route::get('/', 'FormsInboxController@index')->name('forms-inboxes.index');
        Route::get('/{form_data_id}', 'FormsInboxController@form')->name('forms-inboxes.form');
    });

    // Bot
    Route::delete('bots/destroy', 'BotController@massDestroy')->name('bots.massDestroy');
    Route::resource('bots', 'BotController');

    // Moloni Suplier Invoice
    Route::get('moloni-suplier-invoices/invoice/{moloni_suplier_invoice_id}', [MoloniSuplierInvoiceController::class, 'launchToMoloni'])
        ->name('moloni-suplier-invoices.launch');
    Route::get('moloni/suppliers', [MoloniSuplierInvoiceController::class, 'getSuppliersByName'])->name('moloni.suppliers');
    Route::post('moloni/create-supplier', [MoloniSuplierInvoiceController::class, 'createSupplier'])->name('moloni.suppliers.create');
    Route::post('moloni/sync-invoice', [MoloniSuplierInvoiceController::class, 'syncInvoice'])->name('moloni.syncInvoice');


    Route::delete('moloni-suplier-invoices/destroy', 'MoloniSuplierInvoiceController@massDestroy')->name('moloni-suplier-invoices.massDestroy');
    Route::post('moloni-suplier-invoices/media', 'MoloniSuplierInvoiceController@storeMedia')->name('moloni-suplier-invoices.storeMedia');
    Route::post('moloni-suplier-invoices/ckmedia', 'MoloniSuplierInvoiceController@storeCKEditorImages')->name('moloni-suplier-invoices.storeCKEditorImages');
    Route::resource('moloni-suplier-invoices', 'MoloniSuplierInvoiceController');

    // Ai Message
    Route::delete('ai-messages/destroy', 'AiMessageController@massDestroy')->name('ai-messages.massDestroy');

    // AJAX
    Route::post('ai-messages/search',  'AiMessageController@search')->name('ai-messages.search');
    Route::post('ai-messages/history', 'AiMessageController@history')->name('ai-messages.history');

    // Resource
    Route::resource('ai-messages', 'AiMessageController');

    // Technical Assistante Session
    Route::delete('technical-assistante-sessions/destroy', 'TechnicalAssistanteSessionController@massDestroy')->name('technical-assistante-sessions.massDestroy');
    Route::resource('technical-assistante-sessions', 'TechnicalAssistanteSessionController');

    // Technical Assistante Messages
    Route::delete('technical-assistante-messages/destroy', 'TechnicalAssistanteMessagesController@massDestroy')->name('technical-assistante-messages.massDestroy');
    Route::resource('technical-assistante-messages', 'TechnicalAssistanteMessagesController');

    // Ai Assistant Category
    Route::delete('ai-assistant-categories/destroy', 'AiAssistantCategoryController@massDestroy')->name('ai-assistant-categories.massDestroy');
    Route::resource('ai-assistant-categories', 'AiAssistantCategoryController');

    // Ai Assistant Intruction
    Route::delete('ai-assistant-intructions/destroy', 'AiAssistantIntructionController@massDestroy')->name('ai-assistant-intructions.massDestroy');
    Route::post('ai-assistant-intructions/media', 'AiAssistantIntructionController@storeMedia')->name('ai-assistant-intructions.storeMedia');
    Route::post('ai-assistant-intructions/ckmedia', 'AiAssistantIntructionController@storeCKEditorImages')->name('ai-assistant-intructions.storeCKEditorImages');
    Route::resource('ai-assistant-intructions', 'AiAssistantIntructionController');

    // AJAX para categorias e instruções do assistente
    Route::get('ai-messages/assistant/categories', 'AiMessageController@assistantCategories')
        ->name('ai-messages.assistant.categories');

    Route::get('ai-messages/assistant/instructions', 'AiMessageController@assistantInstructions')
        ->name('ai-messages.assistant.instructions');

    Route::get('/zcm/dashboard', ZcmDashboardController::class)->name('zcm.dashboard');
    Route::get('/zcm/pending-ads', [ZcmPendingAdController::class, 'index'])->name('zcm.pending-ads.index');
    Route::get('/zcm/pending-ads/export', [ZcmPendingAdController::class, 'export'])->name('zcm.pending-ads.export');
    Route::post('/zcm/pending-ads/sync', [ZcmPendingAdController::class, 'sync'])->name('zcm.pending-ads.sync');
    Route::get('/zcm/pending-ads/{pendingAd}', [ZcmPendingAdController::class, 'show'])->name('zcm.pending-ads.show');
    Route::get('/zcm/pending-ads/{pendingAd}/generated-image/{filename}', [ZcmPendingAdController::class, 'generatedImage'])->name('zcm.pending-ads.generated-image');
    Route::post('/zcm/pending-ads/{pendingAd}/run-stage', [ZcmPendingAdController::class, 'runStage'])->name('zcm.pending-ads.run-stage');
    Route::post('/zcm/pending-ads/{pendingAd}/recreate-image', [ZcmPendingAdController::class, 'recreateImage'])->name('zcm.pending-ads.recreate-image');
    Route::post('/zcm/pending-ads/{pendingAd}/delete-generated-image', [ZcmPendingAdController::class, 'deleteGeneratedImage'])->name('zcm.pending-ads.delete-generated-image');
    Route::post('/zcm/pending-ads/{pendingAd}/prestashop-draft/generate', [ZcmPendingAdController::class, 'generatePrestashopDraft'])->name('zcm.pending-ads.prestashop-draft.generate');
    Route::post('/zcm/pending-ads/{pendingAd}/prestashop-draft', [ZcmPendingAdController::class, 'savePrestashopDraft'])->name('zcm.pending-ads.prestashop-draft.save');
    Route::post('/zcm/pending-ads/{pendingAd}/approve', [ZcmPendingAdController::class, 'approve'])->name('zcm.pending-ads.approve');
    Route::post('/zcm/pending-ads/{pendingAd}/reject', [ZcmPendingAdController::class, 'reject'])->name('zcm.pending-ads.reject');
    Route::post('/zcm/pending-ads/{pendingAd}/request-changes', [ZcmPendingAdController::class, 'requestChanges'])->name('zcm.pending-ads.request-changes');
});

Route::group(['prefix' => 'profile', 'as' => 'profile.', 'namespace' => 'Auth', 'middleware' => ['auth']], function () {
    // Change password
    if (file_exists(app_path('Http/Controllers/Auth/ChangePasswordController.php'))) {
        Route::get('password', 'ChangePasswordController@edit')->name('password.edit');
        Route::post('password', 'ChangePasswordController@update')->name('password.update');
        Route::post('profile', 'ChangePasswordController@updateProfile')->name('password.updateProfile');
        Route::post('profile/destroy', 'ChangePasswordController@destroy')->name('password.destroyProfile');
    }
});

Route::post('ai-messages/search', [App\Http\Controllers\Admin\AiMessageController::class, 'search'])->name('ai-messages.search');

Route::prefix('chat')->group(function () {
    Route::get('/assistant/{assistant_id}', 'WebsiteController@assistant');
    Route::get('create-assistant/{project_name}', 'ChatController@chatCreateAssistant');
    Route::post('create-thread-and-run', 'ChatController@chatCreateThreadAndRun');
    Route::get('list-run-steps/{thread_id}/{run_id}', 'ChatController@chatListRunSteps');
    Route::get('get-run-status/{thread_id}/{run_id}', 'ChatController@getRunStatus');
    Route::get('get-messages/{thread_id}', 'ChatController@getMessages');
    Route::post('add-message', 'ChatController@addMessage');
    Route::get('run-the-thread/{assistant_id}/{thread_id}', 'ChatController@runTheThread');
    Route::post('submit-tool-outputs-to-run', 'ChatController@chatSubmitToolOutputsToRun');
    Route::post('log', 'ChatController@log');
    Route::get('store-thread-in-session/{thread_id}', 'WebsiteController@storeThreadInSession');
    Route::get('store-log-in-session/{log_id}', 'WebsiteController@storeLogInSession');
    Route::get('check-if-thread-in-session', 'WebsiteController@checkIfThreadInSession');
    Route::get('check-if-log-in-session', 'WebsiteController@checkIfLogInSession');
    Route::get('get-all-messages/{log_id}', 'WebsiteController@getAllMessages');
});

Route::prefix('api')->group(function () {
    Route::get('search/{assistant_id}/{search}', 'ChatController@apiSearch');
    Route::post('send-email', 'ChatController@sendEmail');
});

Route::prefix('form')->group(function () {
    Route::get('{form_id}', 'FormsController@index');
    Route::post('form-send', 'FormsController@formSend');
    Route::get('all/{project_id}', 'FormsController@all');
});

Route::get('logistics', 'LogisticsController@index');

Route::prefix('suporte-tecnico')->group(function () {
    Route::get('/', 'WebsiteController@suporteTecnico')->name('suporte-tecnico');
});
