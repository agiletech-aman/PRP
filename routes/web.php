<?php

use App\Http\Controllers\Admin\AppraisalController;
use App\Http\Controllers\Admin\AppraisalReviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RatingCategoryController;
use App\Http\Controllers\Admin\RatingFactorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Auth\LoginController;
use App\PermissionName;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::view('/no-access', 'admin.no-access')->name('no-access');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('can:'.PermissionName::ViewDashboard->value)
        ->name('dashboard');

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('can:'.PermissionName::ViewEmployees->value)
        ->name('employees.index');

    Route::resource('employees', EmployeeController::class)
        ->except(['index', 'show'])
        ->middleware('can:'.PermissionName::ManageEmployees->value);

    Route::middleware('can:'.PermissionName::ManageDepartments->value)->group(function () {
        Route::resource('departments', DepartmentController::class)->except(['create', 'show']);
        Route::get('departments/{department}/form', [DepartmentController::class, 'form'])->name('departments.form');
    });

    Route::middleware('can:'.PermissionName::ManageRatings->value)->group(function () {
        Route::resource('categories', RatingCategoryController::class)
            ->parameters(['categories' => 'category'])
            ->except(['show']);

        Route::resource('categories.factors', RatingFactorController::class)
            ->parameters(['categories' => 'category'])
            ->only(['index', 'store', 'edit', 'update', 'destroy'])
            ->shallow();
    });

    Route::middleware('can:'.PermissionName::SendAppraisals->value)->group(function () {
        Route::get('appraisals', [AppraisalController::class, 'index'])->name('appraisals.index');
        Route::get('appraisals/send', [AppraisalController::class, 'create'])->name('appraisals.create');
        Route::post('appraisals', [AppraisalController::class, 'store'])->name('appraisals.store');
    });

    Route::get('my-appraisals', [AppraisalReviewController::class, 'index'])->name('appraisals.mine');
    Route::get('appraisals/{appraisal}', [AppraisalReviewController::class, 'show'])
        ->whereNumber('appraisal')
        ->can('view', 'appraisal')
        ->name('appraisals.show');
    Route::put('appraisals/{appraisal}', [AppraisalReviewController::class, 'update'])
        ->whereNumber('appraisal')
        ->name('appraisals.update');
    Route::put('appraisals/{appraisal}/hr-remarks', [AppraisalReviewController::class, 'updateHrRemarks'])
        ->whereNumber('appraisal')
        ->name('appraisals.hr-remarks');

    Route::resource('roles', RoleController::class)
        ->except(['show'])
        ->middleware('can:'.PermissionName::ManageRoles->value);
});
