<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use \App\Models\Assistant;
use \App\Http\Controllers\Traits\OpenAi;
use \App\Http\Controllers\Traits\PrestashopApi;
use Illuminate\Support\Facades\Notification;
use \App\Notifications\ChatContact;
use App\Models\Log;
use App\Models\LogMessage;

class ChatController extends Controller
{

    use OpenAi;
    use PrestashopApi;

    public function chatCreateAssistant($project_name)
    {
        $project = Project::where('name', $project_name)->first()->load('assistant.instructions');

        $assistant_instructions = $project->assistant->instructions;
        $instructions_array = [];
        foreach ($assistant_instructions as $assistant_instruction) {
            $instructions_array[] = $assistant_instruction->text;
        }
        $instructions = implode(', ', $instructions_array);
        $assistant_name = $project->assistant->name;
        $openaiApiKey = env('OPENAI_API_KEY');
        $model = 'gpt-3.5-turbo-1106';

        $get_products = [
            'name' => 'get_products',
            'description' => 'Obter produtos por nome ou referência',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'symbol' => [
                        'type' => 'string',
                        'description' => 'Produtos'
                    ]
                ]
            ],
            'required' => ['symbol']
        ];

        $tools = [
            [
                'type' => 'function',
                'function' => $get_products
            ]
        ];

        return $this->createAssistant($openaiApiKey, $assistant_name, $instructions, $tools, $model);

    }

    public function chatCreateThreadAndRun(Request $request)
    {
        $assistant_id = $request->assistant_id;
        $assistant = Assistant::find($assistant_id)->load('project.openai');
        $openaiApiKey = $assistant->project->openai->openai_api_key;
        $assist_code = $assistant->assist_code;
        $message = $request->message;

        return $this->createThreadAndRun($openaiApiKey, $assist_code, $message);
    }

    public function chatListRunSteps($thread_id, $run_id)
    {
        return $this->listRunSteps(env('OPENAI_API_KEY'), $thread_id, $run_id);
    }

    public function getRunStatus($thread_id, $run_id)
    {
        return $this->retrieveRun(env('OPENAI_API_KEY'), $thread_id, $run_id);
    }

    public function getMessages($thread_id)
    {
        return $this->listMessages(env('OPENAI_API_KEY'), $thread_id);
    }

    public function addMessage(Request $request)
    {

        $openaiApiKey = env('OPENAI_API_KEY');
        $thread_id = $request->thread_id;
        $message = $request->message;

        return $this->createMessage($openaiApiKey, $thread_id, $message);
    }

    public function runTheThread($assistant_id, $thread_id)
    {

        $openaiApiKey = env('OPENAI_API_KEY');
        $assist_code = Assistant::find($assistant_id)->assist_code;

        return $this->createRun($openaiApiKey, $assist_code, $thread_id);
    }

    public function apiSearch($assistant_id, $search)
    {
        $assistant = Assistant::find($assistant_id)->load('project');

        switch ($assistant->project->name) {
            case 'Techniczentrum':
                $website = 'https://techniczentrum.com';
                break;
            case 'Electriczentrum':
                $website = 'https://electriczentrum.com';
                break;
            default:
                $website = 'https://airbagszentrum.com';
                break;
        }

        return $this->zentrumSearch($website, $search);

    }

    public function sendEmail(Request $request)
    {
        $data = $request->data;

        Notification::route('mail', config('mail.commercial_address'))
            ->notify(new ChatContact($data));
    }

    public function chatSubmitToolOutputsToRun(Request $request)
    {

        $openaiApiKey = env('OPENAI_API_KEY');
        $thread_id = $request->thread_id;
        $run_id = $request->run_id;
        $tool_call_id = $request->tool_call_id;
        $output = $request->output;

        return $this->submitToolOutputsToRun($openaiApiKey, $thread_id, $run_id, $tool_call_id, $output);
    }

    public function log(Request $request)
    {
        $log_id = $request->log_id;
        $project = $request->project;
        $role = $request->role;
        $message = $request->message;

        if (!$log_id) {
            $log = new Log;
            $log->project = $project;
            $log->save();
            $log_id = $log->id;
        }

        $log_message = new LogMessage;
        $log_message->log_id = $log_id;
        $log_message->role = $role;
        $log_message->message = $message;
        $log_message->save();

        return $log_message;

    }
}
