<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
