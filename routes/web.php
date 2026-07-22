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
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UnitOfMeasureController;
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
});

require __DIR__.'/settings.php';
