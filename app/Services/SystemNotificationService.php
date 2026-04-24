<?php

namespace App\Services;

use App\Models\DistributionShipment;
use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;

class SystemNotificationService
{
    public function notifyNewEmployeeRequest(User $user): void
    {
        $user->loadMissing('branch');

        $this->sendToBranchRoles(
            $user->branch_id,
            [Role::MANAGER],
            'join_request',
            'طلب انضمام جديد',
            sprintf(
                'تم تسجيل طلب انضمام جديد للموظف %s في فرع %s.',
                $user->name,
                $user->branch?->name ?? 'الفرع الحالي'
            ),
            User::class,
            $user->id
        );
    }

    public function notifyStockThreshold(Product $product, float $previousQuantity): void
    {
        $product->loadMissing('branch');

        $currentQuantity = (float) $product->quantity;
        $minimumQuantity = (float) $product->minimum_quantity;
        $crossedOutOfStock = $previousQuantity > 0 && $currentQuantity <= 0;
        $crossedLowStock = $previousQuantity > $minimumQuantity && $currentQuantity <= $minimumQuantity;

        if (! $crossedOutOfStock && ! $crossedLowStock) {
            return;
        }

        $title = $crossedOutOfStock ? 'نفاد المخزون' : 'انخفاض المخزون';
        $message = $crossedOutOfStock
            ? sprintf(
                'نفد مخزون المنتج %s في فرع %s.',
                $product->name,
                $product->branch?->name ?? 'الفرع الحالي'
            )
            : sprintf(
                'انخفض مخزون المنتج %s إلى %s %s في فرع %s.',
                $product->name,
                $this->formatQuantity($currentQuantity),
                $product->unit,
                $product->branch?->name ?? 'الفرع الحالي'
            );

        $this->sendToBranchRoles(
            $product->branch_id,
            [Role::MANAGER, Role::INVENTORY_EMPLOYEE],
            'low_stock',
            $title,
            $message,
            Product::class,
            $product->id
        );
    }

    public function notifyRoastingCompleted(RoastingRequest $roastingRequest): void
    {
        $roastingRequest->loadMissing(['product', 'branch']);

        $this->sendToBranchRolesAndUsers(
            $roastingRequest->branch_id,
            [Role::MANAGER],
            [$roastingRequest->created_by, $roastingRequest->assigned_to],
            'roasting_update',
            'اكتملت عملية التحميص',
            sprintf(
                'تم إكمال عملية التحميص %s للمنتج %s بكمية %s %s في فرع %s.',
                $roastingRequest->code,
                $roastingRequest->product?->name ?? 'منتج غير معروف',
                $this->formatQuantity((float) $roastingRequest->quantity),
                $roastingRequest->product?->unit ?? 'وحدة',
                $roastingRequest->branch?->name ?? 'الفرع الحالي'
            ),
            RoastingRequest::class,
            $roastingRequest->id
        );
    }

    public function notifyShipmentDelivered(DistributionShipment $distributionShipment): void
    {
        $distributionShipment->loadMissing(['product', 'branch']);

        $this->sendToBranchRolesAndUsers(
            $distributionShipment->branch_id,
            [Role::MANAGER],
            [$distributionShipment->created_by, $distributionShipment->assigned_to],
            'shipment_update',
            'تم تسليم الشحنة',
            sprintf(
                'تم تسليم الشحنة %s الخاصة بالمنتج %s إلى %s في فرع %s.',
                $distributionShipment->shipment_code,
                $distributionShipment->product?->name ?? 'منتج غير معروف',
                $distributionShipment->destination,
                $distributionShipment->branch?->name ?? 'الفرع الحالي'
            ),
            DistributionShipment::class,
            $distributionShipment->id
        );
    }

    private function sendToBranchRoles(
        ?int $branchId,
        array $roleSlugs,
        string $type,
        string $title,
        string $message,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        $this->sendToBranchRolesAndUsers($branchId, $roleSlugs, [], $type, $title, $message, $relatedType, $relatedId);
    }

    private function sendToBranchRolesAndUsers(
        ?int $branchId,
        array $roleSlugs,
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        if (! $branchId) {
            return;
        }

        $roleUserIds = User::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $roleSlugs))
            ->pluck('id')
            ->all();

        $targetUserIds = array_values(array_unique(array_filter([
            ...$roleUserIds,
            ...$userIds,
        ])));

        if ($targetUserIds === []) {
            return;
        }

        $validUserIds = User::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $targetUserIds)
            ->pluck('id')
            ->all();

        foreach ($validUserIds as $userId) {
            SystemNotification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
            ]);
        }
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }
}
