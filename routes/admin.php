<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:admin.access'])->prefix('admin')->name('admin.')->group(function () {
    // Users management
    Route::resource('users', UserController::class);

    // Roles management
    Route::resource('roles', RoleController::class);
});
