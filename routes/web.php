<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

use App\Http\Controllers\ActivePlantController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\SetupWizardController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseLocationController;
use App\Http\Controllers\WarehouseTypeController;
use App\Http\Controllers\WorkCenterController;
use App\Http\Middleware\CheckPermission;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        if ($user->hasRole('super-admin')) {
            return redirect('/admin/dashboard');
        }

        return inertia('admin/dashboard');
    })->middleware(CheckPermission::class.':admin-dashboard')->name('dashboard');

    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/', [SetupWizardController::class, 'index'])->name('index');
        Route::post('dismiss', [SetupWizardController::class, 'complete'])->name('dismiss');
    });

    Route::post('plants/{plant}/activate', ActivePlantController::class)->name('plants.activate');
    Route::post('plants', [PlantController::class, 'store'])
        ->middleware(CheckPermission::class.':plants.create')
        ->name('plants.store');
    Route::put('plants/{plant}', [PlantController::class, 'update'])
        ->middleware(CheckPermission::class.':plants.update')
        ->name('plants.update');
    Route::delete('plants/{plant}', [PlantController::class, 'destroy'])
        ->middleware(CheckPermission::class.':plants.delete')
        ->name('plants.destroy');
    Route::get('organization/users/search', OrganizationUserController::class)->name('organization.users.search');

    // Employees Directory Resource
    Route::middleware(CheckPermission::class.':employees.view')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    });
    Route::middleware(CheckPermission::class.':employees.create')->group(function () {
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    });
    Route::post('employees/bulk', [EmployeeController::class, 'bulk'])->name('employees.bulk');
    Route::middleware(CheckPermission::class.':employees.update')->group(function () {
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });
    Route::middleware(CheckPermission::class.':employees.delete')->group(function () {
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    // Departments Directory Resource
    Route::middleware(CheckPermission::class.':departments.view')->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    });
    Route::middleware(CheckPermission::class.':departments.create')->group(function () {
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    });
    Route::middleware(CheckPermission::class.':departments.update')->group(function () {
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    });
    Route::middleware(CheckPermission::class.':departments.delete')->group(function () {
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });

    // Shifts
    Route::middleware(CheckPermission::class.':shift.view')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    });
    Route::middleware(CheckPermission::class.':shift.create')->group(function () {
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    });
    Route::middleware(CheckPermission::class.':shift.update')->group(function () {
        Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    });
    Route::middleware(CheckPermission::class.':shift.delete')->group(function () {
        Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
    });

    // Work Centers
    Route::middleware(CheckPermission::class.':work-centers.view')->group(function () {
        Route::get('work-centers', [WorkCenterController::class, 'index'])->name('work-centers.index');
    });
    Route::middleware(CheckPermission::class.':work-centers.create')->group(function () {
        Route::post('work-centers', [WorkCenterController::class, 'store'])->name('work-centers.store');
    });
    Route::middleware(CheckPermission::class.':work-centers.update')->group(function () {
        Route::put('work-centers/{workCenter}', [WorkCenterController::class, 'update'])->name('work-centers.update');
    });
    Route::middleware(CheckPermission::class.':work-centers.delete')->group(function () {
        Route::delete('work-centers/{workCenter}', [WorkCenterController::class, 'destroy'])->name('work-centers.destroy');
    });

    // Machines
    Route::middleware(CheckPermission::class.':machine.view')->group(function () {
        Route::get('machines', [MachineController::class, 'index'])->name('machines.index');
    });
    Route::middleware(CheckPermission::class.':machine.create')->group(function () {
        Route::post('machines', [MachineController::class, 'store'])->name('machines.store');
    });
    Route::middleware(CheckPermission::class.':machine.update')->group(function () {
        Route::put('machines/{machine}', [MachineController::class, 'update'])->name('machines.update');
    });
    Route::middleware(CheckPermission::class.':machine.delete')->group(function () {
        Route::delete('machines/{machine}', [MachineController::class, 'destroy'])->name('machines.destroy');
    });

    // Units of Measure (organization-scoped)
    Route::middleware(CheckPermission::class.':uom.view')->group(function () {
        Route::get('units-of-measure', [UnitOfMeasureController::class, 'index'])->name('units-of-measure.index');
    });
    Route::middleware(CheckPermission::class.':uom.create')->group(function () {
        Route::post('units-of-measure', [UnitOfMeasureController::class, 'store'])->name('units-of-measure.store');
    });
    Route::middleware(CheckPermission::class.':uom.update')->group(function () {
        Route::put('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'update'])->name('units-of-measure.update');
    });
    Route::middleware(CheckPermission::class.':uom.delete')->group(function () {
        Route::delete('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy'])->name('units-of-measure.destroy');
    });

    // Product Categories (organization-scoped)
    Route::middleware(CheckPermission::class.':product-category.view')->group(function () {
        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
    });
    Route::middleware(CheckPermission::class.':product-category.create')->group(function () {
        Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
    });
    Route::middleware(CheckPermission::class.':product-category.update')->group(function () {
        Route::put('product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
    });
    Route::middleware(CheckPermission::class.':product-category.delete')->group(function () {
        Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
    });

    // Products (organization-scoped)
    Route::middleware(CheckPermission::class.':products.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
    });
    Route::middleware(CheckPermission::class.':products.create')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
    });
    Route::middleware(CheckPermission::class.':products.export')->group(function () {
        Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    });
    Route::middleware(CheckPermission::class.':products.import')->group(function () {
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    });
    Route::post('products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
    Route::middleware(CheckPermission::class.':products.view')->group(function () {
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    });
    Route::middleware(CheckPermission::class.':products.update')->group(function () {
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}/attachments/{attachment}', [ProductController::class, 'destroyAttachment'])->name('products.attachments.destroy');
    });
    Route::middleware(CheckPermission::class.':products.delete')->group(function () {
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // Bill of Materials (organization-scoped)
    Route::middleware(CheckPermission::class.':boms.create')->group(function () {
        Route::get('boms/create', [BomController::class, 'create'])->name('boms.create');
        Route::post('boms', [BomController::class, 'store'])->name('boms.store');
        Route::post('boms/{bom}/copy', [BomController::class, 'copy'])->name('boms.copy');
    });
    Route::middleware(CheckPermission::class.':boms.view')->group(function () {
        Route::get('boms', [BomController::class, 'index'])->name('boms.index');
        Route::get('boms/{bom}', [BomController::class, 'show'])->name('boms.show');
    });
    Route::middleware(CheckPermission::class.':boms.update')->group(function () {
        Route::get('boms/{bom}/edit', [BomController::class, 'edit'])->name('boms.edit');
        Route::put('boms/{bom}', [BomController::class, 'update'])->name('boms.update');
    });
    Route::middleware(CheckPermission::class.':boms.delete')->group(function () {
        Route::delete('boms/{bom}', [BomController::class, 'destroy'])->name('boms.destroy');
    });

    // Operations master (organization-scoped)
    Route::middleware(CheckPermission::class.':operations.view')->group(function () {
        Route::get('operations', [OperationController::class, 'index'])->name('operations.index');
    });
    Route::middleware(CheckPermission::class.':operations.create')->group(function () {
        Route::post('operations', [OperationController::class, 'store'])->name('operations.store');
    });
    Route::middleware(CheckPermission::class.':operations.update')->group(function () {
        Route::put('operations/{operation}', [OperationController::class, 'update'])->name('operations.update');
    });
    Route::middleware(CheckPermission::class.':operations.delete')->group(function () {
        Route::delete('operations/{operation}', [OperationController::class, 'destroy'])->name('operations.destroy');
    });

    // Routings (active plant — work centers / machines are plant-scoped)
    Route::middleware(CheckPermission::class.':routing.create')->group(function () {
        Route::get('routings/create', [RoutingController::class, 'create'])->name('routings.create');
        Route::post('routings', [RoutingController::class, 'store'])->name('routings.store');
        Route::post('routings/{routing}/copy', [RoutingController::class, 'copy'])->name('routings.copy');
    });
    Route::middleware(CheckPermission::class.':routing.view')->group(function () {
        Route::get('routings', [RoutingController::class, 'index'])->name('routings.index');
        Route::get('routings/{routing}', [RoutingController::class, 'show'])->name('routings.show');
    });
    Route::middleware(CheckPermission::class.':routing.update')->group(function () {
        Route::get('routings/{routing}/edit', [RoutingController::class, 'edit'])->name('routings.edit');
        Route::put('routings/{routing}', [RoutingController::class, 'update'])->name('routings.update');
        Route::post('routings/{routing}/release', [RoutingController::class, 'release'])->name('routings.release');
        Route::post('routings/{routing}/obsolete', [RoutingController::class, 'obsolete'])->name('routings.obsolete');
    });
    Route::middleware(CheckPermission::class.':routing.delete')->group(function () {
        Route::delete('routings/{routing}', [RoutingController::class, 'destroy'])->name('routings.destroy');
    });

    // Warehouse Types (organization-scoped)
    Route::middleware(CheckPermission::class.':warehouses.view')->group(function () {
        Route::get('warehouse-types', [WarehouseTypeController::class, 'index'])->name('warehouse-types.index');
    });
    Route::middleware(CheckPermission::class.':warehouses.create')->group(function () {
        Route::post('warehouse-types', [WarehouseTypeController::class, 'store'])->name('warehouse-types.store');
    });
    Route::middleware(CheckPermission::class.':warehouses.update')->group(function () {
        Route::put('warehouse-types/{warehouseType}', [WarehouseTypeController::class, 'update'])->name('warehouse-types.update');
    });
    Route::middleware(CheckPermission::class.':warehouses.delete')->group(function () {
        Route::delete('warehouse-types/{warehouseType}', [WarehouseTypeController::class, 'destroy'])->name('warehouse-types.destroy');
    });

    // Warehouses (active plant)
    Route::middleware(CheckPermission::class.':warehouses.view')->group(function () {
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    });
    Route::middleware(CheckPermission::class.':warehouses.create')->group(function () {
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    });
    Route::middleware(CheckPermission::class.':warehouses.update')->group(function () {
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    });
    Route::middleware(CheckPermission::class.':warehouses.delete')->group(function () {
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    });

    // Warehouse Locations (active plant)
    Route::middleware(CheckPermission::class.':warehouses.view')->group(function () {
        Route::get('warehouse-locations', [WarehouseLocationController::class, 'index'])->name('warehouse-locations.index');
    });
    Route::middleware(CheckPermission::class.':warehouses.create')->group(function () {
        Route::post('warehouse-locations', [WarehouseLocationController::class, 'store'])->name('warehouse-locations.store');
    });
    Route::middleware(CheckPermission::class.':warehouses.update')->group(function () {
        Route::put('warehouse-locations/{location}', [WarehouseLocationController::class, 'update'])->name('warehouse-locations.update');
    });
    Route::middleware(CheckPermission::class.':warehouses.delete')->group(function () {
        Route::delete('warehouse-locations/{location}', [WarehouseLocationController::class, 'destroy'])->name('warehouse-locations.destroy');
    });

    // Inventory (active plant — balances are not manually CRUD'd)
    Route::middleware(CheckPermission::class.':inventory.view')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/balance', [InventoryController::class, 'balance'])->name('inventory.balance');
        Route::get('inventory-transactions', [InventoryTransactionController::class, 'index'])->name('inventory-transactions.index');
    });
    Route::middleware(CheckPermission::class.':inventory.history')->group(function () {
        Route::get('inventory/{inventory}/history', [InventoryController::class, 'history'])->name('inventory.history');
    });
    Route::middleware(CheckPermission::class.':inventory.adjust')->group(function () {
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('inventory/transfer', [InventoryController::class, 'transfer'])->name('inventory.transfer');
    });
    Route::middleware(CheckPermission::class.':inventory.export')->group(function () {
        Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
    });
});

require __DIR__.'/settings.php';
