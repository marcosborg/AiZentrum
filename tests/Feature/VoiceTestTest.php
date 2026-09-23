<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\VoiceTestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceTestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'session.driver' => 'array', 'cache.default' => 'array']);
        \Illuminate\Support\Facades\Gate::define('user_management_access', fn ($user = null) => true);
    }
    public function test_saved_instructions_are_used_by_next_session(): void
    {
        $this->app->instance('env', 'local');
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['openai.api_key' => 'test-secret']);
        $controller = new VoiceTestController;
        $controller->saveInstructions(Request::create('/', 'PUT', ['instructions' => 'Confirma a referência da peça.']));
        $this->assertSame('Confirma a referência da peça.', app(\App\Services\VoiceInstructions::class)->get());
        Http::fake(['api.openai.com/*' => Http::response('answer', 201)]);
        $controller->session(Request::create('/', 'POST', ['sdp' => 'v=0']));
        Http::assertSent(function ($request) {
            foreach ($request->data() as $part) {
                if (($part['name'] ?? null) === 'session') {
                    $instructions = json_decode($part['contents'], true)['instructions'];
                    return str_contains($instructions, 'Confirma a referência da peça.')
                        && str_contains($instructions, 'simulação no painel');
                }
            }
            return false;
        });
    }

    public function test_guest_cannot_change_instructions(): void
    {
        $this->putJson('/admin/voice-test/instructions', ['instructions' => 'alteração'])->assertUnauthorized();
    }

    public function test_production_instructions_do_not_force_a_test_greeting(): void
    {
        $this->app->instance('env', 'production');
        \Illuminate\Support\Facades\Storage::fake('local');
        $service = app(\App\Services\VoiceInstructions::class);
        $service->save('Apresenta-te como assistente virtual da Zentrum.');
        $instructions = $service->forSession();
        $this->assertStringContainsString('CONTEXTO DE PRODUÇÃO', $instructions);
        $this->assertStringNotContainsString('Identifica-te como IA em teste na abertura', $instructions);
        $this->assertStringNotContainsString('Será contactado brevemente', $instructions);
    }

    public function test_guest_cannot_start_a_voice_session(): void
    {
        Http::fake();
        $this->postJson('/admin/voice-test/session', ['sdp' => 'v=0'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_unauthorized_user_cannot_access_voice_actions(): void
    {
        Http::fake();
        $this->app->instance('env', 'production');
        \Illuminate\Support\Facades\Gate::define('user_management_access', fn ($user = null) => false);
        foreach (['index', 'saveInstructions', 'session'] as $method) {
            try {
                (new VoiceTestController)->$method(Request::create('/', 'POST', ['sdp' => 'v=0', 'instructions' => 'alteração']));
                $this->fail('Unauthorized action accepted');
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $this->assertTrue(true);
            }
        }
        Http::assertNothingSent();
    }

    public function test_session_proxies_sdp_without_exposing_key(): void
    {
        $this->app->instance('env', 'production');
        config(['openai.api_key' => 'test-secret']);
        Http::fake(['api.openai.com/*' => Http::response('v=0 answer', 201)]);
        $response = (new VoiceTestController)->session(Request::create('/', 'POST', ['sdp' => 'v=0']));
        $this->assertSame('v=0 answer', $response->getContent());
        $this->assertSame('application/sdp', $response->headers->get('Content-Type'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/realtime/calls'
            && $request->hasHeader('Authorization', 'Bearer test-secret'));
    }

    public function test_upstream_error_does_not_leak_response(): void
    {
        $this->app->instance('env', 'local');
        config(['openai.api_key' => 'test-secret']);
        Http::fake(['api.openai.com/*' => Http::response('sensitive upstream detail', 401)]);
        $response = (new VoiceTestController)->session(Request::create('/', 'POST', ['sdp' => 'v=0']));
        $this->assertSame(502, $response->getStatusCode());
        $this->assertStringNotContainsString('sensitive', $response->getContent());
    }

    public function test_sdp_line_endings_removed_by_trim_middleware_are_restored(): void
    {
        $this->app->instance('env', 'local');
        config(['openai.api_key' => 'test-secret']);
        Http::fake(['api.openai.com/*' => Http::response('answer', 201)]);
        (new VoiceTestController)->session(Request::create('/', 'POST', ['sdp' => "v=0\ns=-"]));
        Http::assertSent(function ($request) {
            foreach ($request->data() as $part) {
                if (($part['name'] ?? null) === 'sdp') {
                    return $part['contents'] === "v=0\r\ns=-\r\n";
                }
            }
            return false;
        });
    }
}
