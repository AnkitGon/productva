<?php

namespace Tests\Feature;

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\RoutingHeader;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseType;
use App\Services\InventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Organization $org;

    protected Plant $plant;

    protected Warehouse $warehouse;

    protected WarehouseLocation $location;

    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::create(['name' => 'Advanced Test Org']);
        $this->plant = Plant::create([
            'organization_id' => $this->org->id,
            'code' => 'PL-ADV',
            'slug' => 'plant-adv',
            'name' => 'Advanced Plant',
        ]);

        $this->adminUser = User::factory()->create([
            'organization_id' => $this->org->id,
            'active_plant_id' => $this->plant->id,
        ]);

        WarehouseType::ensureDefaultsFor($this->org->id);
        $typeId = WarehouseType::where('organization_id', $this->org->id)->value('id');

        $this->warehouse = Warehouse::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'warehouse_type_id' => $typeId,
            'code' => 'WH-ADV',
            'name' => 'Advanced Warehouse',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->location = WarehouseLocation::create([
            'organization_id' => $this->org->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'Bin',
            'code' => 'BIN-ADV',
            'name' => 'Advanced Bin',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->category = ProductCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'CAT-ADV',
            'name' => 'Advanced Category',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);
    }

    public function test_uom_conversion_factor_on_transactions(): void
    {
        // Base UOM: Pieces
        $pcsUom = UnitOfMeasure::create([
            'organization_id' => $this->org->id,
            'code' => 'PCS',
            'name' => 'Pieces',
            'type' => 'Count',
            'symbol' => 'pcs',
            'decimal_places' => 0,
            'status' => 'Active',
        ]);

        // Box of 12 UOM
        $boxUom = UnitOfMeasure::create([
            'organization_id' => $this->org->id,
            'code' => 'BOX12',
            'name' => 'Box of 12',
            'type' => 'Count',
            'symbol' => 'box12',
            'decimal_places' => 0,
            'status' => 'Active',
            'base_unit_id' => $pcsUom->id,
            'conversion_factor' => 12.0000,
        ]);

        $product = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'CONV-TEST',
            'name' => 'Conversion Product',
            'category_id' => $this->category->id,
            'uom_id' => $pcsUom->id, // Base unit
            'type' => 'Raw Material',
            'status' => 'Active',
            'track_inventory' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $service = app(InventoryService::class);

        // Adjust with 2 Boxes (which should convert to 24 Pieces)
        $inventory = $service->adjust($this->adminUser, [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'new_quantity' => 2,
            'uom_id' => $boxUom->id, // 2 Boxes
        ]);

        // Expect 24.0 in on-hand quantity (base UOM)
        $this->assertEquals(24.0, (float) $inventory->quantity_on_hand);
    }

    public function test_phantom_item_bom_explosion_and_uom_conversion(): void
    {
        $uom = UnitOfMeasure::create([
            'organization_id' => $this->org->id,
            'code' => 'EA',
            'name' => 'Each',
            'type' => 'Count',
            'symbol' => 'ea',
            'decimal_places' => 0,
            'status' => 'Active',
        ]);

        $boxUom = UnitOfMeasure::create([
            'organization_id' => $this->org->id,
            'code' => 'BX',
            'name' => 'Box of 10',
            'type' => 'Count',
            'symbol' => 'bx',
            'decimal_places' => 0,
            'status' => 'Active',
            'base_unit_id' => $uom->id,
            'conversion_factor' => 10.0000,
        ]);

        // Final product
        $final = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'FINAL-PROD',
            'name' => 'Final Product',
            'category_id' => $this->category->id,
            'uom_id' => $uom->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        // Phantom intermediate assembly
        $phantom = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'PHANTOM-ASM',
            'name' => 'Phantom Assembly',
            'category_id' => $this->category->id,
            'uom_id' => $uom->id,
            'type' => 'Raw Material',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        // Raw Material
        $raw = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'RAW-MAT',
            'name' => 'Raw Material',
            'category_id' => $this->category->id,
            'uom_id' => $uom->id,
            'type' => 'Raw Material',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        // BOM for Final Product: requires 2 units of Phantom Assembly
        $finalBom = BomHeader::create([
            'organization_id' => $this->org->id,
            'product_id' => $final->id,
            'version' => '1.0',
            'status' => 'Active',
        ]);

        BomItem::create([
            'bom_header_id' => $finalBom->id,
            'component_product_id' => $phantom->id,
            'quantity' => 2.0,
            'uom_id' => $uom->id,
            'is_phantom' => true, // Flagged as phantom
        ]);

        // BOM for Phantom Product: requires 1 Box (10 EAs) of Raw Material
        $phantomBom = BomHeader::create([
            'organization_id' => $this->org->id,
            'product_id' => $phantom->id,
            'version' => '1.0',
            'status' => 'Active',
        ]);

        BomItem::create([
            'bom_header_id' => $phantomBom->id,
            'component_product_id' => $raw->id,
            'quantity' => 1.0,
            'uom_id' => $boxUom->id, // Specified in BOX of 10
            'is_phantom' => false,
        ]);

        // Explode Final Product BOM for 1 unit
        // Since the phantom requires 1 Box (10 Base EAs), and we require 2 Phantoms:
        // Expected Raw Material required: 2 * 10 = 20 EAs
        $explosion = $finalBom->explode(1.0);

        $this->assertCount(1, $explosion);
        $this->assertEquals($raw->id, $explosion[0]['product_id']);
        $this->assertEquals(20.0, (float) $explosion[0]['quantity']);
    }

    public function test_alternate_routing_fields(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'ROUTE-TEST',
            'name' => 'Routing Product',
            'category_id' => $this->category->id,
            'uom_id' => UnitOfMeasure::firstOrCreate([
                'organization_id' => $this->org->id,
                'code' => 'EA',
                'name' => 'Each',
                'type' => 'Count',
                'symbol' => 'ea',
            ])->id,
            'type' => 'Finished Good',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        // Create primary routing
        $primary = RoutingHeader::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'version' => '1.0',
            'is_primary' => true,
            'routing_name' => 'Standard Production',
            'status' => 'Released',
        ]);

        // Create alternate routing
        $alternate = RoutingHeader::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'version' => '1.0-ALT',
            'is_primary' => false,
            'routing_name' => 'Alternate Line B',
            'status' => 'Released',
        ]);

        $this->assertTrue($primary->is_primary);
        $this->assertFalse($alternate->is_primary);
        $this->assertEquals('Alternate Line B', $alternate->routing_name);
    }

    public function test_incoming_outgoing_quantity_fields(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'IO-TEST',
            'name' => 'IO Product',
            'category_id' => $this->category->id,
            'uom_id' => UnitOfMeasure::firstOrCreate([
                'organization_id' => $this->org->id,
                'code' => 'EA',
                'name' => 'Each',
                'type' => 'Count',
                'symbol' => 'ea',
            ])->id,
            'type' => 'Raw Material',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $inventory = Inventory::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
            'quantity_incoming' => 5,
            'quantity_outgoing' => 3,
        ]);

        $this->assertEquals(5.0, (float) $inventory->quantity_incoming);
        $this->assertEquals(3.0, (float) $inventory->quantity_outgoing);
    }

    public function test_transfer_exceeds_available_throws_validation_exception(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'AVAIL-TEST',
            'name' => 'Avail Product',
            'category_id' => $this->category->id,
            'uom_id' => UnitOfMeasure::firstOrCreate([
                'organization_id' => $this->org->id,
                'code' => 'EA',
                'name' => 'Each',
                'type' => 'Count',
                'symbol' => 'ea',
            ])->id,
            'type' => 'Raw Material',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $inventory = Inventory::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 4, // 6 available
        ]);

        $destLocation = WarehouseLocation::create([
            'organization_id' => $this->org->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'Bin',
            'code' => 'BIN-DEST',
            'name' => 'Dest Bin',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $service = app(InventoryService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Transfer quantity cannot exceed available quantity (6).');

        $service->transfer($this->adminUser, [
            'product_id' => $product->id,
            'from_warehouse_id' => $this->warehouse->id,
            'from_location_id' => $this->location->id,
            'to_warehouse_id' => $this->warehouse->id,
            'to_location_id' => $destLocation->id,
            'quantity' => 7,
        ]);
    }

    public function test_stock_movement_in_deleted_warehouse_throws_validation_exception(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'DEL-WH-TEST',
            'name' => 'Del Product',
            'category_id' => $this->category->id,
            'uom_id' => UnitOfMeasure::firstOrCreate([
                'organization_id' => $this->org->id,
                'code' => 'EA',
                'name' => 'Each',
                'type' => 'Count',
                'symbol' => 'ea',
            ])->id,
            'type' => 'Raw Material',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        // Soft delete the warehouse
        $this->warehouse->delete();

        $service = app(InventoryService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot adjust stock in a deleted warehouse.');

        $service->adjust($this->adminUser, [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'new_quantity' => 5,
        ]);
    }

    public function test_inter_plant_transfer(): void
    {
        $product = Product::create([
            'organization_id' => $this->org->id,
            'sku' => 'INTER-PLANT',
            'name' => 'Inter Product',
            'category_id' => $this->category->id,
            'uom_id' => UnitOfMeasure::firstOrCreate([
                'organization_id' => $this->org->id,
                'code' => 'EA',
                'name' => 'Each',
                'type' => 'Count',
                'symbol' => 'ea',
            ])->id,
            'type' => 'Raw Material',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        // Plant B
        $plantB = Plant::create([
            'organization_id' => $this->org->id,
            'code' => 'PL-B',
            'slug' => 'plant-b',
            'name' => 'Plant B',
        ]);

        $typeId = WarehouseType::where('organization_id', $this->org->id)->value('id');

        $whB = Warehouse::create([
            'organization_id' => $this->org->id,
            'plant_id' => $plantB->id,
            'warehouse_type_id' => $typeId,
            'code' => 'WH-B',
            'name' => 'Warehouse B',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $locB = WarehouseLocation::create([
            'organization_id' => $this->org->id,
            'warehouse_id' => $whB->id,
            'type' => 'Bin',
            'code' => 'BIN-B',
            'name' => 'Bin B',
            'status' => 'Active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        Inventory::create([
            'organization_id' => $this->org->id,
            'plant_id' => $this->plant->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location_id' => $this->location->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
        ]);

        $service = app(InventoryService::class);
        $service->transfer($this->adminUser, [
            'product_id' => $product->id,
            'from_warehouse_id' => $this->warehouse->id,
            'from_location_id' => $this->location->id,
            'to_warehouse_id' => $whB->id,
            'to_location_id' => $locB->id,
            'quantity' => 4,
        ]);

        $fromBalance = Inventory::where('warehouse_id', $this->warehouse->id)->first();
        $toBalance = Inventory::where('warehouse_id', $whB->id)->first();

        $this->assertEquals(6.0, (float) $fromBalance->quantity_on_hand);
        $this->assertEquals(4.0, (float) $toBalance->quantity_on_hand);
        $this->assertEquals($plantB->id, $toBalance->plant_id);
    }

    public function test_cannot_delete_own_account_or_last_admin(): void
    {
        $role = Role::where('slug', 'admin')->first();
        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $this->adminUser->roles()->attach([$role->id, $superAdminRole->id]);

        // Admin User 2
        $admin2 = User::factory()->create([
            'organization_id' => $this->org->id,
        ]);
        $admin2->roles()->attach($role->id);

        $this->actingAs($this->adminUser);

        // 1. Try to delete own account
        $response = $this->delete(route('admin.users.destroy', $this->adminUser));
        $response->assertSessionHasErrors(['user' => 'You cannot delete your own admin account.']);

        // 2. Try to delete last admin of the organization (admin2)
        // Detach admin role from adminUser, leaving only super-admin, so admin2 is the last admin
        $this->adminUser->roles()->detach($role->id);
        $response2 = $this->delete(route('admin.users.destroy', $admin2));
        $response2->assertSessionHasErrors(['user' => 'Cannot delete the last admin user of the organization.']);

        // Attach admin role back so there are 2 admins again
        $this->adminUser->roles()->attach($role->id);

        // Delete admin2 -> should work because adminUser is still an admin
        $response3 = $this->delete(route('admin.users.destroy', $admin2));
        $response3->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $admin2->id]);
    }
}
