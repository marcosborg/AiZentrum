<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\VoiceComplaint;
use Illuminate\Support\Facades\{DB, Mail, Gate};
use Illuminate\Validation\ValidationException;

class VoiceComplaintTest extends TestCase
{
    private string $id = '908dc2da-35f8-4a90-a8ea-0ebc846c175d';
    protected function setUp(): void {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','app.key'=>'base64:'.base64_encode(str_repeat('t',32)),'session.driver'=>'array','cache.default'=>'array','voice.submission_token'=>str_repeat('s',48)]);
        DB::purge('sqlite');
        (require database_path('migrations/2026_09_23_120000_create_voice_complaints_table.php'))->up();
    }
    private function payload(): array {
        return ['zentrum_origin'=>true,'under_warranty'=>true,'customer_confirmed'=>true,'customer_name'=>'Teste automático','contact'=>'Não indicado','part'=>'ABS','summary'=>'Reclamação fictícia para teste.'];
    }
    public function test_sends_to_fixed_recipient_and_retries_do_not_duplicate(): void {
        $mailer = \Mockery::mock();
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);
        $mailer->shouldReceive('raw')->once()->andReturnUsing(function ($body, $callback) {
            $this->assertStringContainsString('Reclamação fictícia', $body);
            $message = \Mockery::mock();
            $message->shouldReceive('to')->once()->with('geral@zentrum-group.com')->andReturnSelf();
            $message->shouldReceive('subject')->once()->with('Reclamação de voz Zentrum · '.$this->id)->andReturnSelf();
            $callback($message);
        });
        $service = new VoiceComplaint;
        $this->assertTrue($service->submit($this->id, $this->payload(), 'phone')['sent']);
        $this->assertTrue($service->submit($this->id, $this->payload(), 'phone')['duplicate']);
        $this->assertSame(1, DB::table('voice_complaints')->count());
    }
    public function test_ineligible_and_unconfirmed_cases_are_not_saved_or_sent(): void {
        Mail::shouldReceive('mailer')->never();
        foreach (['zentrum_origin','under_warranty','customer_confirmed'] as $field) {
            try {
                (new VoiceComplaint)->submit($this->id, array_replace($this->payload(), [$field=>false]), 'phone');
                $this->fail('Ineligible submission accepted');
            } catch (ValidationException $e) { $this->assertArrayHasKey($field, $e->errors()); }
        }
        $this->assertSame(0, DB::table('voice_complaints')->count());
    }
    public function test_smtp_failure_is_retained_and_never_reported_sent_or_retried(): void {
        $mailer = \Mockery::mock();
        Mail::shouldReceive('mailer')->once()->andReturn($mailer);
        $mailer->shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp timeout'));
        $service = new VoiceComplaint;
        $result = $service->submit($this->id, $this->payload(), 'phone');
        $this->assertFalse($result['sent']);
        $this->assertSame('needs_review', $result['status']);
        $this->assertFalse($service->submit($this->id, $this->payload(), 'phone')['sent']);
    }
    public function test_phone_requires_secret_and_admin_endpoint_requires_login(): void {
        $body = ['session_id'=>$this->id,'arguments'=>$this->payload()];
        $this->postJson('/api/voice/complaints', $body)->assertUnauthorized();
        $this->withToken('wrong')->postJson('/api/voice/complaints', $body)->assertUnauthorized();
        $this->postJson('/admin/voice-test/complaint', $body)->assertUnauthorized();
        $this->assertSame(0, DB::table('voice_complaints')->count());
    }
    public function test_authenticated_phone_still_requires_eligibility(): void {
        $this->withToken(str_repeat('s',48))->postJson('/api/voice/complaints', [
            'session_id'=>$this->id,'arguments'=>array_replace($this->payload(), ['under_warranty'=>false]),
        ])->assertUnprocessable();
    }
}
