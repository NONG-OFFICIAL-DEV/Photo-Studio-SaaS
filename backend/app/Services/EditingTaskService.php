<?php

namespace App\Services;

use App\Enums\EditingStatus;
use App\Exceptions\ApiException;
use App\Models\EditingTask;
use App\Repositories\Contracts\EditingTaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EditingTaskService extends BaseService
{
    public function __construct(protected EditingTaskRepositoryInterface $tasks)
    {
        parent::__construct($tasks);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->tasks->paginateServer($filters);
    }

    public function assign(EditingTask $task, ?string $userId): EditingTask
    {
        $task->update(['assigned_user_id' => $userId]);

        return $task;
    }

    public function start(EditingTask $task): EditingTask
    {
        $this->assertStatusIn($task, [EditingStatus::Pending, EditingStatus::RevisionRequested]);
        $task->update(['status' => EditingStatus::InProgress]);

        return $task;
    }

    public function markInReview(EditingTask $task): EditingTask
    {
        $this->assertStatusIn($task, [EditingStatus::InProgress]);
        $task->update(['status' => EditingStatus::InReview]);

        return $task;
    }

    public function requestRevision(EditingTask $task): EditingTask
    {
        $this->assertStatusIn($task, [EditingStatus::InReview]);
        $task->update(['status' => EditingStatus::RevisionRequested]);

        return $task;
    }

    public function complete(EditingTask $task): EditingTask
    {
        $this->assertStatusIn($task, [EditingStatus::InProgress, EditingStatus::InReview]);
        $task->update(['status' => EditingStatus::Completed, 'completed_at' => now()]);

        return $task;
    }

    protected function assertStatusIn(EditingTask $task, array $allowed): void
    {
        if (! in_array($task->status, $allowed, true)) {
            $allowedLabels = implode(', ', array_map(fn ($s) => $s->label(), $allowed));
            throw new ApiException(422, "This action requires the task to be one of: {$allowedLabels} (currently \"{$task->status->label()}\").", 'EDITING_TASK_INVALID_STATUS_TRANSITION', ['allowed' => $allowedLabels, 'current' => $task->status->label()]);
        }
    }
}
