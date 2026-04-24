<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DistributionShipment;
use App\Models\EmployeeRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\RoastingStatusLog;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->get()->keyBy('slug');
        $branches = Branch::query()->get()->keyBy('code');

        $mainManager = $this->upsertActiveUser(
            name: 'Main Branch Manager',
            email: 'manager@example.com',
            phone: '0599000001',
            password: 'password',
            roleId: $roles[Role::MANAGER]->id,
            branchId: $branches['MAIN']->id,
            approvedBy: null,
        );

        $northManager = $this->upsertActiveUser(
            name: 'North Branch Manager',
            email: 'north.manager@example.com',
            phone: '0599000002',
            password: 'password',
            roleId: $roles[Role::MANAGER]->id,
            branchId: $branches['NORTH']->id,
            approvedBy: null,
        );

        $mainRoaster = $this->upsertActiveUser('Main Roasting Employee', 'main.roast@example.com', '0599000011', 'password', $roles[Role::ROASTING_EMPLOYEE]->id, $branches['MAIN']->id, $mainManager->id);
        $mainInventory = $this->upsertActiveUser('Main Inventory Employee', 'main.inventory@example.com', '0599000012', 'password', $roles[Role::INVENTORY_EMPLOYEE]->id, $branches['MAIN']->id, $mainManager->id);
        $mainDistribution = $this->upsertActiveUser('Main Distribution Employee', 'main.distribution@example.com', '0599000013', 'password', $roles[Role::DISTRIBUTION_EMPLOYEE]->id, $branches['MAIN']->id, $mainManager->id);

        $northRoaster = $this->upsertActiveUser('North Roasting Employee', 'north.roast@example.com', '0599000021', 'password', $roles[Role::ROASTING_EMPLOYEE]->id, $branches['NORTH']->id, $northManager->id);
        $northInventory = $this->upsertActiveUser('North Inventory Employee', 'north.inventory@example.com', '0599000022', 'password', $roles[Role::INVENTORY_EMPLOYEE]->id, $branches['NORTH']->id, $northManager->id);
        $northDistribution = $this->upsertActiveUser('North Distribution Employee', 'north.distribution@example.com', '0599000023', 'password', $roles[Role::DISTRIBUTION_EMPLOYEE]->id, $branches['NORTH']->id, $northManager->id);

        $this->upsertPendingEmployeeRequest('Pending Main Employee', 'pending.main@example.com', '0599000031', $branches['MAIN']->id);
        $this->upsertPendingEmployeeRequest('Pending North Employee', 'pending.north@example.com', '0599000032', $branches['NORTH']->id);

        $mainRaw = $this->upsertProduct($branches['MAIN']->id, 'MAIN-RAW-001', 'Brazil Santos Raw', 'raw_coffee', 'kg', 180, 45);
        $mainRoasted = $this->upsertProduct($branches['MAIN']->id, 'MAIN-ROAST-001', 'House Blend Roasted', 'roasted_coffee', 'kg', 72, 20);
        $northRaw = $this->upsertProduct($branches['NORTH']->id, 'NORTH-RAW-001', 'Ethiopia Yirgacheffe Raw', 'raw_coffee', 'kg', 145, 35);
        $northRoasted = $this->upsertProduct($branches['NORTH']->id, 'NORTH-ROAST-001', 'North Signature Roast', 'roasted_coffee', 'kg', 54, 18);

        $this->upsertMovement($mainRaw->id, $branches['MAIN']->id, $mainInventory->id, InventoryMovement::TYPE_IN, InventoryMovement::REASON_SUPPLY, 120, 'Opening supply for main branch.');
        $this->upsertMovement($mainRoasted->id, $branches['MAIN']->id, $mainInventory->id, InventoryMovement::TYPE_ADJUSTMENT, InventoryMovement::REASON_MANUAL_ADJUSTMENT, 72, 'Adjusted roasted stock count.');
        $this->upsertMovement($northRaw->id, $branches['NORTH']->id, $northInventory->id, InventoryMovement::TYPE_IN, InventoryMovement::REASON_SUPPLY, 95, 'Opening supply for north branch.');
        $this->upsertMovement($northRoasted->id, $branches['NORTH']->id, $northInventory->id, InventoryMovement::TYPE_ADJUSTMENT, InventoryMovement::REASON_MANUAL_ADJUSTMENT, 54, 'Adjusted roasted stock count.');

        $mainRoasting = $this->upsertRoastingRequest(
            code: 'ROAST-MAIN-001',
            productId: $mainRoasted->id,
            branchId: $branches['MAIN']->id,
            createdBy: $mainManager->id,
            assignedTo: $mainRoaster->id,
            quantity: 30,
            priority: RoastingRequest::PRIORITY_MEDIUM,
            status: RoastingRequest::STATUS_COMPLETED,
            notes: 'Main branch weekly roast batch.',
            startedAt: now()->subDay(),
            completedAt: now()->subHours(12),
        );

        $northRoasting = $this->upsertRoastingRequest(
            code: 'ROAST-NORTH-001',
            productId: $northRoasted->id,
            branchId: $branches['NORTH']->id,
            createdBy: $northManager->id,
            assignedTo: $northRoaster->id,
            quantity: 22,
            priority: RoastingRequest::PRIORITY_URGENT,
            status: RoastingRequest::STATUS_IN_PROGRESS,
            notes: 'North branch urgent cafe refill.',
            startedAt: now()->subHours(4),
            completedAt: null,
        );

        $this->syncRoastingLogs($mainRoasting, $mainManager->id, $mainRoaster->id);
        $this->syncRoastingLogs($northRoasting, $northManager->id, $northRoaster->id);

        $mainShipment = $this->upsertShipment(
            code: 'SHIP-MAIN-001',
            productId: $mainRoasted->id,
            branchId: $branches['MAIN']->id,
            createdBy: $mainManager->id,
            assignedTo: $mainDistribution->id,
            quantity: 18,
            destination: 'Downtown Cafe',
            recipientName: 'Ahmad Barista',
            status: DistributionShipment::STATUS_DELIVERED,
            notes: 'Delivered to main city partner cafe.',
        );

        $northShipment = $this->upsertShipment(
            code: 'SHIP-NORTH-001',
            productId: $northRoasted->id,
            branchId: $branches['NORTH']->id,
            createdBy: $northManager->id,
            assignedTo: $northDistribution->id,
            quantity: 12,
            destination: 'North Market Store',
            recipientName: 'Sara Buyer',
            status: DistributionShipment::STATUS_READY_FOR_PICKUP,
            notes: 'Ready for pickup at north branch.',
        );

        $this->upsertNotification($mainManager->id, 'general', 'Main branch overview', 'Roasting and shipment demo data are ready for testing.', false, Branch::class, $branches['MAIN']->id);
        $this->upsertNotification($mainRoaster->id, 'roasting_update', 'New roasting batch', 'A completed roasting task is available in your branch demo data.', false, RoastingRequest::class, $mainRoasting->id);
        $this->upsertNotification($northManager->id, 'general', 'North branch overview', 'North branch sample records were seeded successfully.', false, Branch::class, $branches['NORTH']->id);
        $this->upsertNotification($northDistribution->id, 'shipment_update', 'Shipment ready', 'A ready-for-pickup shipment was created for your branch.', false, DistributionShipment::class, $northShipment->id);
    }

    private function upsertActiveUser(
        string $name,
        string $email,
        string $phone,
        string $password,
        int $roleId,
        int $branchId,
        ?int $approvedBy
    ): User {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role_id' => $roleId,
                'branch_id' => $branchId,
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => $approvedBy,
            ]
        );
    }

    private function upsertPendingEmployeeRequest(string $name, string $email, string $phone, int $branchId): void
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'role_id' => null,
                'branch_id' => $branchId,
                'is_active' => false,
                'approved_at' => null,
                'approved_by' => null,
            ]
        );

        EmployeeRequest::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => EmployeeRequest::STATUS_PENDING,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]
        );
    }

    private function upsertProduct(
        int $branchId,
        string $sku,
        string $name,
        string $category,
        string $unit,
        float $quantity,
        float $minimumQuantity
    ): Product {
        return Product::updateOrCreate(
            ['sku' => $sku],
            [
                'branch_id' => $branchId,
                'name' => $name,
                'category' => $category,
                'unit' => $unit,
                'quantity' => $quantity,
                'minimum_quantity' => $minimumQuantity,
                'is_active' => true,
            ]
        );
    }

    private function upsertMovement(
        int $productId,
        int $branchId,
        int $performedBy,
        string $movementType,
        string $reason,
        float $quantity,
        string $notes
    ): void {
        InventoryMovement::updateOrCreate(
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'movement_type' => $movementType,
                'reason' => $reason,
                'notes' => $notes,
            ],
            [
                'quantity' => $quantity,
                'performed_by' => $performedBy,
                'reference_type' => null,
                'reference_id' => null,
            ]
        );
    }

    private function upsertRoastingRequest(
        string $code,
        int $productId,
        int $branchId,
        int $createdBy,
        int $assignedTo,
        float $quantity,
        string $priority,
        string $status,
        string $notes,
        $startedAt,
        $completedAt
    ): RoastingRequest {
        return RoastingRequest::updateOrCreate(
            ['code' => $code],
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'created_by' => $createdBy,
                'assigned_to' => $assignedTo,
                'quantity' => $quantity,
                'priority' => $priority,
                'status' => $status,
                'notes' => $notes,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ]
        );
    }

    private function syncRoastingLogs(RoastingRequest $request, int $managerId, int $employeeId): void
    {
        RoastingStatusLog::updateOrCreate(
            ['roasting_request_id' => $request->id, 'status' => RoastingRequest::STATUS_PENDING],
            ['changed_by' => $managerId, 'note' => 'Seeded pending roasting request.']
        );

        RoastingStatusLog::updateOrCreate(
            ['roasting_request_id' => $request->id, 'status' => RoastingRequest::STATUS_ASSIGNED],
            ['changed_by' => $managerId, 'note' => 'Seeded assignment for branch employee.']
        );

        if ($request->status === RoastingRequest::STATUS_IN_PROGRESS || $request->status === RoastingRequest::STATUS_COMPLETED) {
            RoastingStatusLog::updateOrCreate(
                ['roasting_request_id' => $request->id, 'status' => RoastingRequest::STATUS_IN_PROGRESS],
                ['changed_by' => $employeeId, 'note' => 'Seeded in-progress roasting activity.']
            );
        }

        if ($request->status === RoastingRequest::STATUS_COMPLETED) {
            RoastingStatusLog::updateOrCreate(
                ['roasting_request_id' => $request->id, 'status' => RoastingRequest::STATUS_COMPLETED],
                ['changed_by' => $employeeId, 'note' => 'Seeded completed roasting activity.']
            );
        }
    }

    private function upsertShipment(
        string $code,
        int $productId,
        int $branchId,
        int $createdBy,
        int $assignedTo,
        float $quantity,
        string $destination,
        string $recipientName,
        string $status,
        string $notes
    ): DistributionShipment {
        return DistributionShipment::updateOrCreate(
            ['shipment_code' => $code],
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'created_by' => $createdBy,
                'assigned_to' => $assignedTo,
                'quantity' => $quantity,
                'destination' => $destination,
                'recipient_name' => $recipientName,
                'status' => $status,
                'notes' => $notes,
                'prepared_at' => in_array($status, [DistributionShipment::STATUS_READY_FOR_PICKUP, DistributionShipment::STATUS_TRANSFERRED, DistributionShipment::STATUS_DELIVERED], true) ? now()->subHours(6) : null,
                'transferred_at' => in_array($status, [DistributionShipment::STATUS_TRANSFERRED, DistributionShipment::STATUS_DELIVERED], true) ? now()->subHours(3) : null,
                'delivered_at' => $status === DistributionShipment::STATUS_DELIVERED ? now()->subHour() : null,
            ]
        );
    }

    private function upsertNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        bool $isRead,
        ?string $relatedType,
        ?int $relatedId
    ): void {
        SystemNotification::updateOrCreate(
            ['user_id' => $userId, 'title' => $title],
            [
                'type' => $type,
                'message' => $message,
                'is_read' => $isRead,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'read_at' => $isRead ? now()->subMinutes(30) : null,
            ]
        );
    }
}
