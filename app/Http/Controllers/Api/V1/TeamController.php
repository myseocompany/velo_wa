<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InviteTeamMemberRequest;
use App\Http\Requests\Api\UpdateTeamMemberRequest;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /** GET /api/v1/team/members — returns active users for assignment dropdowns */
    public function members(Request $request): JsonResponse
    {
        $members = User::where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_online', 'avatar_url']);

        return response()->json(['data' => $members]);
    }

    /** GET /api/v1/team — full list including inactive, for management UI */
    public function index(Request $request): JsonResponse
    {
        $members = User::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'is_online', 'avatar_url', 'max_concurrent_conversations', 'created_at', 'last_seen_at']);

        return response()->json(['data' => $members->map(fn (User $u) => [
            'id'                           => $u->id,
            'name'                         => $u->name,
            'email'                        => $u->email,
            'role'                         => $u->role->value,
            'role_label'                   => $u->role->label(),
            'is_active'                    => $u->is_active,
            'is_online'                    => $u->is_online,
            'avatar_url'                   => $u->avatar_url,
            'max_concurrent_conversations' => $u->max_concurrent_conversations,
            'created_at'                   => $u->created_at?->toIso8601String(),
            'last_seen_at'                 => $u->last_seen_at?->toIso8601String(),
        ])]);
    }

    /** POST /api/v1/team — invite a new team member */
    public function invite(InviteTeamMemberRequest $request): JsonResponse
    {
        $tenant = Tenant::find($request->user()->tenant_id);

        // Enforce plan limit
        $activeCount = User::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->count();

        if ($tenant->max_agents !== null && $activeCount >= $tenant->max_agents) {
            return response()->json([
                'message' => 'Has alcanzado el límite de agentes de tu plan.',
            ], 422);
        }

        // Prevent non-owner from assigning owner role
        if ($request->role === UserRole::Owner->value && ! $request->user()->isOwner()) {
            return response()->json(['message' => 'No tienes permiso para asignar el rol de propietario.'], 403);
        }

        $temporaryPassword = Str::password(12);

        $user = DB::transaction(function () use ($request, $tenant, $temporaryPassword) {
            return User::create([
                'tenant_id'                    => $tenant->id,
                'name'                         => $request->name,
                'email'                        => $request->email,
                'password'                     => Hash::make($temporaryPassword),
                'role'                         => $request->role,
                'is_active'                    => true,
                'max_concurrent_conversations' => $request->max_concurrent_conversations ?? 10,
            ]);
        });

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['role' => $user->role->value])
            ->log('team_member_invited');

        return response()->json([
            'data'              => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active'  => $user->is_active,
            ],
            'temporary_password' => $temporaryPassword,
            'message'           => "Miembro invitado. Contraseña temporal: {$temporaryPassword}",
        ], 201);
    }

    /** PATCH /api/v1/team/{user} — update role or max conversations */
    public function update(UpdateTeamMemberRequest $request, User $member): JsonResponse
    {
        $this->ensureSameTenant($request, $member);

        // Prevent demoting the only owner
        if (
            isset($request->role)
            && $member->role === UserRole::Owner
            && $request->role !== UserRole::Owner->value
        ) {
            $ownerCount = User::where('tenant_id', $member->tenant_id)
                ->where('role', UserRole::Owner->value)
                ->where('is_active', true)
                ->count();

            if ($ownerCount <= 1) {
                return response()->json(['message' => 'No puedes degradar al único propietario.'], 422);
            }
        }

        // Prevent non-owner from assigning owner role
        if (
            isset($request->role)
            && $request->role === UserRole::Owner->value
            && ! $request->user()->isOwner()
        ) {
            return response()->json(['message' => 'No tienes permiso para asignar el rol de propietario.'], 403);
        }

        $before = $member->only(['role', 'max_concurrent_conversations']);
        $member->update($request->validated());

        activity()
            ->causedBy($request->user())
            ->performedOn($member)
            ->withProperties(['before' => $before, 'after' => $request->validated()])
            ->log('team_member_updated');

        return response()->json([
            'data' => [
                'id'         => $member->id,
                'name'       => $member->name,
                'role'       => $member->role->value,
                'role_label' => $member->role->label(),
                'is_active'  => $member->is_active,
            ],
        ]);
    }

    /** PATCH /api/v1/team/{user}/deactivate — soft-deactivate a member */
    public function deactivate(Request $request, User $member): JsonResponse
    {
        $this->ensureSameTenant($request, $member);

        if (! $request->user()->canManageTeam()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($member->id === $request->user()->id) {
            return response()->json(['message' => 'No puedes desactivarte a ti mismo.'], 422);
        }

        // Prevent deactivating the only owner
        if ($member->role === UserRole::Owner) {
            $ownerCount = User::where('tenant_id', $member->tenant_id)
                ->where('role', UserRole::Owner->value)
                ->where('is_active', true)
                ->count();

            if ($ownerCount <= 1) {
                return response()->json(['message' => 'No puedes desactivar al único propietario.'], 422);
            }
        }

        $member->update(['is_active' => false]);

        activity()
            ->causedBy($request->user())
            ->performedOn($member)
            ->log('team_member_deactivated');

        return response()->json(['message' => 'Miembro desactivado correctamente.']);
    }

    /** PATCH /api/v1/team/{user}/reactivate */
    public function reactivate(Request $request, User $member): JsonResponse
    {
        $this->ensureSameTenant($request, $member);

        if (! $request->user()->canManageTeam()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $tenant = Tenant::find($request->user()->tenant_id);
        $activeCount = User::where('tenant_id', $tenant->id)->where('is_active', true)->count();

        if ($tenant->max_agents !== null && $activeCount >= $tenant->max_agents) {
            return response()->json(['message' => 'Has alcanzado el límite de agentes de tu plan.'], 422);
        }

        $member->update(['is_active' => true]);

        activity()
            ->causedBy($request->user())
            ->performedOn($member)
            ->log('team_member_reactivated');

        return response()->json(['message' => 'Miembro reactivado correctamente.']);
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
            ->get(['id', 'name', 'email', 'role', 'is_online', 'max_concurrent_conversations', 'avatar_url']);

        $data = $agents->map(function (User $agent) use ($counts) {
            $agentCounts = $counts->get($agent->id, collect());
            $open        = (int) ($agentCounts->firstWhere('status', ConversationStatus::Open->value)?->total ?? 0);
            $pending     = (int) ($agentCounts->firstWhere('status', ConversationStatus::Pending->value)?->total ?? 0);
            $total       = $open + $pending;

            return [
                'id'                           => $agent->id,
                'name'                         => $agent->name,
                'email'                        => $agent->email,
                'role'                         => $agent->role->value,
                'is_online'                    => $agent->is_online,
                'avatar_url'                   => $agent->avatar_url,
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

    private function ensureSameTenant(Request $request, User $member): void
    {
        abort_if(
            $member->tenant_id !== $request->user()->tenant_id,
            404
        );
    }
}
