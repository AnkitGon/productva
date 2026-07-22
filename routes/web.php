<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

use App\Http\Middleware\CheckPermission;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        if ($user->hasRole('super-admin')) {
            return redirect('/admin/dashboard');
        }

        return inertia('admin/dashboard');
    })->middleware(CheckPermission::class.':admin-dashboard')->name('dashboard');

    Route::post('plants/{plant}/activate', \App\Http\Controllers\ActivePlantController::class)->name('plants.activate');
    Route::post('plants', [\App\Http\Controllers\PlantController::class, 'store'])->name('plants.store');
    Route::put('plants/{plant}', [\App\Http\Controllers\PlantController::class, 'update'])->name('plants.update');
    Route::delete('plants/{plant}', [\App\Http\Controllers\PlantController::class, 'destroy'])->name('plants.destroy');

    // Employees Directory Resource
    Route::middleware(CheckPermission::class.':employees.view')->group(function () {
        Route::get('employees', [\App\Http\Controllers\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [\App\Http\Controllers\EmployeeController::class, 'show'])->name('employees.show');
    });
    Route::middleware(CheckPermission::class.':employees.create')->group(function () {
        Route::post('employees', [\App\Http\Controllers\EmployeeController::class, 'store'])->name('employees.store');
    });
    Route::middleware(CheckPermission::class.':employees.update')->group(function () {
        Route::put('employees/{employee}', [\App\Http\Controllers\EmployeeController::class, 'update'])->name('employees.update');
    });
    Route::middleware(CheckPermission::class.':employees.delete')->group(function () {
        Route::delete('employees/{employee}', [\App\Http\Controllers\EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    // Departments Directory Resource
    Route::middleware(CheckPermission::class.':departments.view')->group(function () {
        Route::get('departments', [\App\Http\Controllers\DepartmentController::class, 'index'])->name('departments.index');
    });
    Route::middleware(CheckPermission::class.':departments.create')->group(function () {
        Route::post('departments', [\App\Http\Controllers\DepartmentController::class, 'store'])->name('departments.store');
    });
    Route::middleware(CheckPermission::class.':departments.update')->group(function () {
        Route::put('departments/{department}', [\App\Http\Controllers\DepartmentController::class, 'update'])->name('departments.update');
    });
    Route::middleware(CheckPermission::class.':departments.delete')->group(function () {
        Route::delete('departments/{department}', [\App\Http\Controllers\DepartmentController::class, 'destroy'])->name('departments.destroy');
    });
});



require __DIR__.'/settings.php';
