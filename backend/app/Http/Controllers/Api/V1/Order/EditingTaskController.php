<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\EditingTask\AssignEditingTaskRequest;
use App\Http\Resources\EditingTaskResource;
use App\Models\EditingTask;
use App\Services\EditingTaskService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditingTaskController extends Controller
{
    use ApiResponse;

    public function __construct(protected EditingTaskService $tasks)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EditingTask::class);

        $paginator = $this->tasks->paginate($request->only([
            'sortBy', 'sortDesc', 'page', 'perPage', 'status', 'assigned_user_id',
        ]));

        return $this->success(
            EditingTaskResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function assign(AssignEditingTaskRequest $request, EditingTask $editingTask): JsonResponse
    {
        $editingTask = $this->tasks->assign($editingTask, $request->input('assigned_user_id'));

        return $this->success(new EditingTaskResource($editingTask->load('assignedUser')), 'Task reassigned.');
    }

    public function start(EditingTask $editingTask): JsonResponse
    {
        $this->authorize('update', $editingTask);

        $task = $this->tasks->start($editingTask);

        return $this->success(new EditingTaskResource($task->load('assignedUser')), 'Editing started.');
    }

    public function markInReview(EditingTask $editingTask): JsonResponse
    {
        $this->authorize('update', $editingTask);

        $task = $this->tasks->markInReview($editingTask);

        return $this->success(new EditingTaskResource($task->load('assignedUser')), 'Task submitted for review.');
    }

    public function requestRevision(EditingTask $editingTask): JsonResponse
    {
        $this->authorize('update', $editingTask);

        $task = $this->tasks->requestRevision($editingTask);

        return $this->success(new EditingTaskResource($task->load('assignedUser')), 'Revision requested.');
    }

    public function complete(EditingTask $editingTask): JsonResponse
    {
        $this->authorize('update', $editingTask);

        $task = $this->tasks->complete($editingTask);

        return $this->success(new EditingTaskResource($task->load('assignedUser')), 'Task completed.');
    }
}
