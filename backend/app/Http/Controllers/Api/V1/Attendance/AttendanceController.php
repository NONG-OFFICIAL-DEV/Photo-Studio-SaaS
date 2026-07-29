<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(protected AttendanceService $attendance)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $paginator = $this->attendance->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage', 'user_id', 'status', 'date_from', 'date_to',
        ]));

        return $this->success(
            AttendanceRecordResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $record = $this->attendance->create($request->validated(), $request->user());

        return $this->created(new AttendanceRecordResource($record), 'Attendance record created successfully.');
    }

    public function update(UpdateAttendanceRequest $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $record = $this->attendance->update($attendanceRecord, $request->validated());

        return $this->success(new AttendanceRecordResource($record), 'Attendance record updated successfully.');
    }

    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('delete', $attendanceRecord);

        $this->attendance->delete($attendanceRecord);

        return $this->noContent('Attendance record deleted successfully.');
    }

    public function clockIn(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.clock'), 403);

        $record = $this->attendance->clockIn($request->user());

        return $this->success(new AttendanceRecordResource($record), 'Clocked in successfully.');
    }

    public function clockOut(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.clock'), 403);

        $record = $this->attendance->clockOut($request->user());

        return $this->success(new AttendanceRecordResource($record), 'Clocked out successfully.');
    }

    public function today(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('attendance.clock'), 403);

        $record = AttendanceRecord::where('user_id', $request->user()->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return $this->success($record ? new AttendanceRecordResource($record) : null);
    }
}
