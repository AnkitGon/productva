# 11 — Testing

## Testing Stack

| Tool | Purpose |
|------|---------|
| **Pest v4** | Test framework (syntax on top of PHPUnit v12) |
| **PHPUnit** | Underlying runner |
| **SQLite in-memory** | Test database (default) |
| **Factories** | Eloquent model factories for test data |

---

## Running Tests

```bash
# Run all tests (compact output)
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/PlantControllerTest.php

# Run tests matching a filter
php artisan test --compact --filter=plant_cannot_be_deleted

# Run with coverage (requires Xdebug or PCOV)
php artisan test --coverage
```

---

## Test Database

Tests use an **in-memory SQLite** database by default (configured in `phpunit.xml`). Each test runs migrations fresh via the `RefreshDatabase` trait.

```xml
<!-- phpunit.xml -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

---

## Test Structure

```
tests/
├── Feature/           # HTTP / integration tests (most tests go here)
│   ├── Auth/          # Login, registration, 2FA tests
│   ├── Plant/
│   ├── Product/
│   ├── Inventory/
│   ├── Bom/
│   ├── Routing/
│   └── ...
└── Unit/              # Pure PHP logic tests
    ├── Models/
    └── Services/
```

---

## Writing Tests

### Create a new test

```bash
# Feature test
php artisan make:test --pest PlantDeletionTest

# Unit test
php artisan make:test --pest --unit PlantModelTest
```

### Example Feature Test

```php
<?php

use App\Models\Organization;
use App\Models\Plant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('prevents deleting a plant that has warehouses', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $plant = Plant::factory()->create(['organization_id' => $org->id]);
    
    // Create a second plant so we don't hit the "last plant" guard
    Plant::factory()->create(['organization_id' => $org->id]);
    
    // Attach a warehouse to the plant
    \App\Models\Warehouse::factory()->create(['plant_id' => $plant->id, 'organization_id' => $org->id]);
    
    $response = $this
        ->actingAs($user)
        ->delete(route('plants.destroy', $plant));
    
    $response->assertRedirect();
    expect(Plant::find($plant->id))->not->toBeNull(); // plant still exists
});

it('allows deleting an empty plant', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    
    $keepPlant = Plant::factory()->create(['organization_id' => $org->id]);
    $deletePlant = Plant::factory()->create(['organization_id' => $org->id]);
    
    $response = $this
        ->actingAs($user)
        ->delete(route('plants.destroy', $deletePlant));
    
    $response->assertRedirect();
    expect(Plant::withTrashed()->find($deletePlant->id)->deleted_at)->not->toBeNull();
});
```

### Example Unit Test

```php
<?php

use App\Models\Plant;
use App\Models\Warehouse;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('detects blocking dependencies correctly', function () {
    $plant = Plant::factory()->create();
    
    expect($plant->hasBlockingDependencies())->toBeFalse();
    
    Warehouse::factory()->create(['plant_id' => $plant->id]);
    
    expect($plant->hasBlockingDependencies())->toBeTrue();
});
```

---

## Test Coverage Map

### Feature tests by module

| Test file | Covers |
|-----------|--------|
| `PlantDeletionGuardTest` | Plant delete guards, one-plant minimum |
| `PlantManagementTest` | Plant CRUD, default plant |
| `DepartmentManagementTest` | Department CRUD, delete when in use |
| `EmployeeManagementTest` | Employee CRUD, validation |
| `EmployeeProfileDashboardTest` | Profile tabs (Overview, Assignments, Permissions) |
| `EmployeeBulkActionsTest` | Bulk activate/deactivate/archive |
| `EmployeeReportingManagerTest` | Manager assignment |
| `ProductManagementTest` | CRUD, filters, stock levels, track-inventory-off |
| `ProductBulkActionsTest` | Bulk category/warehouse/status/delete/export |
| `ProductCategoryManagementTest` | Delete guards, name uniqueness |
| `UnitOfMeasureManagementTest` | Name uniqueness, product count links |
| `BomManagementTest` | BOM CRUD, circular/duplicate guards |
| `RoutingManagementTest` | Release lock, sequences |
| `WarehouseManagementTest` | Warehouse CRUD |
| `WarehouseLocationManagementTest` | Location hierarchy, unique codes |
| `InventoryManagementTest` | Adjust, transfer, negative stock block, available qty |
| `InventoryTransactionManagementTest` | Ledger immutability |
| `SoftDeleteUniqueTest` | Soft-delete-aware unique indexes |
| `SetupWizardTest` | Factory setup wizard |
| `PermissionBoundaryTest` | RBAC boundaries |

Run all: `php artisan test --compact`

### Remaining high-priority tests

| Module | Test scenario | Priority |
|--------|--------------|----------|
| Inventory | Transfer blocked when quantity > available | 🔴 Critical |
| Product | `hasBlockingDependencies()` includes inventory balances | 🟠 High |
| Product | Opening stock posts to ledger (when implemented) | 🟠 High |
| BOM | Active BOM edit blocked (if rule adopted) | 🟡 Medium |
| Warehouse | Delete blocked when inventory exists | 🟠 High |
| Employee | Circular manager chain rejected | 🟡 Medium |

---

## Testing Checklist Before Deployment

- [ ] All existing tests pass: `php artisan test --compact`
- [ ] No failing migrations: `php artisan migrate --pretend`
- [ ] Code is formatted: `php vendor/bin/pint --test`
- [ ] Static analysis passes: `php artisan larastan:analyse` (if configured)
- [ ] Manual smoke test: Login, create product, adjust stock, view inventory

---

## Factories Reference

| Model | Factory | Key States |
|-------|---------|------------|
| Organization | `OrganizationFactory` | — |
| Plant | `PlantFactory` | `->inactive()`, `->default()` |
| User | `UserFactory` | `->withOrg($org)`, `->verified()` |
| Department | `DepartmentFactory` | — |
| Employee | `EmployeeFactory` | `->active()`, `->terminated()` |
| Product | `ProductFactory` | `->rawMaterial()`, `->finishedGood()`, `->withLotTracking()` |
| BomHeader | `BomHeaderFactory` | `->active()`, `->draft()` |
| RoutingHeader | `RoutingHeaderFactory` | `->released()`, `->draft()` |
| Warehouse | `WarehouseFactory` | — |
| Inventory | `InventoryFactory` | — |
