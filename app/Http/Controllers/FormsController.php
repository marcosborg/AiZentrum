<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormData;
use App\Http\Controllers\Traits\Iftech;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use \App\Models\FormField;

class FormsController extends Controller
{

    use Iftech;

    public function index(Request $request, $form_id)
    {
        $locale = $this->setPublicLocale($request);
        $form = Form::where('id', $form_id)->with('form_fields')->first();

        return view('website.form', compact('form', 'locale'));
    }

    public function formSend(Request $request)
    {

        $all = $request->all();

        $fields = [];
        $form_id = null;

        foreach ($all as $key => $value) {
            if ($key == 'form_id') {
                $form_id = $value;
            }
        }

        foreach ($all as $key => $value) {
            if ($key != 'form_id') {
                $form_field = FormField::where([
                    'form_id' => $form_id,
                    'name' => $key,
                ])->first();
                if ($request->hasFile($key)) {
                    $urls = [];
                    $uploadedFiles = is_array($request->file($key)) ? $request->file($key) : [$request->file($key)];

                    foreach ($uploadedFiles as $uploadedFile) {
                        if ($uploadedFile->isValid()) {
                            $path = $uploadedFile->store('uploads', 'public');
                            $urls[] = url('/') . '/storage/' . $path;
                        }
                    }

                    $fields[] = [
                        'name' => $key,
                        'value' => count($urls) === 1 ? $urls[0] : $urls,
                        'label' => $form_field->label,
                        'type' => $form_field->type,
                        'required' => $form_field->required
                    ];
                } else {
                    $fields[] = [
                        'name' => $key,
                        'value' => $value,
                        'label' => $form_field->label,
                        'type' => $form_field->type,
                        'required' => $form_field->required
                    ];
                }
            }
        }

        $form_data = new FormData;
        $form_data->form_id = $form_id;
        $form_data->data = json_encode($fields);
        $form_data->save();

        $data = json_encode($form_data);

        $form_data->load('form.project');
        $form_data->data = json_decode($form_data->data);

        try {
            Notification::route('mail', env('COMERCIAL_EMAIL'))
                ->notify(new \App\Notifications\FormSubmit($form_data));
        } catch (\Throwable $exception) {
            Log::error('Public form email delivery failed', [
                'form_data_id' => $form_data->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => __('Não foi possível enviar o email. Tente novamente mais tarde.'),
            ], 502);
        }

        //SEND BY API

        $access_token = $this->login();

        $send_form = $this->sendForm($access_token, $data);

        return $send_form;

    }

    public function all(Request $request, $project_id)
    {
        $locale = $this->setPublicLocale($request);
        $forms = Form::where('project_id', $project_id)->get()->load('project');

        return view('website.all', compact('forms', 'locale'));
    }

    private function setPublicLocale(Request $request): string
    {
        $supportedLocales = ['pt', 'en', 'es', 'fr'];
        $locale = $request->query('lang', session('public_locale', 'pt'));

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = 'pt';
        }

        session(['public_locale' => $locale]);
        App::setLocale($locale);

        return $locale;
    }
}
