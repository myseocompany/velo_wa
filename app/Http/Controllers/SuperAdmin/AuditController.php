<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdminAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PlatformAdminAuditLog::with('admin:id,name,email')
            ->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(50)->through(fn (PlatformAdminAuditLog $log) => [
            'id'          => $log->id,
            'action'      => $log->action,
            'target_type' => $log->target_type,
            'target_id'   => $log->target_id,
            'ip_address'  => $log->ip_address,
            'metadata'    => $log->metadata,
            'admin'       => $log->admin ? ['id' => $log->admin->id, 'name' => $log->admin->name] : null,
            'created_at'  => $log->created_at?->toIso8601String(),
        ]);

        return Inertia::render('SuperAdmin/AuditLog', [
            'logs'    => $logs,
            'filters' => $request->only(['action', 'from', 'to']),
        ]);
    }
}
