<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::query()->with('assignee');

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('push_name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $sort = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';
        $sortable = ['name', 'created_at', 'last_contact_at'];
        $sortBy = in_array($sort, $sortable, true) ? $sort : 'last_contact_at';

        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $contacts = $query->orderBy($sortBy, $direction)->paginate($perPage);

        return ContactResource::collection($contacts);
    }
}
