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
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            BranchSeeder::class,
        ]);

        $roles = Role::query()->get()->keyBy('slug');
        $branches = Branch::query()->get()->keyBy('code');

        $branchTeams = [];

        foreach ($this->teamBlueprints() as $branchCode => $team) {
            $branch = $branches[$branchCode];

            $manager = $this->upsertActiveUser(
                name: $team['manager']['name'],
                email: $team['manager']['email'],
                phone: $team['manager']['phone'],
                roleId: $roles[Role::MANAGER]->id,
                branchId: $branch->id,
                approvedBy: null,
            );

            $branchTeams[$branchCode] = [
                'branch' => $branch,
                'manager' => $manager,
                'roasters' => [],
                'inventory' => [],
                'distribution' => [],
            ];

            foreach ($team['roasters'] as $employee) {
                $branchTeams[$branchCode]['roasters'][] = $this->upsertActiveUser(
                    $employee['name'],
                    $employee['email'],
                    $employee['phone'],
                    $roles[Role::ROASTING_EMPLOYEE]->id,
                    $branch->id,
                    $manager->id,
                );
            }

            foreach ($team['inventory'] as $employee) {
                $branchTeams[$branchCode]['inventory'][] = $this->upsertActiveUser(
                    $employee['name'],
                    $employee['email'],
                    $employee['phone'],
                    $roles[Role::INVENTORY_EMPLOYEE]->id,
                    $branch->id,
                    $manager->id,
                );
            }

            foreach ($team['distribution'] as $employee) {
                $branchTeams[$branchCode]['distribution'][] = $this->upsertActiveUser(
                    $employee['name'],
                    $employee['email'],
                    $employee['phone'],
                    $roles[Role::DISTRIBUTION_EMPLOYEE]->id,
                    $branch->id,
                    $manager->id,
                );
            }

            foreach ($team['pending'] as $employee) {
                $this->upsertEmployeeRequest($employee['name'], $employee['email'], $employee['phone'], $branch->id);
            }

            foreach ($team['rejected'] as $employee) {
                $this->upsertEmployeeRequest(
                    $employee['name'],
                    $employee['email'],
                    $employee['phone'],
                    $branch->id,
                    EmployeeRequest::STATUS_REJECTED,
                    $manager->id,
                    'Incomplete employment details in demo data.',
                );
            }
        }

        foreach ($branchTeams as $branchCode => $team) {
            $products = $this->seedProductsForBranch($branchCode, $team);
            $this->seedRoastingForBranch($branchCode, $team, $products);
            $this->seedDistributionForBranch($branchCode, $team, $products);
            $this->seedNotificationsForBranch($branchCode, $team, $products);
        }
    }

    private function teamBlueprints(): array
    {
        return [
            'MAIN' => [
                'manager' => ['name' => 'Yousef Main Manager', 'email' => 'manager@example.com', 'phone' => '0599000001'],
                'roasters' => [
                    ['name' => 'Ahmad Roasting', 'email' => 'main.roast@example.com', 'phone' => '0599000011'],
                    ['name' => 'Lina Roast Lead', 'email' => 'main.roast.lead@example.com', 'phone' => '0599000014'],
                ],
                'inventory' => [
                    ['name' => 'Mona Inventory', 'email' => 'main.inventory@example.com', 'phone' => '0599000012'],
                    ['name' => 'Fadi Stock Clerk', 'email' => 'main.stock@example.com', 'phone' => '0599000015'],
                ],
                'distribution' => [
                    ['name' => 'Omar Distribution', 'email' => 'main.distribution@example.com', 'phone' => '0599000013'],
                    ['name' => 'Sara Courier', 'email' => 'main.courier@example.com', 'phone' => '0599000016'],
                ],
                'pending' => [
                    ['name' => 'Pending Main Employee', 'email' => 'pending.main@example.com', 'phone' => '0599000031'],
                    ['name' => 'New Main Barista', 'email' => 'pending.main.barista@example.com', 'phone' => '0599000033'],
                ],
                'rejected' => [
                    ['name' => 'Rejected Main Employee', 'email' => 'rejected.main@example.com', 'phone' => '0599000041'],
                ],
            ],
            'NORTH' => [
                'manager' => ['name' => 'North Branch Manager', 'email' => 'north.manager@example.com', 'phone' => '0599000002'],
                'roasters' => [
                    ['name' => 'Nader Roaster', 'email' => 'north.roast@example.com', 'phone' => '0599000021'],
                    ['name' => 'Hiba Roasting', 'email' => 'north.roast.hiba@example.com', 'phone' => '0599000024'],
                ],
                'inventory' => [
                    ['name' => 'Rami Inventory', 'email' => 'north.inventory@example.com', 'phone' => '0599000022'],
                ],
                'distribution' => [
                    ['name' => 'Tariq Distribution', 'email' => 'north.distribution@example.com', 'phone' => '0599000023'],
                    ['name' => 'Noor Delivery', 'email' => 'north.delivery@example.com', 'phone' => '0599000025'],
                ],
                'pending' => [
                    ['name' => 'Pending North Employee', 'email' => 'pending.north@example.com', 'phone' => '0599000032'],
                ],
                'rejected' => [
                    ['name' => 'Rejected North Employee', 'email' => 'rejected.north@example.com', 'phone' => '0599000042'],
                ],
            ],
            'ATTIL' => [
                'manager' => ['name' => 'Attil Branch Manager', 'email' => 'attil.manager@example.com', 'phone' => '0599000003'],
                'roasters' => [
                    ['name' => 'Khaled Roasting', 'email' => 'attil.roast@example.com', 'phone' => '0599000051'],
                ],
                'inventory' => [
                    ['name' => 'Dima Inventory', 'email' => 'attil.inventory@example.com', 'phone' => '0599000052'],
                ],
                'distribution' => [
                    ['name' => 'Majd Distribution', 'email' => 'attil.distribution@example.com', 'phone' => '0599000053'],
                ],
                'pending' => [
                    ['name' => 'Pending Attil Employee', 'email' => 'pending.attil@example.com', 'phone' => '0599000054'],
                ],
                'rejected' => [],
            ],
        ];
    }

    private function seedProductsForBranch(string $branchCode, array $team): array
    {
        $branch = $team['branch'];
        $inventoryUser = $team['inventory'][0];
        $prefix = $branchCode;

        $products = [
            'raw_brazil' => $this->upsertProduct($branch->id, "{$prefix}-RAW-BRAZIL", 'Brazil Santos Raw Coffee', 'raw_coffee', 'kg', 180, 45),
            'raw_ethiopia' => $this->upsertProduct($branch->id, "{$prefix}-RAW-ETHIOPIA", 'Ethiopia Yirgacheffe Raw Coffee', 'raw_coffee', 'kg', 96, 32),
            'roasted_house' => $this->upsertProduct($branch->id, "{$prefix}-ROAST-HOUSE", 'House Blend Roasted Coffee', 'roasted_coffee', 'kg', 64, 20),
            'roasted_dark' => $this->upsertProduct($branch->id, "{$prefix}-ROAST-DARK", 'Dark Espresso Roast', 'roasted_coffee', 'kg', 18, 22),
            'cups' => $this->upsertProduct($branch->id, "{$prefix}-PACK-CUPS", 'Branded Paper Cups', 'packaging_material', 'box', 34, 12),
            'milk' => $this->upsertProduct($branch->id, "{$prefix}-BEV-MILK", 'Whole Milk Bottles', 'beverage', 'bottle', 42, 18),
        ];

        $this->upsertMovement($products['raw_brazil']->id, $branch->id, $inventoryUser->id, InventoryMovement::TYPE_IN, InventoryMovement::REASON_SUPPLY, 120, "{$prefix} opening raw Brazil supply.");
        $this->upsertMovement($products['raw_ethiopia']->id, $branch->id, $inventoryUser->id, InventoryMovement::TYPE_IN, InventoryMovement::REASON_SUPPLY, 80, "{$prefix} opening Ethiopia supply.");
        $this->upsertMovement($products['roasted_house']->id, $branch->id, $inventoryUser->id, InventoryMovement::TYPE_ADJUSTMENT, InventoryMovement::REASON_MANUAL_ADJUSTMENT, 64, "{$prefix} roasted stock count.");
        $this->upsertMovement($products['roasted_dark']->id, $branch->id, $inventoryUser->id, InventoryMovement::TYPE_ADJUSTMENT, InventoryMovement::REASON_MANUAL_ADJUSTMENT, 18, "{$prefix} low stock demo item.");
        $this->upsertMovement($products['cups']->id, $branch->id, $inventoryUser->id, InventoryMovement::TYPE_IN, InventoryMovement::REASON_SUPPLY, 24, "{$prefix} packaging supply.");
        $this->upsertMovement($products['milk']->id, $branch->id, $inventoryUser->id, InventoryMovement::TYPE_IN, InventoryMovement::REASON_SUPPLY, 36, "{$prefix} beverage supply.");

        return $products;
    }

    private function seedRoastingForBranch(string $branchCode, array $team, array $products): void
    {
        $branch = $team['branch'];
        $manager = $team['manager'];
        $roasters = $team['roasters'];

        $requests = [
            [
                'code' => "ROAST-{$branchCode}-001",
                'product' => $products['roasted_house'],
                'assigned' => $roasters[0],
                'quantity' => 28,
                'priority' => RoastingRequest::PRIORITY_MEDIUM,
                'status' => RoastingRequest::STATUS_COMPLETED,
                'notes' => "{$branchCode} weekly house blend batch.",
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDay(),
            ],
            [
                'code' => "ROAST-{$branchCode}-002",
                'product' => $products['roasted_dark'],
                'assigned' => $roasters[1] ?? $roasters[0],
                'quantity' => 16,
                'priority' => RoastingRequest::PRIORITY_URGENT,
                'status' => RoastingRequest::STATUS_IN_PROGRESS,
                'notes' => "{$branchCode} espresso refill for active orders.",
                'started_at' => now()->subHours(5),
                'completed_at' => null,
            ],
            [
                'code' => "ROAST-{$branchCode}-003",
                'product' => $products['roasted_house'],
                'assigned' => $roasters[0],
                'quantity' => 22,
                'priority' => RoastingRequest::PRIORITY_LOW,
                'status' => RoastingRequest::STATUS_ASSIGNED,
                'notes' => "{$branchCode} scheduled afternoon roast.",
                'started_at' => null,
                'completed_at' => null,
            ],
            [
                'code' => "ROAST-{$branchCode}-004",
                'product' => $products['raw_ethiopia'],
                'assigned' => $roasters[0],
                'quantity' => 12,
                'priority' => RoastingRequest::PRIORITY_LOW,
                'status' => RoastingRequest::STATUS_CANCELLED,
                'notes' => "{$branchCode} cancelled demo request.",
                'started_at' => null,
                'completed_at' => null,
            ],
        ];

        foreach ($requests as $requestData) {
            $request = $this->upsertRoastingRequest(
                code: $requestData['code'],
                productId: $requestData['product']->id,
                branchId: $branch->id,
                createdBy: $manager->id,
                assignedTo: $requestData['assigned']->id,
                quantity: $requestData['quantity'],
                priority: $requestData['priority'],
                status: $requestData['status'],
                notes: $requestData['notes'],
                startedAt: $requestData['started_at'],
                completedAt: $requestData['completed_at'],
            );

            $this->syncRoastingLogs($request, $manager->id, $requestData['assigned']->id);
        }
    }

    private function seedDistributionForBranch(string $branchCode, array $team, array $products): void
    {
        $branch = $team['branch'];
        $manager = $team['manager'];
        $drivers = $team['distribution'];

        $shipments = [
            ['code' => "SHIP-{$branchCode}-001", 'product' => $products['roasted_house'], 'driver' => $drivers[0], 'quantity' => 18, 'destination' => 'Downtown Cafe', 'recipient' => 'Ahmad Barista', 'status' => DistributionShipment::STATUS_DELIVERED, 'notes' => "{$branchCode} delivered shipment."],
            ['code' => "SHIP-{$branchCode}-002", 'product' => $products['roasted_dark'], 'driver' => $drivers[1] ?? $drivers[0], 'quantity' => 10, 'destination' => 'North Market Store', 'recipient' => 'Sara Buyer', 'status' => DistributionShipment::STATUS_READY_FOR_PICKUP, 'notes' => "{$branchCode} ready for pickup."],
            ['code' => "SHIP-{$branchCode}-003", 'product' => $products['cups'], 'driver' => $drivers[0], 'quantity' => 5, 'destination' => 'Partner Kiosk', 'recipient' => 'Khaled Partner', 'status' => DistributionShipment::STATUS_TRANSFERRED, 'notes' => "{$branchCode} transferred shipment."],
            ['code' => "SHIP-{$branchCode}-004", 'product' => $products['milk'], 'driver' => $drivers[0], 'quantity' => 8, 'destination' => 'Campus Cafe', 'recipient' => 'Mona Cafe', 'status' => DistributionShipment::STATUS_PENDING, 'notes' => "{$branchCode} pending assignment demo."],
        ];

        foreach ($shipments as $shipmentData) {
            $shipment = $this->upsertShipment(
                code: $shipmentData['code'],
                productId: $shipmentData['product']->id,
                branchId: $branch->id,
                createdBy: $manager->id,
                assignedTo: $shipmentData['driver']->id,
                quantity: $shipmentData['quantity'],
                destination: $shipmentData['destination'],
                recipientName: $shipmentData['recipient'],
                status: $shipmentData['status'],
                notes: $shipmentData['notes'],
            );

            if ($shipmentData['status'] === DistributionShipment::STATUS_DELIVERED) {
                $this->upsertMovement($shipmentData['product']->id, $branch->id, $shipmentData['driver']->id, InventoryMovement::TYPE_OUT, InventoryMovement::REASON_SHIPMENT, $shipmentData['quantity'], "{$shipment->shipment_code} delivered stock out.");
            }
        }
    }

    private function seedNotificationsForBranch(string $branchCode, array $team, array $products): void
    {
        $branch = $team['branch'];
        $manager = $team['manager'];
        $roaster = $team['roasters'][0];
        $inventory = $team['inventory'][0];
        $driver = $team['distribution'][0];

        $this->upsertNotification($manager->id, 'general', "{$branchCode} dashboard ready", 'Demo branch activity is ready for manager review.', false, Branch::class, $branch->id);
        $this->upsertNotification($manager->id, 'low_stock', "{$branchCode} low roasted stock", 'Dark espresso roast is below the preferred minimum quantity.', false, Product::class, $products['roasted_dark']->id);
        $this->upsertNotification($roaster->id, 'roasting_update', "{$branchCode} roasting assignment", 'You have active roasting work in this branch demo.', false, RoastingRequest::class, null);
        $this->upsertNotification($inventory->id, 'low_stock', "{$branchCode} inventory check", 'Please review demo low-stock items for the branch.', true, Product::class, $products['roasted_dark']->id);
        $this->upsertNotification($driver->id, 'shipment_update', "{$branchCode} shipment ready", 'A shipment is ready for pickup in your delivery queue.', false, DistributionShipment::class, null);
    }

    private function upsertActiveUser(
        string $name,
        string $email,
        string $phone,
        int $roleId,
        int $branchId,
        ?int $approvedBy
    ): User {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make(self::PASSWORD),
                'role_id' => $roleId,
                'branch_id' => $branchId,
                'is_active' => true,
                'approved_at' => now()->subDays(7),
                'approved_by' => $approvedBy,
            ]
        );
    }

    private function upsertEmployeeRequest(
        string $name,
        string $email,
        string $phone,
        int $branchId,
        string $status = EmployeeRequest::STATUS_PENDING,
        ?int $reviewedBy = null,
        ?string $rejectionReason = null
    ): void {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make(self::PASSWORD),
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
                'status' => $status,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $status === EmployeeRequest::STATUS_PENDING ? null : now()->subDays(2),
                'rejection_reason' => $rejectionReason,
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
        $statusActors = [
            RoastingRequest::STATUS_PENDING => $managerId,
            RoastingRequest::STATUS_ASSIGNED => $managerId,
            RoastingRequest::STATUS_IN_PROGRESS => $employeeId,
            RoastingRequest::STATUS_COMPLETED => $employeeId,
            RoastingRequest::STATUS_CANCELLED => $managerId,
        ];

        foreach (RoastingRequest::STATUSES as $status) {
            if (! $this->shouldWriteRoastingLog($request->status, $status)) {
                continue;
            }

            RoastingStatusLog::updateOrCreate(
                ['roasting_request_id' => $request->id, 'status' => $status],
                [
                    'changed_by' => $statusActors[$status],
                    'note' => "Seeded {$status} roasting activity.",
                ]
            );
        }
    }

    private function shouldWriteRoastingLog(string $currentStatus, string $logStatus): bool
    {
        $order = [
            RoastingRequest::STATUS_PENDING => 1,
            RoastingRequest::STATUS_ASSIGNED => 2,
            RoastingRequest::STATUS_IN_PROGRESS => 3,
            RoastingRequest::STATUS_COMPLETED => 4,
            RoastingRequest::STATUS_CANCELLED => 5,
        ];

        if ($currentStatus === RoastingRequest::STATUS_CANCELLED) {
            return in_array($logStatus, [RoastingRequest::STATUS_PENDING, RoastingRequest::STATUS_CANCELLED], true);
        }

        return $order[$logStatus] <= $order[$currentStatus];
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
