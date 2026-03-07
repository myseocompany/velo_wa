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
        $range = $request->string('range', 'horas')->toString();

        $data = $action->handle($request->user(), $range);

        return Inertia::render('Dashboard', $data);
    }
}
