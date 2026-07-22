<?php

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| All routes in this file are automatically prefixed with '/admin' and
| prefixed with route name 'admin.'.
|
*/

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Super Admin Dashboard -> /admin/dashboard
    Route::get('/dashboard', function () {
        return inertia('admin/super-dashboard');
    })->middleware(CheckPermission::class.':super-admin-dashboard')->name('dashboard');

    // Admin Users Management -> /admin/users
    Route::middleware(CheckPermission::class.':super-admin-dashboard')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Admin Roles Management -> /admin/roles
    Route::middleware(CheckPermission::class.':roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});




