<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        $tags = Tag::query()->orderBy('name')->get();

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:80'],
            'color'                => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'exclude_from_metrics' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($data['name']);

        $request->validate([
            'name' => [Rule::unique('tags', 'slug')->where('tenant_id', $tenantId)->ignore($slug, 'slug')],
        ]);

        $tag = Tag::create([
            'tenant_id'            => $tenantId,
            'name'                 => $data['name'],
            'slug'                 => $slug,
            'color'                => $data['color'] ?? '#6366f1',
            'exclude_from_metrics' => $data['exclude_from_metrics'] ?? false,
        ]);

        return response()->json(['data' => $tag], 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;

        $data = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:80'],
            'color'                => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'exclude_from_metrics' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $newSlug = Str::slug($data['name']);

            $exists = Tag::where('tenant_id', $tenantId)
                ->where('slug', $newSlug)
                ->where('id', '!=', $tag->id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Ya existe una etiqueta con ese nombre.'], 422);
            }

            $data['slug'] = $newSlug;
        }

        $tag->update($data);

        return response()->json(['data' => $tag]);
    }

    public function destroy(Tag $tag): Response
    {
        $tag->delete();

        return response()->noContent();
    }
}
