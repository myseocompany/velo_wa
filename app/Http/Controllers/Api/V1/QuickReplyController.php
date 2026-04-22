<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuickReplyRequest;
use App\Http\Resources\QuickReplyResource;
use App\Models\QuickReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuickReplyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = QuickReply::query()->orderBy('shortcut');

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->where(function ($q) use ($lower): void {
                $q->whereRaw('LOWER(shortcut) LIKE ?', ['%' . $lower . '%'])
                    ->orWhereRaw('LOWER(title) LIKE ?', ['%' . $lower . '%'])
                    ->orWhereRaw('LOWER(body) LIKE ?', ['%' . $lower . '%']);
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return QuickReplyResource::collection($query->get());
    }

    public function store(QuickReplyRequest $request): JsonResponse
    {
        $isAutoReply = (bool) $request->input('is_auto_reply', false);

        if ($isAutoReply) {
            QuickReply::where('tenant_id', $request->user()->tenant_id)
                ->where('is_auto_reply', true)
                ->update(['is_auto_reply' => false]);
        }

        $quickReply = QuickReply::create([
            'tenant_id'     => $request->user()->tenant_id,
            'shortcut'      => $request->input('shortcut'),
            'title'         => $request->input('title'),
            'body'          => $request->input('body'),
            'has_variables' => str_contains($request->input('body'), '{{'),
            'category'      => $request->input('category', 'general'),
            'usage_count'   => 0,
            'is_auto_reply' => $isAutoReply,
        ]);

        return (new QuickReplyResource($quickReply))->response()->setStatusCode(201);
    }

    public function update(QuickReplyRequest $request, QuickReply $quickReply): QuickReplyResource
    {
        $isAutoReply = (bool) $request->input('is_auto_reply', $quickReply->is_auto_reply);

        if ($isAutoReply && ! $quickReply->is_auto_reply) {
            // Deactivate any other auto-reply for this tenant
            QuickReply::where('tenant_id', $quickReply->tenant_id)
                ->where('is_auto_reply', true)
                ->update(['is_auto_reply' => false]);
        }

        $quickReply->update([
            'shortcut'      => $request->input('shortcut'),
            'title'         => $request->input('title'),
            'body'          => $request->input('body'),
            'has_variables' => str_contains($request->input('body'), '{{'),
            'category'      => $request->input('category', $quickReply->category),
            'is_auto_reply' => $isAutoReply,
        ]);

        return new QuickReplyResource($quickReply);
    }

    public function destroy(QuickReply $quickReply): Response
    {
        $quickReply->delete();

        return response()->noContent();
    }
}
