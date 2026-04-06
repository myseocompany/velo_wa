<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\MoveOrderToStatus;
use App\Actions\Loyalty\AwardLoyaltyPointsForOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderRequest;
use App\Http\Requests\Api\UpdateOrderRequest;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()->with(['contact', 'assignee']);

        $status = $request->string('status')->toString();
        if ($status !== '' && OrderStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        $contactId = $request->string('contact_id')->toString();
        if ($contactId !== '') {
            $query->where('contact_id', $contactId);
        }

        $conversationId = $request->string('conversation_id')->toString();
        if ($conversationId !== '') {
            $query->where('conversation_id', $conversationId);
        }

        $assignedTo = $request->string('assigned_to')->toString();
        if ($assignedTo === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignedTo !== '') {
            $query->where('assigned_to', $assignedTo);
        }

        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function ($q) use ($operator, $search): void {
                $q->where('code', $operator, '%' . $search . '%')
                    ->orWhere('notes', $operator, '%' . $search . '%');
            });
        }

        $cases = collect(OrderStatus::cases())
            ->map(fn (OrderStatus $s, int $i) => "WHEN ? THEN {$i}")
            ->implode(' ');
        $bindings = array_column(OrderStatus::cases(), 'value');

        $perPage = max(1, min((int) $request->integer('per_page', 100), 300));

        $orders = $query
            ->orderByRaw("CASE status {$cases} ELSE 999 END", $bindings)
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return OrderResource::collection($orders);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $status = OrderStatus::tryFrom($request->input('status', OrderStatus::New->value)) ?? OrderStatus::New;

        $order = Order::create([
            'tenant_id' => $request->user()->tenant_id,
            'contact_id' => $request->input('contact_id'),
            'conversation_id' => $request->input('conversation_id'),
            'assigned_to' => $request->input('assigned_to'),
            'code' => $this->generateOrderCode(),
            'status' => $status,
            'total' => $request->input('total'),
            'currency' => $request->input('currency', 'COP'),
            'items' => $request->input('items'),
            'notes' => $request->input('notes'),
            'new_at' => now(),
        ]);

        return response()->json(['data' => new OrderResource($order->load(['contact', 'assignee']))], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => new OrderResource($order->load(['contact', 'assignee'])),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $order->update([
            'contact_id' => $request->input('contact_id', $order->contact_id),
            'conversation_id' => $request->input('conversation_id', $order->conversation_id),
            'assigned_to' => $request->input('assigned_to', $order->assigned_to),
            'total' => $request->has('total') ? $request->input('total') : $order->total,
            'currency' => $request->input('currency', $order->currency),
            'items' => $request->has('items') ? $request->input('items') : $order->items,
            'notes' => $request->has('notes') ? $request->input('notes') : $order->notes,
        ]);

        return response()->json([
            'data' => new OrderResource($order->fresh(['contact', 'assignee'])),
        ]);
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
        MoveOrderToStatus $action,
        AwardLoyaltyPointsForOrder $awardPoints
    ): JsonResponse {
        $previousStatus = $order->status;
        $updated = $action->handle($order, $request->status());

        if ($previousStatus !== OrderStatus::Delivered && $updated->status === OrderStatus::Delivered) {
            $awardPoints->handle($updated);
        }

        return response()->json([
            'data' => new OrderResource($updated->load(['contact', 'assignee'])),
        ]);
    }

    public function destroy(Order $order): Response
    {
        $order->delete();

        return response()->noContent();
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'PED-' . strtoupper(Str::random(6));
        } while (Order::query()->where('code', $code)->exists());

        return $code;
    }
}
