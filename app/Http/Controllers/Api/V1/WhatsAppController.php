<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $line = $request->user()->tenant->getOrCreateDefaultLine();
        $response = app(WhatsAppLineController::class)->status($request, $line);
        $data = $response->getData(true);

        return response()->json([
            'status'       => $data['status'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'connected_at' => $data['connected_at'] ?? null,
            'instance_id'  => $data['instance_id'] ?? null,
            'line_id'      => $data['id'] ?? null,
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        $line = $request->user()->tenant->getOrCreateDefaultLine();

        return app(WhatsAppLineController::class)->connect($request, $line);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $line = $request->user()->tenant->getOrCreateDefaultLine();

        return app(WhatsAppLineController::class)->disconnect($request, $line);
    }

    public function healthLogs(Request $request): JsonResponse
    {
        $line = $request->user()->tenant->getOrCreateDefaultLine();

        return app(WhatsAppLineController::class)->healthLogs($request, $line);
    }
}
