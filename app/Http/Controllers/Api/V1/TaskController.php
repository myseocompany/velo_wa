<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Task::query()->with(['assignee', 'contact']);

        // Agents only see tasks assigned to them
        if ($user->role === 'agent') {
            $query->where('assigned_to', $user->id);
        }

        // Filters
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->string('assigned_to')->toString());
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->string('contact_id')->toString());
        }

        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->string('deal_id')->toString());
        }

        $status = $request->string('status')->toString();
        match ($status) {
            'pending' => $query->pending(),
            'completed' => $query->completed(),
            'overdue' => $query->overdue(),
            'today' => $query->today(),
            default => null,
        };

        $sort = $request->string('sort', 'due_at')->toString();
        $dir = $request->string('direction', 'asc')->toString() === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, ['due_at', 'created_at', 'priority', 'title'], true)) {
            $query->orderByRaw("CASE WHEN {$sort} IS NULL THEN 1 ELSE 0 END")
                ->orderBy($sort, $dir);
        } else {
            $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_at', 'asc');
        }

        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        return TaskResource::collection($query->paginate($perPage));
    }

    public function store(StoreTaskRequest $request): TaskResource
    {
        $task = Task::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return new TaskResource($task->load(['assignee', 'contact']));
    }

    public function show(Task $task): TaskResource
    {
        $this->authorizeTaskAccess($task);

        return new TaskResource($task->load(['assignee', 'contact', 'creator']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorizeTaskAccess($task);

        $task->update($request->validated());

        return new TaskResource($task->fresh(['assignee', 'contact']));
    }

    public function destroy(Task $task): Response
    {
        $this->authorizeTaskAccess($task);

        $task->delete();

        return response()->noContent();
    }

    public function complete(Request $request, Task $task): TaskResource
    {
        $this->authorizeTaskAccess($task);

        $task->update(['completed_at' => now()]);

        return new TaskResource($task->fresh(['assignee', 'contact']));
    }

    public function reopen(Request $request, Task $task): TaskResource
    {
        $this->authorizeTaskAccess($task);

        $task->update(['completed_at' => null]);

        return new TaskResource($task->fresh(['assignee', 'contact']));
    }

    private function authorizeTaskAccess(Task $task): void
    {
        $user = auth()->user();

        if ($user->role === 'agent' && $task->assigned_to !== $user->id) {
            abort(403, 'No tienes permiso para acceder a esta tarea.');
        }
    }
}
