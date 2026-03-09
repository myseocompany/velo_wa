<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetDashboardStats $action): Response
    {
        $range         = $request->string('range', 'horas')->toString();
        $businessHours = $request->boolean('business_hours', false);

        $data = $action->handle($request->user(), $range, $businessHours);

        return Inertia::render('Dashboard', $data);
    }
}
