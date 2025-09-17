<?php

use App\Http\Controllers\RefSearchController;

Route::post('login', 'Api\\AuthController@login');

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::post('send-photo', 'Api\\AuthController@sendPhoto');
    Route::get('get-user', 'Api\\AuthController@getUser');
    Route::post('update-user', 'Api\\AuthController@updateUser');
    Route::get('form-datas/{done}/{page}', 'Api\\AuthController@formDatas');
    Route::get('form-data/{form_data_id}', 'Api\\AuthController@formData');
    Route::post('search-stock', 'Api\\AuthController@searchStock');
    Route::get('update-state/{form_data_id}', 'Api\\AuthController@updateState');
    Route::get('prestashop-categories', 'Api\\AuthController@prestashopCategories');
    Route::get('prestashop-manufacturers', 'Api\\AuthController@prestashopManufacturers');
    Route::get('prestashop-category/{ctegory_id}', 'Api\\AuthController@prestashopCategory');
    Route::get('prestashop-manufacturer/{manufacturer_id}', 'Api\\AuthController@prestashopManufacturer');
    Route::post('create-product', 'Api\\AuthController@createProduct');
    Route::post('upload-image', 'Api\\AuthController@uploadImage');
    Route::post('search-form-datas', 'Api\\AuthController@searchFormDatas');

    Route::prefix('zcm/orders')->group(function () {
        Route::get('categories', 'ZcmController@categories');
        Route::get('sub-categories/{phase_id}', 'ZcmController@subCategories');
        Route::post('zcm-update-state', 'ZcmController@zcmUpdateState');
        Route::get('check-zcm-stock/{prestashop_id}', 'ZcmController@checkZcmStock');
        Route::get('prestashop-product/{prestashop_id}', 'ZcmController@prestashopProduct');
        Route::post('prestashop-create-stock', 'ZcmController@prestashopCreateStock');
        Route::post('prestashop-update-stock', 'ZcmController@prestashopUpdateStock');
    });

});

Route::post('/assistant', 'Api\\AIController@handle');

Route::post('/chat', 'Api\\TechnicalAssistanteController@responder');
Route::post('/chat/reset', 'Api\\TechnicalAssistanteController@resetChat');

Route::get('/ref-search', [RefSearchController::class, 'index']);