<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;

class AdminAuditController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = AdminAuditLog::query()->with('actor:id,name,email')->latest('id')->paginate(50);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AdminAuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->actor?->email,
                'action' => $log->action,
                'subject' => $log->subject_type ? "{$log->subject_type} #{$log->subject_id}" : null,
                'details' => $log->details ?? [],
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage()],
        ]);
    }
}
