<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VoiceComplaint
{
    public function submit(string $id, array $data, string $channel): array
    {
        Validator::make(['id' => $id] + $data, [
            'id' => 'required|uuid',
            'zentrum_origin' => 'required|boolean|accepted',
            'under_warranty' => 'required|boolean|accepted',
            'customer_confirmed' => 'required|boolean|accepted',
            'collection_sufficient' => 'required|boolean|accepted',
            'collection_assessment' => 'required|string|min:20|max:1000',
            'customer_name' => 'required|string|max:200',
            'contact' => 'required|string|max:200',
            'part' => 'required|string|max:300',
            'summary' => 'required|string|min:30|max:10000',
        ])->after(function ($validator) use ($data) {
            foreach (['customer_name','part','summary'] as $field) {
                $value = mb_strtolower(trim(is_string($data[$field] ?? null) ? $data[$field] : ''));
                if (in_array($value, ['', 'não indicado', 'não indicada', 'não sei', 'desconhecido', 'desconhecida', 'não confirmado', 'n/a', 'nenhum'])) {
                    $validator->errors()->add($field, 'Informação essencial em falta.');
                }
            }
            $contact = is_string($data['contact'] ?? null) ? trim($data['contact']) : '';
            $phone = preg_replace('/[\s().+\-]/', '', $contact);
            if (!filter_var($contact, FILTER_VALIDATE_EMAIL) && !preg_match('/^\d{7,15}$/', $phone)) {
                $validator->errors()->add('contact', 'Indique um telefone ou email utilizável.');
            }
        })->validate();
        $payload = array_intersect_key($data, array_flip(['zentrum_origin','under_warranty','customer_confirmed','collection_sufficient','collection_assessment','customer_name','contact','part','summary']));
        // A unique conversation ID claims the delivery once, including concurrent retries.
        $created = DB::table('voice_complaints')->insertOrIgnore([
            'id' => $id, 'channel' => $channel, 'status' => 'sending',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (!$created) {
            $status = DB::table('voice_complaints')->where('id', $id)->value('status');
            return ['sent' => $status === 'sent', 'reference' => $id, 'status' => $status, 'duplicate' => true];
        }
        $body = "Subtil desagrado recebido pelo atendimento de voz\nReferência: $id\nCanal: $channel\n\n";
        foreach (['customer_name'=>'Nome','contact'=>'Contacto','part'=>'Peça','summary'=>'Resumo'] as $key=>$label) $body .= "$label: {$payload[$key]}\n\n";
        $body .= "Origem Zentrum e garantia declaradas pelo cliente; sujeitas a validação pela equipa. Resumo confirmado pelo cliente.\n";
        try {
            // Do not use a log/array fallback and then claim that an email was sent.
            Mail::mailer('smtp')->raw($body, fn ($message) => $message
                ->to(config('voice.recipient'))->subject('Subtil desagrado · Zentrum · '.$id));
            DB::table('voice_complaints')->where('id', $id)->update(['status'=>'sent','sent_at'=>now(),'updated_at'=>now()]);
            return ['sent'=>true,'reference'=>$id,'status'=>'sent'];
        } catch (\Throwable $e) {
            // Delivery can be uncertain after an SMTP timeout. Do not resend automatically.
            DB::table('voice_complaints')->where('id', $id)->update(['status'=>'needs_review','updated_at'=>now()]);
            Log::error('Voice complaint delivery needs review', ['reference'=>$id,'exception'=>get_class($e)]);
            return ['sent'=>false,'reference'=>$id,'status'=>'needs_review'];
        }
    }
}
