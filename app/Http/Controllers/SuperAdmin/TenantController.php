<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\WaStatus;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\PipelineDeal;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /** GET /superadmin/tenants */
    public function index(Request $request): Response
    {
        $query = Tenant::withCount(['users', 'contacts', 'conversations'])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%")
                  ->orWhere('wa_phone', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('wa_status')) {
            $query->where('wa_status', $request->wa_status);
        }

        $tenants = $query->paginate(20)->through(fn (Tenant $t) => [
            'id'                   => $t->id,
            'name'                 => $t->name,
            'slug'                 => $t->slug,
            'wa_status'            => $t->wa_status->value,
            'wa_phone'             => $t->wa_phone,
            'max_agents'           => $t->max_agents,
            'max_contacts'         => $t->max_contacts,
            'users_count'          => $t->users_count,
            'contacts_count'       => $t->contacts_count,
            'conversations_count'  => $t->conversations_count,
            'created_at'           => $t->created_at?->toIso8601String(),
        ]);

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'wa_status']),
        ]);
    }

    /** GET /superadmin/tenants/{tenant} */
    public function show(Tenant $tenant): Response
    {
        $usersCount         = User::where('tenant_id', $tenant->id)->count();
        $activeUsersCount   = User::where('tenant_id', $tenant->id)->where('is_active', true)->count();
        $contactsCount      = Contact::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
        $conversationsCount = Conversation::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
        $openConversations  = Conversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'open')
            ->count();
        $dealsCount         = PipelineDeal::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();

        $members = User::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'is_online', 'last_seen_at', 'created_at']);

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => [
                'id'                   => $tenant->id,
                'name'                 => $tenant->name,
                'slug'                 => $tenant->slug,
                'wa_status'            => $tenant->wa_status->value,
                'wa_phone'             => $tenant->wa_phone,
                'wa_instance_id'       => $tenant->wa_instance_id,
                'wa_connected_at'      => $tenant->wa_connected_at?->toIso8601String(),
                'timezone'             => $tenant->timezone,
                'max_agents'           => $tenant->max_agents,
                'max_contacts'         => $tenant->max_contacts,
                'auto_close_hours'     => $tenant->auto_close_hours,
                'created_at'           => $tenant->created_at?->toIso8601String(),
            ],
            'stats' => [
                'users_count'          => $usersCount,
                'active_users_count'   => $activeUsersCount,
                'contacts_count'       => $contactsCount,
                'conversations_count'  => $conversationsCount,
                'open_conversations'   => $openConversations,
                'deals_count'          => $dealsCount,
            ],
            'members' => $members->map(fn (User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'role'        => $u->role->value,
                'is_active'   => $u->is_active,
                'is_online'   => $u->is_online,
                'last_seen_at' => $u->last_seen_at?->toIso8601String(),
                'created_at'  => $u->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** PATCH /superadmin/tenants/{tenant}/plan */
    public function updatePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'max_agents'   => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_contacts' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenant->update($validated);

        PlatformAudit::log(
            auth('platform')->user(),
            'update_plan',
            Tenant::class,
            $tenant->id,
            $validated,
            $request
        );

        return back()->with('success', 'Límites del plan actualizados.');
    }

    /** POST /superadmin/tenants/{tenant}/impersonate */
    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        $owner = User::where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->where('is_active', true)
            ->first();

        if (! $owner) {
            return back()->withErrors(['impersonate' => 'Este tenant no tiene un propietario activo.']);
        }

        // Store impersonation in session
        $request->session()->put('impersonating_user_id', $owner->id);
        $request->session()->put('impersonating_tenant_id', $tenant->id);
        $request->session()->put('impersonating_admin_id', auth('platform')->id());

        // Log before granting access
        PlatformAudit::log(
            auth('platform')->user(),
            'impersonate',
            Tenant::class,
            $tenant->id,
            ['owner_id' => $owner->id, 'owner_email' => $owner->email],
            $request
        );

        // Login as the owner on the web guard
        auth('web')->loginUsingId($owner->id);

        return redirect('/dashboard');
    }

    /** POST /superadmin/tenants/{tenant}/wa/disconnect */
    public function disconnectWa(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['wa_status' => WaStatus::Disconnected]);

        PlatformAudit::log(
            auth('platform')->user(),
            'force_wa_disconnect',
            Tenant::class,
            $tenant->id,
            ['previous_status' => $tenant->getOriginal('wa_status')],
            $request
        );

        return response()->json(['message' => 'Instancia de WhatsApp desconectada.']);
    }
}
