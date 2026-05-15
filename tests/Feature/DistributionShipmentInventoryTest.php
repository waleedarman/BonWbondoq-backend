<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DistributionShipment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionShipmentInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Role $managerRole;
    private Role $inventoryRole;
    private Role $distributionRole;
    private User $manager;
    private User $inventoryEmployee;
    private User $employee;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $this->managerRole = Role::create([
            'name' => 'Manager',
            'slug' => Role::MANAGER,
        ]);

        $this->inventoryRole = Role::create([
            'name' => 'Inventory Employee',
            'slug' => Role::INVENTORY_EMPLOYEE,
        ]);

        $this->distributionRole = Role::create([
            'name' => 'Distribution Employee',
            'slug' => Role::DISTRIBUTION_EMPLOYEE,
        ]);

        $this->manager = User::factory()->create([
            'role_id' => $this->managerRole->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->inventoryEmployee = User::factory()->create([
            'role_id' => $this->inventoryRole->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->employee = User::factory()->create([
            'role_id' => $this->distributionRole->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'name' => 'Pistachio',
            'category' => 'raw_nuts',
            'unit' => 'kg',
            'quantity' => 200,
            'minimum_quantity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_create_shipment_does_not_change_product_quantity(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/distribution/shipments', $this->shipmentPayload(quantity: 50))
            ->assertCreated();

        $this->assertSame(200.0, (float) $this->product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_inventory_employee_can_list_pending_preparation_tasks(): void
    {
        $pendingShipment = $this->createPendingShipment(quantity: 25);
        $this->createShipment(quantity: 10);

        $this->actingAs($this->inventoryEmployee, 'sanctum')
            ->getJson('/api/distribution/preparation-tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $pendingShipment->id)
            ->assertJsonPath('data.data.0.status', DistributionShipment::STATUS_PENDING);
    }

    public function test_manager_can_update_pending_unprepared_shipment(): void
    {
        $shipment = $this->createPendingShipment(quantity: 25);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/distribution/shipments/{$shipment->id}", [
                'product_id' => $this->product->id,
                'quantity' => 30,
                'destination' => 'Tulkarm',
                'recipient_name' => 'Updated Recipient',
                'assigned_to' => $this->employee->id,
                'inventory_assigned_to' => $this->inventoryEmployee->id,
                'notes' => 'Updated before preparation.',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity', '30.00')
            ->assertJsonPath('data.destination', 'Tulkarm')
            ->assertJsonPath('data.recipient_name', 'Updated Recipient')
            ->assertJsonPath('data.assigned_to', $this->employee->id)
            ->assertJsonPath('data.inventory_assigned_to', $this->inventoryEmployee->id);

        $this->assertDatabaseHas('distribution_shipments', [
            'id' => $shipment->id,
            'quantity' => 30,
            'destination' => 'Tulkarm',
            'recipient_name' => 'Updated Recipient',
            'assigned_to' => $this->employee->id,
            'inventory_assigned_to' => $this->inventoryEmployee->id,
            'status' => DistributionShipment::STATUS_PENDING,
        ]);
        $this->assertSame(200.0, (float) $this->product->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_manager_cannot_update_prepared_shipment_details(): void
    {
        $shipment = $this->createShipment(quantity: 25);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/distribution/shipments/{$shipment->id}", [
                'product_id' => $this->product->id,
                'quantity' => 30,
                'destination' => 'Tulkarm',
                'recipient_name' => 'Updated Recipient',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_inventory_employee_can_prepare_pending_shipment_and_deduct_stock_once(): void
    {
        $shipment = $this->createPendingShipment(quantity: 50);

        $this->actingAs($this->inventoryEmployee, 'sanctum')
            ->postJson("/api/distribution/preparation-tasks/{$shipment->id}/prepare", [
                'notes' => 'Prepared by inventory.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DistributionShipment::STATUS_READY_FOR_PICKUP);

        $this->assertSame(150.0, (float) $this->product->fresh()->quantity);
        $this->assertSame(DistributionShipment::STATUS_READY_FOR_PICKUP, $shipment->fresh()->status);
        $this->assertNotNull($shipment->fresh()->prepared_at);
        $this->assertSame(1, InventoryMovement::where('reference_id', $shipment->id)->where('movement_type', InventoryMovement::TYPE_OUT)->count());
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'performed_by' => $this->inventoryEmployee->id,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'reference_type' => 'distribution_shipment',
            'reference_id' => $shipment->id,
        ]);

        $this->actingAs($this->inventoryEmployee, 'sanctum')
            ->postJson("/api/distribution/preparation-tasks/{$shipment->id}/prepare")
            ->assertOk();

        $this->assertSame(150.0, (float) $this->product->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::where('reference_id', $shipment->id)->where('movement_type', InventoryMovement::TYPE_OUT)->count());
    }

    public function test_inventory_employee_still_cannot_access_manager_distribution_shipments_endpoint(): void
    {
        $this->actingAs($this->inventoryEmployee, 'sanctum')
            ->getJson('/api/distribution/shipments')
            ->assertForbidden();
    }

    public function test_transfer_deducts_quantity_once(): void
    {
        $shipment = $this->createShipment(quantity: 50);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/distribution/my-shipments/{$shipment->id}/transfer")
            ->assertOk();

        $this->assertSame(150.0, (float) $this->product->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'performed_by' => $this->employee->id,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'reason' => InventoryMovement::REASON_MANUAL_ADJUSTMENT,
            'quantity' => 50,
            'reference_type' => 'distribution_shipment',
            'reference_id' => $shipment->id,
        ]);
    }

    public function test_repeated_transfer_does_not_deduct_twice(): void
    {
        $shipment = $this->createShipment(quantity: 50);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/distribution/my-shipments/{$shipment->id}/transfer")
            ->assertOk();

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/distribution/my-shipments/{$shipment->id}/transfer")
            ->assertOk();

        $this->assertSame(150.0, (float) $this->product->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::where('reference_id', $shipment->id)->where('movement_type', InventoryMovement::TYPE_OUT)->count());
    }

    public function test_insufficient_stock_returns_validation_error_and_does_not_change_status(): void
    {
        $shipment = $this->createShipment(quantity: 250);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/distribution/my-shipments/{$shipment->id}/transfer")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity'])
            ->assertJsonPath('errors.quantity.0', 'الكمية المتوفرة في المخزون غير كافية.');

        $this->assertSame(200.0, (float) $this->product->fresh()->quantity);
        $this->assertSame(DistributionShipment::STATUS_READY_FOR_PICKUP, $shipment->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_cancel_transfer_restores_quantity_when_transferred(): void
    {
        $shipment = $this->createShipment(quantity: 50);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/distribution/my-shipments/{$shipment->id}/transfer")
            ->assertOk();

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/distribution/my-shipments/{$shipment->id}/cancel-transfer")
            ->assertOk();

        $this->assertSame(200.0, (float) $this->product->fresh()->quantity);
        $this->assertSame(DistributionShipment::STATUS_CANCELLED, $shipment->fresh()->status);
        $this->assertSame(1, InventoryMovement::where('reference_id', $shipment->id)->where('movement_type', InventoryMovement::TYPE_IN)->count());
    }

    private function createPendingShipment(float $quantity): DistributionShipment
    {
        return DistributionShipment::create([
            'shipment_code' => 'SHIP-PENDING-'.str_replace('.', '-', (string) $quantity).'-'.uniqid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->manager->id,
            'quantity' => $quantity,
            'destination' => 'Ramallah',
            'recipient_name' => 'Recipient',
            'status' => DistributionShipment::STATUS_PENDING,
        ]);
    }

    private function createShipment(float $quantity): DistributionShipment
    {
        return DistributionShipment::create([
            'shipment_code' => 'SHIP-'.str_replace('.', '-', (string) $quantity).'-'.uniqid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->manager->id,
            'assigned_to' => $this->employee->id,
            'quantity' => $quantity,
            'destination' => 'Ramallah',
            'recipient_name' => 'Recipient',
            'status' => DistributionShipment::STATUS_READY_FOR_PICKUP,
            'prepared_at' => now(),
        ]);
    }

    private function shipmentPayload(float $quantity): array
    {
        return [
            'shipment_code' => 'SHIP-TEST-'.uniqid(),
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'destination' => 'Ramallah',
            'recipient_name' => 'Recipient',
            'assigned_to' => $this->employee->id,
        ];
    }
}
