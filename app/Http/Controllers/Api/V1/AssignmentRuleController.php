<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignmentRuleRequest;
use App\Http\Resources\AssignmentRuleResource;
use App\Models\AssignmentRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AssignmentRuleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $rules = AssignmentRule::query()->orderBy('priority')->get();

        return AssignmentRuleResource::collection($rules);
    }

    public function store(AssignmentRuleRequest $request): AssignmentRuleResource
    {
        $rule = AssignmentRule::create([
            'tenant_id' => $request->user()->tenant_id,
            'name'      => $request->input('name'),
            'type'      => $request->input('type'),
            'priority'  => $request->input('priority'),
            'is_active' => $request->boolean('is_active', true),
            'config'    => $request->input('config', []),
        ]);

        return new AssignmentRuleResource($rule);
    }

    public function update(AssignmentRuleRequest $request, AssignmentRule $assignmentRule): AssignmentRuleResource
    {
        $assignmentRule->update([
            'name'      => $request->input('name'),
            'type'      => $request->input('type'),
            'priority'  => $request->input('priority'),
            'is_active' => $request->boolean('is_active', $assignmentRule->is_active),
            'config'    => $request->input('config', $assignmentRule->config),
        ]);

        return new AssignmentRuleResource($assignmentRule);
    }

    public function toggle(AssignmentRule $assignmentRule): AssignmentRuleResource
    {
        $assignmentRule->update(['is_active' => ! $assignmentRule->is_active]);

        return new AssignmentRuleResource($assignmentRule);
    }

    public function destroy(AssignmentRule $assignmentRule): Response
    {
        $assignmentRule->delete();

        return response()->noContent();
    }
}
