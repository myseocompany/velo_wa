<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\WaStatus;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $totalTenants   = Tenant::count();
        $waConnected    = Tenant::where('wa_status', WaStatus::Connected->value)->count();
        $totalAgents    = User::count();
        $totalContacts  = Contact::withoutGlobalScope('tenant')->count();
        $totalConversations = Conversation::withoutGlobalScope('tenant')->count();

        $recentTenants = Tenant::withCount(['users', 'contacts', 'conversations'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Tenant $t) => [
                'id'                => $t->id,
                'name'              => $t->name,
                'slug'              => $t->slug,
                'wa_status'         => $t->wa_status->value,
                'wa_phone'          => $t->wa_phone,
                'users_count'       => $t->users_count,
                'contacts_count'    => $t->contacts_count,
                'conversations_count' => $t->conversations_count,
                'created_at'        => $t->created_at?->toIso8601String(),
            ]);

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => [
                'total_tenants'        => $totalTenants,
                'wa_connected'         => $waConnected,
                'total_agents'         => $totalAgents,
                'total_contacts'       => $totalContacts,
                'total_conversations'  => $totalConversations,
            ],
            'recent_tenants' => $recentTenants,
        ]);
    }
}
