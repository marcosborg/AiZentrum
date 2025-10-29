<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Log;
use App\Models\LogMessage;


class LogHistoryController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('log_history_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $logs = Log::orderBy('id', 'desc')->limit(100)->get();

        return view('admin.logHistories.index', compact('logs'));
    }

    public function history($log_id)
    {
        $logMessages = LogMessage::where('log_id', $log_id)->get();

        return view('admin.logHistories.history', compact('logMessages'));
    }

}
