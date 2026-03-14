<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /** GET /api/v1/activity — paginated activity log for the tenant */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // Get all user IDs in this tenant for filtering causer
        $tenantUserIds = User::where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();

        $query = Activity::query()
            ->where(function ($q) use ($tenantUserIds) {
                $q->whereIn('causer_id', $tenantUserIds)
                  ->where('causer_type', 'App\\Models\\User');
            })
            ->with('causer:id,name,avatar_url')
            ->latest()
            ->limit(200);

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $activities = $query->paginate(50);

        return response()->json([
            'data' => $activities->map(fn (Activity $a) => [
                'id'          => $a->id,
                'description' => $a->description,
                'log_name'    => $a->log_name,
                'properties'  => $a->properties,
                'causer'      => $a->causer ? [
                    'id'         => $a->causer->id,
                    'name'       => $a->causer->name,
                    'avatar_url' => $a->causer->avatar_url,
                ] : null,
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'total'        => $activities->total(),
                'current_page' => $activities->currentPage(),
                'last_page'    => $activities->lastPage(),
            ],
        ]);
    }
}
