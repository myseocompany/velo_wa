<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoyaltyEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoyaltyAdjustRequest;
use App\Models\Contact;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    public function account(Contact $contact): JsonResponse
    {
        $account = LoyaltyAccount::query()->firstOrCreate(
            ['tenant_id' => $contact->tenant_id, 'contact_id' => $contact->id],
            ['points_balance' => 0, 'total_earned' => 0, 'total_redeemed' => 0]
        );

        return response()->json([
            'data' => [
                'id' => $account->id,
                'contact_id' => $account->contact_id,
                'points_balance' => $account->points_balance,
                'total_earned' => $account->total_earned,
                'total_redeemed' => $account->total_redeemed,
            ],
        ]);
    }

    public function events(Contact $contact, Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $events = LoyaltyEvent::query()
            ->where('tenant_id', $contact->tenant_id)
            ->where('contact_id', $contact->id)
            ->with('order')
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json(['data' => $events->items(), 'meta' => [
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
        ]]);
    }

    public function adjust(Contact $contact, LoyaltyAdjustRequest $request): JsonResponse
    {
        $user = $request->user();
        $points = (int) $request->integer('points');
        $description = $request->input('description');

        $account = DB::transaction(function () use ($contact, $points, $description, $user) {
            $account = LoyaltyAccount::query()->firstOrCreate(
                ['tenant_id' => $contact->tenant_id, 'contact_id' => $contact->id],
                ['points_balance' => 0, 'total_earned' => 0, 'total_redeemed' => 0]
            );

            $nextBalance = $account->points_balance + $points;
            if ($nextBalance < 0) {
                abort(422, 'Puntos insuficientes para este canje.');
            }

            $account->update(['points_balance' => $nextBalance]);
            if ($points > 0) {
                $account->increment('total_earned', $points);
            } else {
                $account->increment('total_redeemed', abs($points));
            }

            LoyaltyEvent::create([
                'tenant_id' => $contact->tenant_id,
                'loyalty_account_id' => $account->id,
                'contact_id' => $contact->id,
                'type' => $points > 0 ? LoyaltyEventType::ManualAdjustment : LoyaltyEventType::Redemption,
                'points' => $points,
                'description' => $description,
                'created_by' => $user->id,
            ]);

            return $account->fresh();
        });

        return response()->json([
            'data' => [
                'id' => $account->id,
                'contact_id' => $account->contact_id,
                'points_balance' => $account->points_balance,
                'total_earned' => $account->total_earned,
                'total_redeemed' => $account->total_redeemed,
            ],
        ]);
    }
}

