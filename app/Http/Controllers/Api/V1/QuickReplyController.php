<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuickReplyRequest;
use App\Http\Resources\QuickReplyResource;
use App\Models\QuickReply;
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
            $query->where(function ($q) use ($search): void {
                $q->where('shortcut', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return QuickReplyResource::collection($query->get());
    }

    public function store(QuickReplyRequest $request): QuickReplyResource
    {
        $quickReply = QuickReply::create([
            'tenant_id'     => $request->user()->tenant_id,
            'shortcut'      => $request->input('shortcut'),
            'title'         => $request->input('title'),
            'body'          => $request->input('body'),
            'has_variables' => str_contains($request->input('body'), '{{'),
            'category'      => $request->input('category'),
            'usage_count'   => 0,
        ]);

        return new QuickReplyResource($quickReply);
    }

    public function update(QuickReplyRequest $request, QuickReply $quickReply): QuickReplyResource
    {
        $quickReply->update([
            'shortcut'      => $request->input('shortcut'),
            'title'         => $request->input('title'),
            'body'          => $request->input('body'),
            'has_variables' => str_contains($request->input('body'), '{{'),
            'category'      => $request->input('category'),
        ]);

        return new QuickReplyResource($quickReply);
    }

    public function destroy(QuickReply $quickReply): Response
    {
        $quickReply->delete();

        return response()->noContent();
    }
}
