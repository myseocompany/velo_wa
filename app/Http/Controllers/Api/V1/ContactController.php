<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::query()->with('assignee');

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('push_name', 'ilike', '%' . $search . '%')
                    ->orWhere('phone', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%');
            });
        }

        // Tag filter: ?tags[]=vip&tags[]=premium OR ?tags=vip,premium
        $tags = $request->input('tags');
        if ($tags) {
            $tagList = is_array($tags) ? $tags : explode(',', $tags);
            $tagList = array_filter(array_map('trim', $tagList));
            foreach ($tagList as $tag) {
                $query->whereRaw('tags @> ?::jsonb', [json_encode([$tag])]);
            }
        }

        // Assignee filter
        $assigned = $request->string('assigned')->toString();
        if ($assigned === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($assigned === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assigned !== '' && $assigned !== 'all') {
            $query->where('assigned_to', $assigned)
                ->whereHas('assignee', fn ($q) => $q->where('tenant_id', $request->user()->tenant_id));
        }

        $sort      = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';
        $sortable  = ['name', 'created_at', 'last_contact_at'];
        $sortBy    = in_array($sort, $sortable, true) ? $sort : 'last_contact_at';

        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        return ContactResource::collection(
            $query->orderBy($sortBy, $direction)->paginate($perPage)
        );
    }

    public function show(Contact $contact): ContactResource
    {
        $contact->load(['assignee', 'conversations']);

        return new ContactResource($contact);
    }

    public function update(ContactRequest $request, Contact $contact): ContactResource
    {
        $contact->update($request->only([
            'name', 'email', 'company', 'notes', 'tags', 'assigned_to',
        ]));

        return new ContactResource($contact->fresh('assignee'));
    }

    public function destroy(Contact $contact): Response
    {
        $contact->delete();

        return response()->noContent();
    }

    /** All unique tags used across contacts in this tenant. */
    public function tags(): \Illuminate\Http\JsonResponse
    {
        $tags = Contact::query()
            ->selectRaw("DISTINCT jsonb_array_elements_text(tags) AS tag")
            ->whereNotNull('tags')
            ->where('tags', '!=', '[]')
            ->orderBy('tag')
            ->pluck('tag');

        return response()->json(['data' => $tags]);
    }
}
