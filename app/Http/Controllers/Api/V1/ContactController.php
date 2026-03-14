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
        $driver = DB::connection()->getDriverName();

        $search = $this->sanitizeSearchTerm(trim($request->string('search')->toString()));
        if ($search !== '') {
            $phoneSearch = preg_replace('/[\s\+\-\(\)]+/', '', $search);
            $operator    = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($operator, $search, $phoneSearch): void {
                $q->where('name', $operator, '%' . $search . '%')
                    ->orWhere('push_name', $operator, '%' . $search . '%')
                    ->orWhere('email', $operator, '%' . $search . '%');

                if ($phoneSearch !== '') {
                    $q->orWhere('phone', $operator, '%' . $phoneSearch . '%');
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

    private function sanitizeSearchTerm(string $search): string
    {
        if ($search === '') {
            return $search;
        }

        if (! mb_check_encoding($search, 'UTF-8')) {
            $search = mb_convert_encoding($search, 'UTF-8', ['UTF-8', 'ISO-8859-1', 'Windows-1252']);
        }

        $sanitized = iconv('UTF-8', 'UTF-8//IGNORE', $search);

        return $sanitized === false ? $search : $sanitized;
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $contact = Contact::create([
            'phone'       => $request->string('phone')->toString(),
            'name'        => $request->input('name'),
            'email'       => $request->input('email'),
            'company'     => $request->input('company'),
            'notes'       => $request->input('notes'),
            'tags'        => $request->input('tags', []),
            'custom_fields' => $request->input('custom_fields', []),
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
            if (! $target->wa_id            && $contact->wa_id)           $updates['wa_id']           = $contact->wa_id;
            if (! $target->push_name        && $contact->push_name)       $updates['push_name']       = $contact->push_name;
            if (! $target->profile_pic_url  && $contact->profile_pic_url) $updates['profile_pic_url'] = $contact->profile_pic_url;
            if (! $target->email            && $contact->email)           $updates['email']           = $contact->email;
            if (! $target->company          && $contact->company)         $updates['company']         = $contact->company;
            if (! $target->name             && $contact->name)            $updates['name']            = $contact->name;
            if (! $target->notes            && $contact->notes)           $updates['notes']           = $contact->notes;
            // Keep the earliest first_contact_at
            if ($contact->first_contact_at && (! $target->first_contact_at || $contact->first_contact_at < $target->first_contact_at)) {
                $updates['first_contact_at'] = $contact->first_contact_at;
            }
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
            'name', 'email', 'company', 'notes', 'tags', 'custom_fields', 'assigned_to',
        ]));

        return new ContactResource($contact->fresh('assignee'));
    }

    public function destroy(Contact $contact): Response
    {
        $contact->delete();

        return response()->noContent();
    }

    /**
     * Contacts grouped by duplicate normalized phone within the tenant.
     * Returns groups where more than one contact shares the same digits-only phone.
     */
    public function duplicates(): JsonResponse
    {
        $tenantId = request()->user()->tenant_id;

        $duplicatePhones = DB::table('contacts')
            ->selectRaw("REGEXP_REPLACE(phone, '[^0-9]', '', 'g') AS np")
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupByRaw("REGEXP_REPLACE(phone, '[^0-9]', '', 'g')")
            ->havingRaw('COUNT(*) > 1')
            ->pluck('np');

        if ($duplicatePhones->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $contacts = Contact::with('assignee')
            ->whereIn(DB::raw("REGEXP_REPLACE(phone, '[^0-9]', '', 'g')"), $duplicatePhones->toArray())
            ->orderBy('created_at')
            ->get();

        $groups = $contacts
            ->groupBy(fn ($c) => preg_replace('/[^0-9]/', '', $c->phone ?? ''))
            ->map(fn ($group, $phone) => [
                'normalized_phone' => $phone,
                'contacts'         => ContactResource::collection($group->values()),
            ])
            ->values();

        return response()->json(['data' => $groups]);
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
