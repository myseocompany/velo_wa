<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContactSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactRequest;
use App\Http\Requests\Api\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::query()->with('assignee');

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $phoneSearch = preg_replace('/[\s\+\-\(\)]+/', '', $search);

            $query->where(function ($q) use ($search, $phoneSearch): void {
                $q->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('push_name', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%');

                if ($phoneSearch !== '') {
                    $q->orWhere('phone', 'ilike', '%' . $phoneSearch . '%');
                }
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

    public function store(StoreContactRequest $request): ContactResource
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $request->string('phone')->toString());

        $contact = Contact::create([
            'phone'       => $phone,
            'name'        => $request->input('name'),
            'email'       => $request->input('email'),
            'company'     => $request->input('company'),
            'notes'       => $request->input('notes'),
            'tags'        => $request->input('tags', []),
            'assigned_to' => $request->input('assigned_to'),
            'source'      => ContactSource::Manual,
        ]);

        return new ContactResource($contact->fresh('assignee'));
    }

    /** Merge $source into $target: transfer relationships, merge tags, soft-delete source. */
    public function merge(Contact $contact, Request $request): ContactResource
    {
        $request->validate([
            'merge_into_id' => ['required', 'uuid', 'exists:contacts,id'],
        ]);

        $target = Contact::findOrFail($request->input('merge_into_id'));

        // Ensure both contacts belong to same tenant
        if ($contact->tenant_id !== $target->tenant_id || $target->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        DB::transaction(function () use ($contact, $target): void {
            // Transfer conversations
            $contact->conversations()->update(['contact_id' => $target->id]);

            // Transfer deals
            $contact->deals()->update(['contact_id' => $target->id]);

            // Merge tags (union, deduplicated)
            $mergedTags = array_values(array_unique(array_merge(
                $target->tags ?? [],
                $contact->tags ?? [],
            )));
            $target->update(['tags' => $mergedTags]);

            // Carry over fields missing from target
            $updates = [];
            if (! $target->email    && $contact->email)    $updates['email']    = $contact->email;
            if (! $target->company  && $contact->company)  $updates['company']  = $contact->company;
            if (! $target->name     && $contact->name)     $updates['name']     = $contact->name;
            if (! $target->notes    && $contact->notes)    $updates['notes']    = $contact->notes;
            if ($updates) $target->update($updates);

            // Soft-delete the source
            $contact->delete();
        });

        return new ContactResource($target->fresh(['assignee', 'conversations']));
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
