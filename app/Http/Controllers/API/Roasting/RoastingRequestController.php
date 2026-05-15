<?php

namespace App\Http\Controllers\API\Roasting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roasting\AssignRoastingRequest;
use App\Http\Requests\Roasting\StoreRoastingRequest;
use App\Http\Requests\Roasting\UpdateRoastingRequest;
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
    private const INVENTORY_REFERENCE_TYPE = 'roasting_request';

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
            $outputProductId = $this->outputProductIdFromNotes($data['notes'] ?? null);

            if (! $outputProductId) {
                throw ValidationException::withMessages([
                    'output_product_id' => 'لم يتم تحديد المنتج الناتج لهذه المهمة.',
                ]);
            }

            if ((int) $product->id === $outputProductId) {
                throw ValidationException::withMessages([
                    'output_product_id' => 'يجب أن يكون المنتج الناتج مختلفا عن المادة الخام.',
                ]);
            }

            Product::where('branch_id', $product->branch_id)->findOrFail($outputProductId);

            if ((float) $product->quantity < (float) $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'الكمية المتوفرة من المادة الخام غير كافية.',
                ]);
            }

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

    public function update(UpdateRoastingRequest $request, RoastingRequest $roastingRequest): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        if (in_array($roastingRequest->status, [RoastingRequest::STATUS_IN_PROGRESS, RoastingRequest::STATUS_COMPLETED, RoastingRequest::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Roasting request cannot be edited after it starts.',
            ]);
        }

        $product = Product::where('branch_id', $request->user()->branch_id)->findOrFail($data['product_id']);
        $outputProductId = $this->outputProductIdFromNotes($data['notes'] ?? null);

        if (! $outputProductId) {
            throw ValidationException::withMessages([
                'output_product_id' => 'لم يتم تحديد المنتج الناتج لهذه المهمة.',
            ]);
        }

        if ((int) $product->id === $outputProductId) {
            throw ValidationException::withMessages([
                'output_product_id' => 'يجب أن يكون المنتج الناتج مختلفا عن المادة الخام.',
            ]);
        }

        Product::where('branch_id', $product->branch_id)->findOrFail($outputProductId);

        if ((float) $product->quantity < (float) $data['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية المتوفرة من المادة الخام غير كافية.',
            ]);
        }

        $roastingRequest->forceFill([
            'product_id' => $product->id,
            'quantity' => $data['quantity'],
            'priority' => $data['priority'],
            'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->save();

        $this->logStatus($roastingRequest, $roastingRequest->status, $request->user()?->id, 'Request details updated.');

        return response()->json([
            'success' => true,
            'message' => 'Roasting request updated successfully.',
            'data' => $roastingRequest->fresh(['product', 'creator', 'assignedEmployee', 'branch', 'statusLogs']),
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


    public function destroy(RoastingRequest $roastingRequest): JsonResponse
    {
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        if ($roastingRequest->status !== RoastingRequest::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'Only cancelled roasting requests can be deleted.',
            ]);
        }

        DB::transaction(function () use ($roastingRequest): void {
            $roastingRequest->statusLogs()->delete();
            $roastingRequest->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Roasting request deleted successfully.',
            'data' => null,
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

    public function showMyTask(Request $request, RoastingRequest $roastingRequest): JsonResponse
    {
        $this->abortUnlessAssignedToCurrentUser($request, $roastingRequest);
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'Assigned roasting task fetched successfully.',
            'data' => $roastingRequest->load(['product', 'branch', 'statusLogs']),
        ]);
    }

    public function startTask(Request $request, RoastingRequest $roastingRequest): JsonResponse
    {
        $this->abortUnlessAssignedToCurrentUser($request, $roastingRequest);
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        DB::transaction(function () use ($request, $roastingRequest): void {
            $lockedRequest = RoastingRequest::whereKey($roastingRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedRequest->status, [RoastingRequest::STATUS_IN_PROGRESS, RoastingRequest::STATUS_COMPLETED], true)) {
                return;
            }

            if ($lockedRequest->status === RoastingRequest::STATUS_CANCELLED) {
                abort(422, 'This roasting task can no longer be started.');
            }

            $this->deductRawStock($lockedRequest, $request->user()->id);

            $lockedRequest->forceFill([
                'status' => RoastingRequest::STATUS_IN_PROGRESS,
                'started_at' => $lockedRequest->started_at ?? now(),
            ])->save();

            $this->logStatus($lockedRequest, RoastingRequest::STATUS_IN_PROGRESS, $request->user()->id, 'Task started.');
        });

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
            'final_output_quantity' => ['nullable', 'required_without:final_output_kg', 'numeric', 'min:0.01'],
            'final_output_kg' => ['nullable', 'required_without:final_output_quantity', 'numeric', 'min:0.01'],
        ]);

        $this->abortUnlessAssignedToCurrentUser($request, $roastingRequest);
        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);
        $finalOutputQuantity = (float) ($data['final_output_quantity'] ?? $data['final_output_kg']);

        if ($finalOutputQuantity > (float) $roastingRequest->quantity) {
            throw ValidationException::withMessages([
                'final_output_quantity' => 'Final output quantity cannot be greater than the raw quantity.',
            ]);
        }

        DB::transaction(function () use ($request, $roastingRequest, $data, $finalOutputQuantity): void {
            $lockedRequest = RoastingRequest::whereKey($roastingRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status === RoastingRequest::STATUS_COMPLETED) {
                return;
            }

            if ($lockedRequest->status !== RoastingRequest::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'status' => 'يجب بدء مهمة التحميص قبل إنهائها.',
                ]);
            }

            $this->addOutputStock($lockedRequest, $finalOutputQuantity, $request->user()->id);

            $lockedRequest->forceFill([
                'status' => RoastingRequest::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            $this->logStatus($lockedRequest, RoastingRequest::STATUS_COMPLETED, $request->user()->id, $data['note'] ?? 'Task completed.');
            app(SystemNotificationService::class)->notifyRoastingCompleted($lockedRequest);
        });

        return response()->json([
            'success' => true,
            'message' => 'Roasting task completed successfully.',
            'data' => $roastingRequest->fresh(['product', 'branch', 'statusLogs']),
        ]);
    }

    private function changeStatus(
        RoastingRequest $roastingRequest,
        string $status,
        ?int $changedBy,
        ?string $note = null,
        array $extra = [],
        ?float $finalOutputQuantity = null,
    ): void
    {
        DB::transaction(function () use ($roastingRequest, $status, $changedBy, $note, $extra, $finalOutputQuantity): void {
            $previousStatus = $roastingRequest->status;
            $this->consumeStockIfNeeded($roastingRequest, $status, $changedBy);
            $roastingRequest->forceFill($extra + ['status' => $status])->save();
            $this->logStatus($roastingRequest, $status, $changedBy, $note);

            if ($status === RoastingRequest::STATUS_COMPLETED && $previousStatus !== RoastingRequest::STATUS_COMPLETED) {
                $this->produceOutputStockIfNeeded($roastingRequest, $finalOutputQuantity, $changedBy);
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

    private function abortUnlessAssignedToCurrentUser(Request $request, RoastingRequest $roastingRequest): void
    {
        abort_unless($roastingRequest->assigned_to === $request->user()?->id, 403, 'You can only access tasks assigned to you.');
    }

    private function consumeStockIfNeeded(RoastingRequest $roastingRequest, string $nextStatus, ?int $performedBy): void
    {
        if ($nextStatus !== RoastingRequest::STATUS_IN_PROGRESS) {
            return;
        }

        $alreadyConsumed = InventoryMovement::whereIn('reference_type', [self::INVENTORY_REFERENCE_TYPE, RoastingRequest::class])
            ->where('reference_id', $roastingRequest->id)
            ->where('movement_type', InventoryMovement::TYPE_OUT)
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
                'quantity' => 'الكمية المتوفرة من المادة الخام غير كافية.',
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
            'reason' => InventoryMovement::REASON_MANUAL_ADJUSTMENT,
            'reference_type' => self::INVENTORY_REFERENCE_TYPE,
            'reference_id' => $roastingRequest->id,
            'performed_by' => $performedBy,
            'notes' => "{$roastingRequest->code} - خصم تلقائي عند بدء التحميص",
        ]);
    }

    private function produceOutputStockIfNeeded(RoastingRequest $roastingRequest, ?float $finalOutputQuantity, ?int $performedBy): void
    {
        if (! $finalOutputQuantity || $finalOutputQuantity <= 0) {
            return;
        }

        $alreadyProduced = InventoryMovement::whereIn('reference_type', [self::INVENTORY_REFERENCE_TYPE, RoastingRequest::class])
            ->where('reference_id', $roastingRequest->id)
            ->where('movement_type', InventoryMovement::TYPE_IN)
            ->exists();

        if ($alreadyProduced) {
            return;
        }

        $outputProductId = $this->outputProductIdFromNotes($roastingRequest->notes);

        if (! $outputProductId) {
            throw ValidationException::withMessages([
                'output_product_id' => 'لم يتم تحديد المنتج الناتج لهذه المهمة.',
            ]);
        }

        $product = Product::where('branch_id', $roastingRequest->branch_id)
            ->lockForUpdate()
            ->findOrFail($outputProductId);

        $previousQuantity = (float) $product->quantity;
        $product->quantity = $previousQuantity + $finalOutputQuantity;
        $product->save();

        app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

        InventoryMovement::create([
            'product_id' => $product->id,
            'branch_id' => $product->branch_id,
            'movement_type' => InventoryMovement::TYPE_IN,
            'quantity' => $finalOutputQuantity,
            'reason' => InventoryMovement::REASON_MANUAL_ADJUSTMENT,
            'reference_type' => self::INVENTORY_REFERENCE_TYPE,
            'reference_id' => $roastingRequest->id,
            'performed_by' => $performedBy,
            'notes' => "{$roastingRequest->code} - إضافة الناتج بعد إنهاء التحميص",
        ]);
    }

    private function deductRawStock(RoastingRequest $roastingRequest, int $performedBy): void
    {
        $this->consumeStockIfNeeded($roastingRequest, RoastingRequest::STATUS_IN_PROGRESS, $performedBy);
    }

    private function addOutputStock(RoastingRequest $roastingRequest, float $finalOutputQuantity, int $performedBy): void
    {
        $this->produceOutputStockIfNeeded($roastingRequest, $finalOutputQuantity, $performedBy);
    }

    private function inventoryPlan(RoastingRequest $roastingRequest): array
    {
        if (! $roastingRequest->notes) {
            return [];
        }

        $plan = json_decode($roastingRequest->notes, true);

        return is_array($plan) ? $plan : [];
    }

    private function outputProductIdFromNotes(?string $notes): ?int
    {
        if (! $notes) {
            return null;
        }

        $plan = json_decode($notes, true);

        if (! is_array($plan)) {
            return null;
        }

        $outputProductId = (int) ($plan['output_product_id'] ?? $plan['roasted_product_id'] ?? 0);

        return $outputProductId > 0 ? $outputProductId : null;
    }
}
