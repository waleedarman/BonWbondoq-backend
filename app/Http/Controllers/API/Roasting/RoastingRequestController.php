<?php

namespace App\Http\Controllers\API\Roasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roasting\AssignRoastingRequest;
use App\Http\Requests\Roasting\StoreRoastingRequest;
use App\Http\Requests\Roasting\UpdateRoastingStatusRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\RoastingStatusLog;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoastingRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RoastingRequest::query()
            ->with(['product', 'creator', 'assignedEmployee', 'branch'])
            ->where('branch_id', $request->user()->branch_id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only list roasting requests from your branch.',
            ], 403);
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

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Roasting requests can only be created inside your branch.',
            ], 422);
        }

        $roastingRequest = DB::transaction(function () use ($request, $data): RoastingRequest {
            $product = Product::where('branch_id', $request->user()->branch_id)->findOrFail($data['product_id']);

            $roastingRequest = new RoastingRequest();
            $roastingRequest->forceFill(array_merge($data, [
                'branch_id' => $product->branch_id,
                'status' => RoastingRequest::STATUS_PENDING,
                'created_by' => $request->user()->id,
            ]))->save();

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
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'Roasting request fetched successfully.',
            'data' => $roastingRequest->load(['product', 'creator', 'assignedEmployee', 'branch', 'statusLogs.changer']),
        ]);
    }

    public function assignEmployee(AssignRoastingRequest $request, RoastingRequest $roastingRequest): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);
        $this->abortUnlessAssignableEmployee($data['assigned_to'], Role::ROASTING_EMPLOYEE);

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
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

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
            ->where('branch_id', $request->user()->branch_id)
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
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

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
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

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
            $previousStatus = $roastingRequest->status;
            $this->consumeStockIfNeeded($roastingRequest, $status, $changedBy);
            $roastingRequest->forceFill($extra + ['status' => $status])->save();
            $this->logStatus($roastingRequest, $status, $changedBy, $note);

            if ($status === RoastingRequest::STATUS_COMPLETED && $previousStatus !== RoastingRequest::STATUS_COMPLETED) {
                app(SystemNotificationService::class)->notifyRoastingCompleted($roastingRequest);
            }
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

    private function abortUnlessAssignableEmployee(int $userId, string $roleSlug): void
    {
        $exists = User::where('id', $userId)
            ->where('branch_id', $this->currentBranchId())
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->exists();

        abort_unless($exists, 422, 'Assigned employee must belong to your branch and role.');
    }

    private function consumeStockIfNeeded(RoastingRequest $roastingRequest, string $nextStatus, ?int $performedBy): void
    {
        if (! in_array($nextStatus, [RoastingRequest::STATUS_IN_PROGRESS, RoastingRequest::STATUS_COMPLETED], true)) {
            return;
        }

        $alreadyConsumed = InventoryMovement::where('reference_type', RoastingRequest::class)
            ->where('reference_id', $roastingRequest->id)
            ->where('reason', InventoryMovement::REASON_ROASTING_USAGE)
            ->exists();

        if ($alreadyConsumed) {
            return;
        }

        $product = Product::where('branch_id', $roastingRequest->branch_id)
            ->lockForUpdate()
            ->findOrFail($roastingRequest->product_id);

        $previousQuantity = (float) $product->quantity;
        $quantity = (float) $roastingRequest->quantity;

        if ((float) $product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient product quantity in inventory for this roasting request.',
            ]);
        }

        $product->quantity = (float) $product->quantity - $quantity;
        $product->save();

        app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

        InventoryMovement::create([
            'product_id' => $product->id,
            'branch_id' => $product->branch_id,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'quantity' => $quantity,
            'reason' => InventoryMovement::REASON_ROASTING_USAGE,
            'reference_type' => RoastingRequest::class,
            'reference_id' => $roastingRequest->id,
            'performed_by' => $performedBy,
            'notes' => 'Inventory consumed for roasting request.',
        ]);
    }
}
