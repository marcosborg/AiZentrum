<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class PublicChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:', 'session.driver'=>'array','cache.default'=>'array']);
        DB::purge('sqlite');
        foreach ([
            'openais'=>'name TEXT, openai_api_key TEXT',
            'projects'=>'name TEXT, openai_id INTEGER',
            'assistants'=>'name TEXT, project_id INTEGER, assist_code TEXT',
            'instructions'=>'assistant_id INTEGER, text TEXT, position INTEGER',
            'logs'=>'project TEXT',
            'log_messages'=>'log_id INTEGER, role TEXT, message TEXT',
        ] as $table=>$columns) DB::statement("CREATE TABLE $table (id INTEGER PRIMARY KEY AUTOINCREMENT, $columns, created_at TEXT, updated_at TEXT, deleted_at TEXT)");
        DB::table('openais')->insert(['id'=>1,'openai_api_key'=>'test-only-key']);
        DB::table('projects')->insert(['id'=>1,'name'=>'Techniczentrum','openai_id'=>1]);
        DB::table('assistants')->insert(['id'=>1,'project_id'=>1,'name'=>'Test']);
        DB::table('instructions')->insert(['assistant_id'=>1,'text'=>'Encaminha para o formulário correto.','position'=>1]);
        Http::preventStrayRequests();
        Notification::fake();
    }
    private function answer(): array { return ['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'Como posso ajudar?']]]]]; }
    public function test_response_preserves_context_and_records_both_messages(): void
    {
        Http::fake(['api.openai.com/v1/responses'=>Http::response($this->answer())]);
        $this->postJson('/chat/response/1',['message'=>'Olá'])->assertOk()->assertJson(['message'=>'Como posso ajudar?']);
        $this->postJson('/chat/response/1',['message'=>'Uma avaria'])->assertOk();
        Http::assertSent(fn($r)=>count($r['input'])===3 && $r['input'][0]['content']==='Olá' && $r['input'][1]['role']==='assistant');
        $this->assertSame(4,DB::table('log_messages')->count());
        $this->getJson('/chat/response/1')->assertJsonCount(4);
    }
    public function test_upstream_error_is_recoverable_without_exposing_details(): void
    {
        Http::fake(['*'=>Http::response(['error'=>['message'=>'private secret','code'=>'invalid_api_key']],401)]);
        $this->postJson('/chat/response/1',['message'=>'Olá'])->assertStatus(502)->assertDontSee('private secret');
        $this->assertSame(0,DB::table('log_messages')->count());
    }
    public function test_product_tool_result_is_sent_back_to_model(): void
    {
        $mock=\Mockery::mock(\App\Http\Controllers\ChatController::class);
        $mock->shouldReceive('apiSearch')->once()->with('1','ABS')->andReturn(['product'=>'ABS']);
        $this->app->instance(\App\Http\Controllers\ChatController::class,$mock);
        Http::fake(['*'=>Http::sequence()->push(['output'=>[['type'=>'function_call','name'=>'get_products','call_id'=>'call_1','arguments'=>'{"symbol":"ABS"}']]])->push($this->answer())]);
        $this->postJson('/chat/response/1',['message'=>'ABS'])->assertOk();
        Http::assertSent(fn($r)=>collect($r['input'])->contains(fn($i)=>($i['type']??'')==='function_call_output' && $i['call_id']==='call_1'));
        Notification::assertNothingSent();
    }
    public function test_empty_message_is_rejected(): void
    {
        $this->postJson('/chat/response/1',['message'=>''])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
