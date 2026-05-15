<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoastingRequestInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Role $managerRole;
    private Role $roastingRole;
    private User $manager;
    private User $employee;
    private Product $rawProduct;
    private Product $outputProduct;

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

        $this->roastingRole = Role::create([
            'name' => 'Roasting Employee',
            'slug' => Role::ROASTING_EMPLOYEE,
        ]);

        $this->manager = User::factory()->create([
            'role_id' => $this->managerRole->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->employee = User::factory()->create([
            'role_id' => $this->roastingRole->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->rawProduct = Product::create([
            'branch_id' => $this->branch->id,
            'name' => 'Raw Pistachio',
            'category' => 'raw_nuts',
            'unit' => 'kg',
            'quantity' => 200,
            'minimum_quantity' => 10,
            'is_active' => true,
        ]);

        $this->outputProduct = Product::create([
            'branch_id' => $this->branch->id,
            'name' => 'Roasted Pistachio',
            'category' => 'roasted_nuts',
            'unit' => 'kg',
            'quantity' => 20,
            'minimum_quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_create_roasting_request_does_not_change_raw_or_output_quantity(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/roasting/requests', $this->roastingPayload(quantity: 50))
            ->assertCreated();

        $this->assertSame(200.0, (float) $this->rawProduct->fresh()->quantity);
        $this->assertSame(20.0, (float) $this->outputProduct->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_start_deducts_raw_quantity_once(): void
    {
        $roastingRequest = $this->createAssignedRoastingRequest(quantity: 50);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/start")
            ->assertOk();

        $this->assertSame(150.0, (float) $this->rawProduct->fresh()->quantity);
        $this->assertSame(20.0, (float) $this->outputProduct->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->rawProduct->id,
            'branch_id' => $this->branch->id,
            'performed_by' => $this->employee->id,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'reason' => InventoryMovement::REASON_MANUAL_ADJUSTMENT,
            'quantity' => 50,
            'reference_type' => 'roasting_request',
            'reference_id' => $roastingRequest->id,
        ]);
    }

    public function test_repeated_start_does_not_deduct_twice(): void
    {
        $roastingRequest = $this->createAssignedRoastingRequest(quantity: 50);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/start")
            ->assertOk();

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/start")
            ->assertOk();

        $this->assertSame(150.0, (float) $this->rawProduct->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::where('reference_id', $roastingRequest->id)->where('movement_type', InventoryMovement::TYPE_OUT)->count());
    }

    public function test_start_with_insufficient_stock_returns_validation_error_and_does_not_change_status(): void
    {
        $roastingRequest = $this->createAssignedRoastingRequest(quantity: 250);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity'])
            ->assertJsonPath('errors.quantity.0', 'الكمية المتوفرة من المادة الخام غير كافية.');

        $this->assertSame(200.0, (float) $this->rawProduct->fresh()->quantity);
        $this->assertSame(RoastingRequest::STATUS_ASSIGNED, $roastingRequest->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_complete_increases_output_product_only(): void
    {
        $roastingRequest = $this->createAssignedRoastingRequest(quantity: 50);
        $this->start($roastingRequest);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/complete", [
                'final_output_kg' => 42,
            ])
            ->assertOk();

        $this->assertSame(150.0, (float) $this->rawProduct->fresh()->quantity);
        $this->assertSame(62.0, (float) $this->outputProduct->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->outputProduct->id,
            'movement_type' => InventoryMovement::TYPE_IN,
            'reason' => InventoryMovement::REASON_MANUAL_ADJUSTMENT,
            'quantity' => 42,
            'reference_type' => 'roasting_request',
            'reference_id' => $roastingRequest->id,
        ]);
    }

    public function test_repeated_complete_does_not_add_twice(): void
    {
        $roastingRequest = $this->createAssignedRoastingRequest(quantity: 50);
        $this->start($roastingRequest);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/complete", [
                'final_output_kg' => 42,
            ])
            ->assertOk();

        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/complete", [
                'final_output_kg' => 42,
            ])
            ->assertOk();

        $this->assertSame(62.0, (float) $this->outputProduct->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::where('reference_id', $roastingRequest->id)->where('movement_type', InventoryMovement::TYPE_IN)->count());
    }

    private function start(RoastingRequest $roastingRequest): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/roasting/my-tasks/{$roastingRequest->id}/start")
            ->assertOk();
    }

    private function createAssignedRoastingRequest(float $quantity): RoastingRequest
    {
        return RoastingRequest::create([
            'code' => 'ROAST-'.str_replace('.', '-', (string) $quantity).'-'.uniqid(),
            'product_id' => $this->rawProduct->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->manager->id,
            'assigned_to' => $this->employee->id,
            'quantity' => $quantity,
            'priority' => RoastingRequest::PRIORITY_MEDIUM,
            'status' => RoastingRequest::STATUS_ASSIGNED,
            'notes' => $this->notes(),
        ]);
    }

    private function roastingPayload(float $quantity): array
    {
        return [
            'code' => 'ROAST-TEST-'.uniqid(),
            'product_id' => $this->rawProduct->id,
            'quantity' => $quantity,
            'priority' => RoastingRequest::PRIORITY_MEDIUM,
            'notes' => $this->notes(),
        ];
    }

    private function notes(): string
    {
        return json_encode([
            'type' => 'product_processing_plan',
            'green_product_id' => $this->rawProduct->id,
            'output_product_id' => $this->outputProduct->id,
            'output_product_name' => $this->outputProduct->name,
            'raw_quantity_kg' => 50,
            'expected_loss_percentage' => 12.5,
            'created_at' => now()->toISOString(),
        ]);
    }
}
