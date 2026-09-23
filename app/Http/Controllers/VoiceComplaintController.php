<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Services\VoiceComplaint;

class VoiceComplaintController extends Controller
{
    public function phone(Request $request, VoiceComplaint $service)
    {
        $token = config('voice.submission_token');
        abort_unless(is_string($token) && strlen($token) >= 32 && hash_equals($token, (string)$request->bearerToken()), 401);
        $data = $request->validate(['session_id'=>'required|uuid','arguments'=>'required|array']);
        return response()->json($service->submit($data['session_id'], $data['arguments'], 'phone'));
    }
    public function browser(Request $request, VoiceComplaint $service)
    {
        Gate::authorize('user_management_access');
        $data = $request->validate(['session_id'=>'required|uuid','arguments'=>'required|array']);
        abort_unless($request->session()->has('voice_sessions.'.$data['session_id']), 403);
        return response()->json($service->submit($data['session_id'], $data['arguments'], 'admin'));
    }
}
