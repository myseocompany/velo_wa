<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    /** Returns active users in the same tenant (for assignment dropdowns). */
    public function members(Request $request): JsonResponse
    {
        $members = User::where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_online']);

        return response()->json(['data' => $members]);
    }

    /**
     * GET /api/v1/team/workload
     * Returns each active agent with their current conversation load.
     */
    public function workload(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $counts = Conversation::withoutGlobalScope('tenant')
            ->select('assigned_to', 'status', DB::raw('count(*) as total'))
            ->where('tenant_id', $tenantId)
            ->whereNotNull('assigned_to')
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->groupBy('assigned_to', 'status')
            ->get()
            ->groupBy('assigned_to');

        $agents = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_online', 'max_concurrent_conversations']);

        $data = $agents->map(function (User $agent) use ($counts) {
            $agentCounts  = $counts->get($agent->id, collect());
            $open         = (int) ($agentCounts->firstWhere('status', ConversationStatus::Open->value)?->total ?? 0);
            $pending      = (int) ($agentCounts->firstWhere('status', ConversationStatus::Pending->value)?->total ?? 0);
            $total        = $open + $pending;

            return [
                'id'                           => $agent->id,
                'name'                         => $agent->name,
                'email'                        => $agent->email,
                'role'                         => $agent->role->value,
                'is_online'                    => $agent->is_online,
                'max_concurrent_conversations' => $agent->max_concurrent_conversations,
                'open_conversations'           => $open,
                'pending_conversations'        => $pending,
                'active_conversations'         => $total,
                'capacity_pct'                 => $agent->max_concurrent_conversations > 0
                    ? min(100, (int) round($total / $agent->max_concurrent_conversations * 100))
                    : null,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
