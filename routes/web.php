<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

use App\Http\Controllers\ActivePlantController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\WarehouseController;
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
    Route::middleware(CheckPermission::class.':employees.update')->group(function () {
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });
    Route::middleware(CheckPermission::class.':employees.delete')->group(function () {
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    // Departments Directory Resource
    Route::middleware(CheckPermission::class.':departments.view')->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
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
    Route::middleware(CheckPermission::class.':products.view')->group(function () {
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    });
    Route::middleware(CheckPermission::class.':products.update')->group(function () {
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    });
    Route::middleware(CheckPermission::class.':products.delete')->group(function () {
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
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
});

require __DIR__.'/settings.php';
