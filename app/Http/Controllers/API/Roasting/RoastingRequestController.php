<?php

namespace App\Http\Controllers\API\Roasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roasting\AssignRoastingRequest;
use App\Http\Requests\Roasting\StoreRoastingRequest;
use App\Http\Requests\Roasting\UpdateRoastingStatusRequest;
use App\Models\RoastingRequest;
use App\Models\RoastingStatusLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoastingRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RoastingRequest::query()
            ->with(['product', 'creator', 'assignedEmployee', 'branch'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Roasting requests fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function store(StoreRoastingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $roastingRequest = DB::transaction(function () use ($request, $data): RoastingRequest {
            $roastingRequest = new RoastingRequest();
            $roastingRequest->forceFill($data + [
                'status' => RoastingRequest::STATUS_PENDING,
                'created_by' => $request->user()->id,
            ])->save();

            $this->logStatus($roastingRequest, RoastingRequest::STATUS_PENDING, $request->user()?->id, 'Request created.');

            return $roastingRequest;
        });

        return response()->json([
            'success' => true,
            'message' => 'Roasting request created successfully.',
            'data' => $roastingRequest->load(['product', 'creator', 'assignedEmployee', 'branch', 'statusLogs']),
        ], 201);
    }

    public function show(RoastingRequest $roastingRequest): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Roasting request fetched successfully.',
            'data' => $roastingRequest->load(['product', 'creator', 'assignedEmployee', 'branch', 'statusLogs.changer']),
        ]);
    }

    public function assignEmployee(AssignRoastingRequest $request, RoastingRequest $roastingRequest): JsonResponse
    {
        $data = $request->validated();

        $this->changeStatus($roastingRequest, RoastingRequest::STATUS_ASSIGNED, $request->user()?->id, $data['note'] ?? null, [
            'assigned_to' => $data['assigned_to'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Roasting request assigned successfully.',
            'data' => $roastingRequest->fresh(['product', 'creator', 'assignedEmployee', 'branch', 'statusLogs']),
        ]);
    }

    public function updateStatus(UpdateRoastingStatusRequest $request, RoastingRequest $roastingRequest): JsonResponse
    {
        $data = $request->validated();

        $this->changeStatus($roastingRequest, $data['status'], $request->user()?->id, $data['note'] ?? null, $this->timestampsForStatus($data['status']));

        return response()->json([
            'success' => true,
            'message' => 'Roasting status updated successfully.',
            'data' => $roastingRequest->fresh(['product', 'creator', 'assignedEmployee', 'branch', 'statusLogs']),
        ]);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $query = RoastingRequest::query()
            ->with(['product', 'branch'])
            ->where('assigned_to', $request->user()->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Assigned roasting tasks fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function startTask(Request $request, RoastingRequest $roastingRequest): JsonResponse
    {
        if ($roastingRequest->assigned_to !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only start tasks assigned to you.',
            ], 403);
        }

        $this->changeStatus($roastingRequest, RoastingRequest::STATUS_IN_PROGRESS, $request->user()->id, 'Task started.', [
            'started_at' => $roastingRequest->started_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Roasting task started successfully.',
            'data' => $roastingRequest->fresh(['product', 'branch', 'statusLogs']),
        ]);
    }

    public function completeTask(Request $request, RoastingRequest $roastingRequest): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        if ($roastingRequest->assigned_to !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only complete tasks assigned to you.',
            ], 403);
        }

        $this->changeStatus($roastingRequest, RoastingRequest::STATUS_COMPLETED, $request->user()->id, $data['note'] ?? 'Task completed.', [
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Roasting task completed successfully.',
            'data' => $roastingRequest->fresh(['product', 'branch', 'statusLogs']),
        ]);
    }

    private function changeStatus(RoastingRequest $roastingRequest, string $status, ?int $changedBy, ?string $note = null, array $extra = []): void
    {
        DB::transaction(function () use ($roastingRequest, $status, $changedBy, $note, $extra): void {
            $roastingRequest->forceFill($extra + ['status' => $status])->save();
            $this->logStatus($roastingRequest, $status, $changedBy, $note);
        });
    }

    private function logStatus(RoastingRequest $roastingRequest, string $status, ?int $changedBy, ?string $note = null): void
    {
        $log = new RoastingStatusLog();
        $log->forceFill([
            'roasting_request_id' => $roastingRequest->id,
            'status' => $status,
            'changed_by' => $changedBy,
            'note' => $note,
        ])->save();
    }

    private function timestampsForStatus(string $status): array
    {
        return match ($status) {
            RoastingRequest::STATUS_IN_PROGRESS => ['started_at' => now()],
            RoastingRequest::STATUS_COMPLETED => ['completed_at' => now()],
            default => [],
        };
    }
}
