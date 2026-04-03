<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuCategoryResource;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\MenuFormatterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function __construct(private readonly MenuFormatterService $formatter) {}

    // ─── Categories ───────────────────────────────────────────────────────────

    public function indexCategories(Request $request): AnonymousResourceCollection
    {
        $categories = MenuCategory::query()
            ->with('items')
            ->orderBy('sort_order')
            ->get();

        return MenuCategoryResource::collection($categories);
    }

    public function storeCategory(Request $request): MenuCategoryResource
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $maxOrder = MenuCategory::max('sort_order') ?? 0;

        $category = MenuCategory::create([
            ...$data,
            'tenant_id'  => $request->user()->tenant_id,
            'sort_order' => $maxOrder + 1,
        ]);

        return new MenuCategoryResource($category->load('items'));
    }

    public function updateCategory(Request $request, MenuCategory $menuCategory): MenuCategoryResource
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $menuCategory->update($data);

        return new MenuCategoryResource($menuCategory->fresh('items'));
    }

    public function destroyCategory(MenuCategory $menuCategory): Response
    {
        $menuCategory->delete();
        return response()->noContent();
    }

    public function reorderCategories(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['uuid'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $index => $id) {
                MenuCategory::withoutGlobalScope('tenant')
                    ->where('id', $id)
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->update(['sort_order' => $index]);
            }
        });

        return response()->json(['message' => 'ok']);
    }

    // ─── Items ────────────────────────────────────────────────────────────────

    public function indexItems(Request $request): AnonymousResourceCollection
    {
        $query = MenuItem::query()->orderBy('sort_order');

        if ($request->filled('category_id')) {
            $query->where('menu_category_id', $request->string('category_id')->toString());
        }

        return MenuItemResource::collection($query->get());
    }

    public function storeItem(Request $request): MenuItemResource
    {
        $data = $request->validate([
            'menu_category_id' => ['required', 'uuid', 'exists:menu_categories,id'],
            'name'             => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string'],
            'price'            => ['required', 'numeric', 'min:0'],
            'currency'         => ['nullable', 'string', 'size:3'],
            'is_available'     => ['boolean'],
        ]);

        $maxOrder = MenuItem::where('menu_category_id', $data['menu_category_id'])->max('sort_order') ?? 0;

        $item = MenuItem::create([
            ...$data,
            'tenant_id'  => $request->user()->tenant_id,
            'sort_order' => $maxOrder + 1,
        ]);

        return new MenuItemResource($item);
    }

    public function updateItem(Request $request, MenuItem $menuItem): MenuItemResource
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:200'],
            'description'  => ['nullable', 'string'],
            'price'        => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'is_available' => ['boolean'],
        ]);

        $menuItem->update($data);

        return new MenuItemResource($menuItem->fresh());
    }

    public function destroyItem(MenuItem $menuItem): Response
    {
        $menuItem->delete();
        return response()->noContent();
    }

    public function toggleItem(MenuItem $menuItem): MenuItemResource
    {
        $menuItem->update(['is_available' => ! $menuItem->is_available]);
        return new MenuItemResource($menuItem->fresh());
    }

    public function reorderItems(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['uuid'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $index => $id) {
                MenuItem::withoutGlobalScope('tenant')
                    ->where('id', $id)
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->update(['sort_order' => $index]);
            }
        });

        return response()->json(['message' => 'ok']);
    }

    // ─── Preview & Test ───────────────────────────────────────────────────────

    public function preview(Request $request): JsonResponse
    {
        $tenant   = $request->user()->tenant;
        $messages = $this->formatter->format($tenant);

        return response()->json(['messages' => $messages]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant->isConnected()) {
            return response()->json(['message' => 'WhatsApp no está conectado.'], 422);
        }

        $owner = $tenant->users()->where('role', 'owner')->first();

        if (! $owner?->phone ?? null) {
            // Fallback: use wa_phone of the instance
            $phone = $tenant->wa_phone;
        } else {
            $phone = $owner->phone ?? $tenant->wa_phone;
        }

        if (! $phone) {
            return response()->json(['message' => 'No se encontró un número de destino para la prueba.'], 422);
        }

        $messages = $this->formatter->format($tenant);

        foreach ($messages as $body) {
            app(\App\Services\WhatsAppClientService::class)->sendText(
                $tenant->wa_instance_id,
                $phone,
                $body,
            );
        }

        return response()->json(['message' => 'Menú de prueba enviado.', 'chunks' => count($messages)]);
    }
}
