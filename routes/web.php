<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\CaseManagementController;
use App\Http\Controllers\User\TransactionController;
use App\Http\Controllers\User\TransactionRuleController;
use App\Http\Controllers\User\CustomerDetailsController;
use App\Http\Controllers\User\WatchListController;
use App\Http\Controllers\User\NIBSSWatchListController;
use App\Http\Controllers\User\TeamController;
use App\Http\Controllers\User\SettingsController;
use App\Http\Controllers\User\AccountSettingsController;
use App\Http\Controllers\User\AuditTrailController;
use App\Http\Controllers\User\RiskRatingController;
use App\Http\Controllers\User\PeerGroupAnalysisController;
use App\Http\Controllers\User\PASController;
use App\Http\Controllers\User\SanctionsController;
use App\Http\Controllers\User\AIAlertController;
use App\Http\Controllers\User\ToolController;

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/
Route::get('/', fn() => redirect()->route('login'));
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('login', [LoginController::class, 'login'])->middleware('guest');
Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    /*--- Dashboard ---*/
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('analytics', [DashboardController::class, 'analytics'])->name('analytics');
    Route::get('state-analytics', [DashboardController::class, 'getStateAnalytics'])->name('state-analytics');
    Route::get('get-state-lgas', [DashboardController::class, 'getStateLgas'])->name('get-state-lgas');

    /*--- Transactions ---*/
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/ajax-get', [TransactionController::class, 'ajaxGet'])->name('show');
        Route::get("/show/{id}", [TransactionController::class, "showTransaction"])->name("detail");
        Route::get('/risk-scoring-config', [TransactionController::class, 'listRiskConfig'])->name('risk-scoring-config');
        Route::post('/risk-scoring-config', [TransactionController::class, 'storeRiskConfig'])->name('risk-scoring-config.store');
        Route::put('/risk-scoring-config/{id}', [TransactionController::class, 'updateRiskConfig'])->name('risk-scoring-config.update');
        Route::delete('/risk-scoring-config/{id}', [TransactionController::class, 'destroyRiskConfig'])->name('risk-scoring-config.destroy');
        Route::post("/risk-scoring-config/update-threshold", [TransactionController::class, "updateThreshold"])->name("risk-scoring-config.update-threshold");
        Route::post('/import', [TransactionController::class, 'importTransactions'])->name('import');
    });

    /*--- Case Management ---*/
    Route::prefix('case-management')->name('case-management.')->group(function () {
        Route::get('/', [CaseManagementController::class, 'index'])->name('index');
        Route::get('/show/{id}', [CaseManagementController::class, 'show'])->name('show');
        Route::put('/update/{id}', [CaseManagementController::class, 'update'])->name('update');
        Route::post('/toogle-classification/{id}', [CaseManagementController::class, 'toogleClass'])->name('toogle-class');
        Route::post('/set-nfiu-indicator/{id}', [CaseManagementController::class, 'setNfiuIndicator'])->name('set-nfiu-indicator');
        Route::post('/export', [CaseManagementController::class, 'export'])->name('export');
        Route::get('/performance', [CaseManagementController::class, 'casePerformance'])->name('performance');
        Route::get('/performance/export', [CaseManagementController::class, 'exportPerformance'])->name('performance.export');
        Route::get('/carrd', [CaseManagementController::class, 'carrd'])->name('carrd');
        Route::get('/carrd-data', [CaseManagementController::class, 'carrdData'])->name('carrd.data');
        Route::get('/carrd/export', [CaseManagementController::class, 'exportCarrd'])->name('carrd.export');
        Route::get('/false-positive-dashboard', [CaseManagementController::class, 'falsePositiveDashboard'])->name('false-positive-dashboard');
        Route::get('/false-positive-dashboard/export', [CaseManagementController::class, 'exportFalsePositive'])->name('false-positive-dashboard.export');
        Route::post('/set-false-positive-threshold', [CaseManagementController::class, 'setFalsePositiveThreshold'])->name('set-false-positive-threshold');
    });

    /*--- Transaction Rules ---*/
    Route::prefix('transaction-rules')->name('transaction-rules.')->group(function () {
        Route::get('/', [TransactionRuleController::class, 'index'])->name('index');
        Route::get('/create', [TransactionRuleController::class, 'create'])->name('create');
        Route::post('/', [TransactionRuleController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [TransactionRuleController::class, 'edit'])->name('edit');
        Route::put('/{id}', [TransactionRuleController::class, 'update'])->name('update');
        Route::delete('/{id}', [TransactionRuleController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/activate', [TransactionRuleController::class, 'activate'])->name('activate');
        Route::post('/{id}/deactivate', [TransactionRuleController::class, 'deactivate'])->name('deactivate');
        Route::get('/{id}/edit-value', [TransactionRuleController::class, 'editValue'])->name('edit-value');
        Route::post('/{id}/edit-value', [TransactionRuleController::class, 'storeValue'])->name('store-value');
    });

    /*--- AI Alerts ---*/
    Route::get('ai-alerts', [AIAlertController::class, 'index'])->name('ai-alerts.index');
    Route::post('ai-alerts/{id}/flag', [AIAlertController::class, 'flagAsCase'])->name('ai-alerts.flag');

    /*--- Customers ---*/
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerDetailsController::class, 'index'])->name('all');
        Route::get('/search', [CustomerDetailsController::class, 'search'])->name('search');
        Route::get('/show/{id}', [CustomerDetailsController::class, 'show'])->name('show');
        Route::get('/show/{id}/export', [CustomerDetailsController::class, 'export360'])->name('show.export');
        Route::get('/ajax-show', [CustomerDetailsController::class, 'ajaxshow'])->name('ajax-show');
        Route::get('/create', [CustomerDetailsController::class, 'create'])->name('create');
        Route::post('/store', [CustomerDetailsController::class, 'store'])->name('store');
        Route::put('/update', [CustomerDetailsController::class, 'update'])->name('update');
        Route::delete('/delete', [CustomerDetailsController::class, 'destroy'])->name('destory');
    });

    /*--- Internal Watchlist ---*/
    Route::prefix('watch-list/internal')->name('watch-list.internal.')->group(function () {
        Route::get('/', [WatchListController::class, 'index'])->name('all');
        Route::get('/show', [WatchListController::class, 'show'])->name('show');
        Route::post('/add', [WatchListController::class, 'store'])->name('add');
        Route::put('/update', [WatchListController::class, 'update'])->name('update');
        Route::delete('/delete', [WatchListController::class, 'destroy'])->name('destory');
        Route::post('/upload', [WatchListController::class, 'upload'])->name('upload');
    });

    /*--- NIBSS Watchlist ---*/
    Route::prefix('watch-list/nibss')->name('watch-list.nibss.')->group(function () {
        Route::get('/', [NIBSSWatchListController::class, 'index'])->name('all');
        Route::get('/show', [NIBSSWatchListController::class, 'show'])->name('show');
        Route::post('/add', [NIBSSWatchListController::class, 'store'])->name('add');
        Route::put('/update', [NIBSSWatchListController::class, 'update'])->name('update');
        Route::delete('/delete', [NIBSSWatchListController::class, 'destroy'])->name('destroy');
        Route::post('/upload', [NIBSSWatchListController::class, 'upload'])->name('upload');
    });

    /*--- Risk Rating ---*/
    Route::prefix('risk-rating')->name('risk-rating.')->group(function () {
        Route::get('/', [RiskRatingController::class, 'index'])->name('index');
        Route::post('/rate', [RiskRatingController::class, 'riskRate'])->name('rate');
        Route::get('/view/{id}', [RiskRatingController::class, 'viewRiskRating'])->name('view');
        Route::delete('/delete', [RiskRatingController::class, 'destroyRiskRating'])->name('destroy');
        Route::get('/export/{id}/{format}', [RiskRatingController::class, 'exportRiskRating'])->name('export');
        Route::get('/risk-level-changes/export', [RiskRatingController::class, 'exportRiskLevelChanges'])->name('risk-level-changes.export');
        Route::match(['get', 'post'], '/reviews-due', [RiskRatingController::class, 'reviewsDue'])->name('reviews-due');

        Route::prefix('manage-risk-level')->name('manage-risk-level.')->group(function () {
            Route::get('/', [RiskRatingController::class, 'listRiskLevel'])->name('index');
            Route::post('/', [RiskRatingController::class, 'storeRiskLevel'])->name('store');
            Route::get('/show', [RiskRatingController::class, 'showRiskLevel'])->name('show');
            Route::put('/update', [RiskRatingController::class, 'updateRiskLevel'])->name('update');
            Route::delete('/delete', [RiskRatingController::class, 'destroyRiskLevel'])->name('destroy');
        });

        Route::prefix('manage-risk-profile')->name('manage-risk-profile.')->group(function () {
            Route::get('/', [RiskRatingController::class, 'listRiskProfile'])->name('index');
            Route::post('/', [RiskRatingController::class, 'storeRiskProfile'])->name('store');
            Route::get('/show/{id}', [RiskRatingController::class, 'showRiskProfile'])->name('show');
            Route::put('/update', [RiskRatingController::class, 'updateRiskProfile'])->name('update');
            Route::delete('/delete', [RiskRatingController::class, 'destroyRiskProfile'])->name('destroy');
            Route::get('/ajax', [RiskRatingController::class, 'ajaxRiskProfile'])->name('ajax');
            Route::get('/download-template/{id}', [RiskRatingController::class, 'getRiskProfileTemplate'])->name('download-template');
            Route::post('/import-template', [RiskRatingController::class, 'importRiskProfileTemplate'])->name('import-template');
            Route::delete('/delete-template', [RiskRatingController::class, 'deleteRiskProfileTemplate'])->name('delete-template');
        });
    });

    /*--- PAS Screening ---*/
    Route::prefix('pas')->name('pas.')->group(function () {
        Route::get('/', [PASController::class, 'index'])->name('index');
        Route::post('/screen', [PASController::class, 'screen'])->name('screen');
    });

    /*--- Sanction Lists (admin) ---*/
    Route::prefix('sanctions')->name('sanctions.')->group(function () {
        Route::get('/', [SanctionsController::class, 'index'])->name('index');
        Route::post('/sync', [SanctionsController::class, 'sync'])->name('sync');
    });

    /*--- Peer Group Analysis ---*/
    Route::prefix('peer-grouping')->name('peer-grouping.')->group(function () {
        Route::get('/', [PeerGroupAnalysisController::class, 'index'])->name('index');
        Route::post('/update-settings', [PeerGroupAnalysisController::class, 'updateSettings'])->name('update-settings');
    });

    /*--- Tools ---*/
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/validate-xml', [ToolController::class, 'viewValidateXml'])->name('view-validate-xml');
        Route::post('/validate-xml', [ToolController::class, 'validateXml'])->name('validate-xml');
        Route::post('/upload-xsd', [ToolController::class, 'uploadXsd'])->name('upload-xsd');
        Route::delete('/delete-xsd', [ToolController::class, 'deleteXsd'])->name('delete-xsd');
    });

    /*--- Team Management ---*/
    Route::prefix('manage-team')->name('manage-team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::get('/show', [TeamController::class, 'show'])->name('show');
        Route::post('/add', [TeamController::class, 'store'])->name('add');
        Route::put('/update', [TeamController::class, 'update'])->name('update');
        Route::delete('/delete', [TeamController::class, 'destroy'])->name('delete');
        Route::get('/assigned-rules', [TeamController::class, 'showAssignedRules'])->name('assign-rule.index');
        Route::post('/store-assigned-rule', [TeamController::class, 'updateAssignedRule'])->name('assign-rule.update');
        Route::get('/delete/{id}', [TeamController::class, 'delete'])->name('assign-rule.delete');
        Route::post('/bulk-delete', [TeamController::class, 'bulkDelete'])->name('assign-rule.bulk-delete');
        Route::get('/manage-role', [TeamController::class, 'listRole'])->name('manage-roles');
        Route::get('/create-role', [TeamController::class, 'createRole'])->name('create-role');
        Route::post('/store-role', [TeamController::class, 'storeRole'])->name('store-role');
        Route::get('/get-role/{id}', [TeamController::class, 'getRole'])->name('get-role');
        Route::put('/update-role', [TeamController::class, 'updateRole'])->name('update-role');
        Route::delete('/delete-role', [TeamController::class, 'deleteRole'])->name('delete-role');
    });

    /*--- Audit Trail ---*/
    Route::prefix('audit-trail')->name('audit-trail.')->middleware('permission:audit-trail')->group(function () {
        Route::get('/', [AuditTrailController::class, 'index'])->name('index');
        Route::get('export', [AuditTrailController::class, 'export'])->name('export');
    });

    /*--- Account Settings ---*/
    Route::get('account-settings-profile', [AccountSettingsController::class, 'index'])->name('account-settings-profile');
    Route::put('account-settings-profile', [AccountSettingsController::class, 'updateProfile'])->name('account-settings-profile.update');
    Route::put('account-settings-password', [AccountSettingsController::class, 'updatePassword'])->name('account-settings-password.update');
    Route::get('account-settings-security', [AccountSettingsController::class, 'security'])->name('account-settings-security');
    Route::post('toggle-email-otp', [AccountSettingsController::class, 'toggleEmailOtp'])->name('toggle-email-otp');

    /*--- System Settings (Admin) ---*/
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('business-profile-update', [AccountSettingsController::class, 'businessProfileUpdate'])->name('business-profile-update');
    Route::post('generate-api-keys', [SettingsController::class, 'generateApiKeys'])->name('generate-api-keys');
    Route::post('settings-toggle-update', [SettingsController::class, 'settingsToggleUpdate'])->name('settings-toggle-update');
    Route::post('settings/customer-sync', [SettingsController::class, 'saveCustomerSyncSettings'])->name('settings.customer-sync');
});

/*
|--------------------------------------------------------------------------
| Cron Job Routes
|
| The scheduler (routes/console.php → php artisan schedule:run) invokes the
| controller methods directly, so these HTTP endpoints are only manual
| triggers. They mutate state, therefore they are restricted to admins.
|--------------------------------------------------------------------------
*/
Route::prefix('cron-job')->name('cron-job.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/review-scheduler', [\App\Http\Controllers\CronJobController::class, 'reviewScheduler'])->name('review-scheduler');
    Route::get('/24hr-rule-engine', [\App\Http\Controllers\CronJobController::class, 'dailyRuleEngine'])->name('24hr-rule-engine');
    Route::get('/watchlist-screening', [\App\Http\Controllers\CronJobController::class, 'watchListScreening'])->name('watchlist-screening');
    Route::get('/pas-screening', [\App\Http\Controllers\CronJobController::class, 'pasScreening'])->name('pas-screening');
    Route::get('/risk-rate-new-customers', [\App\Http\Controllers\CronJobController::class, 'riskRateNewCustomers'])->name('risk-rate-new-customers');
    Route::get('/generate-ctr', [\App\Http\Controllers\CronJobController::class, 'generateCtr'])->name('generate-ctr');
    Route::get('/customer-sync', [\App\Http\Controllers\CronJobController::class, 'customerSync'])->name('customer-sync');
});

// Cron status dashboard (authenticated)
Route::get('cron-jobs', [\App\Http\Controllers\CronJobController::class, 'status'])->name('cron-jobs.status')->middleware('auth');
