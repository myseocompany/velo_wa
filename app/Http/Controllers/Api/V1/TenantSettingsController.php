<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TenantSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSettingsController extends Controller
{
    /** GET /api/v1/tenant/settings */
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        return response()->json([
            'data' => [
                'timezone'         => $tenant->timezone,
                'auto_close_hours' => $tenant->auto_close_hours,
                'business_hours'   => $tenant->business_hours ?? $this->defaultBusinessHours(),
                'max_agents'       => $tenant->max_agents,
                'max_contacts'     => $tenant->max_contacts,
            ],
        ]);
    }

    /** PATCH /api/v1/tenant/settings */
    public function update(TenantSettingsRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tenant->update($request->validated());

        activity()
            ->causedBy($request->user())
            ->performedOn($tenant)
            ->withProperties($request->validated())
            ->log('tenant_settings_updated');

        return response()->json([
            'message' => 'Configuración actualizada correctamente.',
            'data'    => [
                'timezone'         => $tenant->timezone,
                'auto_close_hours' => $tenant->auto_close_hours,
                'business_hours'   => $tenant->business_hours,
            ],
        ]);
    }

    private function defaultBusinessHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return collect($days)->mapWithKeys(fn (string $day) => [
            $day => [
                'enabled' => ! in_array($day, ['saturday', 'sunday']),
                'start'   => '09:00',
                'end'     => '18:00',
            ],
        ])->toArray();
    }
}
