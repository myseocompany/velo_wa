<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookableUnitRequest;
use App\Http\Resources\BookableUnitResource;
use App\Models\BookableUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookableUnitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BookableUnit::query()->orderBy('name');

        $type = $request->string('type')->toString();
        if ($type !== '') {
            $query->ofType($type);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $service = $request->string('service')->toString();
        if ($service !== '') {
            $query->whereJsonContains('settings->services', $service);
        }

        return BookableUnitResource::collection($query->get());
    }

    public function store(BookableUnitRequest $request): JsonResponse
    {
        $unit = BookableUnit::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$request->safe()->only(['type', 'name', 'capacity', 'user_id', 'settings', 'is_active']),
        ]);

        return response()->json(['data' => new BookableUnitResource($unit)], 201);
    }

    public function show(BookableUnit $unit): JsonResponse
    {
        return response()->json(['data' => new BookableUnitResource($unit)]);
    }

    public function update(BookableUnitRequest $request, BookableUnit $unit): JsonResponse
    {
        $unit->update($request->safe()->only(['type', 'name', 'capacity', 'user_id', 'settings', 'is_active']));

        return response()->json(['data' => new BookableUnitResource($unit->fresh())]);
    }

    public function destroy(BookableUnit $unit): Response
    {
        $unit->delete();

        return response()->noContent();
    }
}
