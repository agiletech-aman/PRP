<?php

use App\Http\Controllers\Admin\AppraisalController;
use App\Http\Controllers\Admin\AppraisalReportController;
use App\Http\Controllers\Admin\AppraisalReviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RatingCategoryController;
use App\Http\Controllers\Admin\RatingFactorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AppraisalFormController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\ApplySmtpConfiguration;
use App\PermissionName;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
 * Standalone appraisal form opened from the mail links: sign in, fill in your part, see a thank-you page.
 */
Route::prefix('appraisal/{appraisal}')
    ->where(['appraisal' => '[0-9]+'])
    ->name('appraisal.')
    ->controller(AppraisalFormController::class)
    ->group(function () {
        Route::get('/', 'show')->name('form');
        Route::get('login', 'login')->name('login');

        Route::middleware(['auth', ApplySmtpConfiguration::class])->group(function () {
            Route::put('/', 'submit')->name('submit');
            Route::put('hr-remarks', 'saveHrRemarks')->name('hr-remarks');
            Route::get('thank-you', 'thankYou')->name('thank-you');
            Route::post('switch-user', 'switchUser')->name('switch-user');
        });
    });

Route::middleware(['auth', ApplySmtpConfiguration::class])->prefix('admin')->name('admin.')->group(function () {
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

    Route::get('employees/import/sample', [EmployeeController::class, 'downloadImportSample'])
        ->middleware('can:'.PermissionName::ManageEmployees->value)
        ->name('employees.import.sample');

    Route::post('employees/import', [EmployeeController::class, 'import'])
        ->middleware('can:'.PermissionName::ManageEmployees->value)
        ->name('employees.import');

    Route::resource('employees', EmployeeController::class)
        ->except(['index', 'show'])
        ->middleware('can:'.PermissionName::ManageEmployees->value);

    Route::post('employees/{employee}/send-credentials', [EmployeeController::class, 'sendCredentials'])
        ->middleware('can:'.PermissionName::ManageEmployees->value)
        ->name('employees.send-credentials');

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

    Route::middleware('can:'.PermissionName::ViewPerformanceReports->value)->prefix('appraisal-reports')->name('appraisal-reports.')->group(function () {
        Route::get('/', [AppraisalReportController::class, 'index'])->name('index');
        Route::get('/export', [AppraisalReportController::class, 'export'])->name('export');
        Route::get('/{appraisal}/export', [AppraisalReportController::class, 'exportDetail'])->name('export-detail');
    });

    Route::get('my-appraisals', [AppraisalReviewController::class, 'index'])->name('appraisals.mine');

    Route::middleware('can:'.PermissionName::ManageAppraisalSettings->value)->group(function () {
        Route::get('settings/appraisal', [SettingsController::class, 'edit'])->name('settings.appraisal');
        Route::put('settings/appraisal', [SettingsController::class, 'update'])->name('settings.appraisal.update');
        Route::post('settings/smtp', [SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
    });

    Route::resource('roles', RoleController::class)
        ->except(['show'])
        ->middleware('can:'.PermissionName::ManageRoles->value);
});
