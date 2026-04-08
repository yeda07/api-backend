<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccessControlController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CrmEntityController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\InventoryCategoryController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryProductController;
use App\Http\Controllers\Api\InventoryWarehouseController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PriceBookController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\RelationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Security Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant.active', 'tenant.token'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/2fa/setup', [AuthController::class, 'twoFactorSetup']);
    Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor']);
});

/*
|--------------------------------------------------------------------------
| Full Access Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant.active', 'tenant.token', 'full.access'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/2fa/recovery-codes/regenerate', [AuthController::class, 'regenerateRecoveryCodes']);

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage');
    Route::get('/rbac/roles', [AccessControlController::class, 'roles'])->middleware('permission:users.manage');
    Route::post('/rbac/roles', [AccessControlController::class, 'storeRole'])->middleware('permission:users.manage');
    Route::put('/rbac/roles/{roleUid}', [AccessControlController::class, 'updateRole'])->middleware('permission:users.manage');
    Route::delete('/rbac/roles/{roleUid}', [AccessControlController::class, 'destroyRole'])->middleware('permission:users.manage');
    Route::get('/rbac/permissions', [AccessControlController::class, 'permissions'])->middleware('permission:users.manage');
    Route::get('/users/{uid}/access', [AccessControlController::class, 'userAccess'])->middleware('permission:users.manage');
    Route::post('/users/{uid}/manager', [AccessControlController::class, 'assignManager'])->middleware('permission:users.manage');
    Route::post('/users/{uid}/roles', [AccessControlController::class, 'assignRole'])->middleware('permission:users.manage');
    Route::delete('/users/{uid}/roles/{roleUid}', [AccessControlController::class, 'removeRole'])->middleware('permission:users.manage');
    Route::post('/users/{uid}/permissions', [AccessControlController::class, 'grantPermission'])->middleware('permission:users.manage');
    Route::delete('/users/{uid}/permissions/{permissionUid}', [AccessControlController::class, 'revokePermission'])->middleware('permission:users.manage');

    Route::prefix('plans')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->middleware('permission:plans.manage');
        Route::post('/', [PlanController::class, 'store'])->middleware('permission:plans.manage');
    });

    Route::prefix('accounts')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->middleware('permission:accounts.read');
        Route::get('/{uid}', [AccountController::class, 'show'])->middleware('permission:accounts.read');
        Route::post('/', [AccountController::class, 'store'])->middleware('permission:accounts.create');
        Route::put('/{uid}', [AccountController::class, 'update'])->middleware('permission:accounts.update');
        Route::post('/{uid}/owner', [AccountController::class, 'assignOwner'])->middleware('permission:accounts.update');
        Route::delete('/{uid}', [AccountController::class, 'destroy'])->middleware('permission:accounts.delete');
    });

    Route::prefix('contacts')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->middleware('permission:contacts.read');
        Route::get('/{uid}', [ContactController::class, 'show'])->middleware('permission:contacts.read');
        Route::post('/', [ContactController::class, 'store'])->middleware('permission:contacts.create');
        Route::put('/{uid}', [ContactController::class, 'update'])->middleware('permission:contacts.update');
        Route::post('/{uid}/owner', [ContactController::class, 'assignOwner'])->middleware('permission:contacts.update');
        Route::delete('/{uid}', [ContactController::class, 'destroy'])->middleware('permission:contacts.delete');
    });

    Route::prefix('relations')->group(function () {
        Route::get('/', [RelationController::class, 'index'])->middleware('permission:relations.read');
        Route::get('/with-entities', [RelationController::class, 'indexWithEntities'])->middleware('permission:relations.read');
        Route::get('/hierarchy/{type}/{uid}', [RelationController::class, 'hierarchy'])->middleware('permission:relations.read');
        Route::post('/', [RelationController::class, 'store'])->middleware('permission:relations.create');
        Route::get('/{type}/{uid}', [RelationController::class, 'showByEntity'])->middleware('permission:relations.read');
        Route::delete('/{uid}', [RelationController::class, 'destroy'])->middleware('permission:relations.delete');
    });

    Route::prefix('crm-entities')->group(function () {
        Route::get('/', [CrmEntityController::class, 'index'])->middleware('permission:crm-entities.read');
        Route::post('/', [CrmEntityController::class, 'store'])->middleware('permission:crm-entities.create');
        Route::post('/{uid}/owner', [CrmEntityController::class, 'assignOwner'])->middleware('permission:crm-entities.update');
    });

    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index'])->middleware('permission:tags.manage');
        Route::post('/', [TagController::class, 'store'])->middleware('permission:tags.manage');
        Route::put('/{uid}', [TagController::class, 'update'])->middleware('permission:tags.manage');
        Route::delete('/{uid}', [TagController::class, 'destroy'])->middleware('permission:tags.manage');
        Route::post('/assign', [TagController::class, 'assign'])->middleware('permission:tags.manage');
        Route::post('/unassign', [TagController::class, 'unassign'])->middleware('permission:tags.manage');
        Route::delete('/assign', [TagController::class, 'unassign'])->middleware('permission:tags.manage');
    });

    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->middleware('permission:tasks.read');
        Route::get('/{uid}', [TaskController::class, 'show'])->middleware('permission:tasks.read');
        Route::post('/', [TaskController::class, 'store'])->middleware('permission:tasks.create');
        Route::put('/{uid}', [TaskController::class, 'update'])->middleware('permission:tasks.update');
        Route::delete('/{uid}', [TaskController::class, 'destroy'])->middleware('permission:tasks.delete');
    });

    Route::prefix('interactions')->group(function () {
        Route::get('/{type}/{uid}', [InteractionController::class, 'timeline'])->middleware('permission:interactions.read');
        Route::post('/notes', [InteractionController::class, 'note'])->middleware('permission:interactions.create');
        Route::post('/calls', [InteractionController::class, 'call'])->middleware('permission:interactions.create');
        Route::post('/emails', [InteractionController::class, 'email'])->middleware('permission:interactions.create');
    });

    Route::prefix('activities')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->middleware('permission:activities.read');
        Route::get('/range', [ActivityController::class, 'byRange'])->middleware('permission:activities.read');
        Route::get('/{uid}', [ActivityController::class, 'show'])->middleware('permission:activities.read');
        Route::post('/', [ActivityController::class, 'store'])->middleware('permission:activities.create');
        Route::put('/{uid}', [ActivityController::class, 'update'])->middleware('permission:activities.update');
        Route::delete('/{uid}', [ActivityController::class, 'destroy'])->middleware('permission:activities.delete');
    });

    Route::prefix('documents')->group(function () {
        Route::get('/entity/{type}/{uid}', [DocumentController::class, 'index'])->middleware('permission:documents.read');
        Route::get('/download/{uid}', [DocumentController::class, 'download'])->middleware('permission:documents.read');
        Route::post('/', [DocumentController::class, 'upload'])->middleware('permission:documents.create');
    });

    Route::prefix('inventory')->group(function () {
        Route::get('/master', [InventoryController::class, 'master'])->middleware('permission:inventory.read');
        Route::post('/stocks/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.manage');
        Route::post('/reservations', [InventoryController::class, 'reserve'])->middleware('permission:inventory.reserve');
        Route::get('/reservations/source/{sourceType}/{sourceUid}', [InventoryController::class, 'reservationsBySource'])->middleware('permission:inventory.read');
        Route::delete('/reservations/{uid}', [InventoryController::class, 'releaseReservation'])->middleware('permission:inventory.reserve');
        Route::post('/movements/transfer', [InventoryController::class, 'transfer'])->middleware('permission:inventory.manage');
        Route::get('/report', [InventoryController::class, 'report'])->middleware('permission:inventory.report');
        Route::get('/report/export', [InventoryController::class, 'exportReport'])->middleware('permission:inventory.report');

        Route::prefix('categories')->group(function () {
            Route::get('/', [InventoryCategoryController::class, 'index'])->middleware('permission:inventory.read');
            Route::post('/', [InventoryCategoryController::class, 'store'])->middleware('permission:inventory.manage');
            Route::put('/{uid}', [InventoryCategoryController::class, 'update'])->middleware('permission:inventory.manage');
            Route::delete('/{uid}', [InventoryCategoryController::class, 'destroy'])->middleware('permission:inventory.manage');
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [InventoryProductController::class, 'index'])->middleware('permission:inventory.read');
            Route::post('/', [InventoryProductController::class, 'store'])->middleware('permission:inventory.manage');
            Route::put('/{uid}', [InventoryProductController::class, 'update'])->middleware('permission:inventory.manage');
            Route::delete('/{uid}', [InventoryProductController::class, 'destroy'])->middleware('permission:inventory.manage');
        });

        Route::prefix('warehouses')->group(function () {
            Route::get('/', [InventoryWarehouseController::class, 'index'])->middleware('permission:inventory.read');
            Route::get('/{uid}/stocks', [InventoryWarehouseController::class, 'stocks'])->middleware('permission:inventory.read');
            Route::post('/', [InventoryWarehouseController::class, 'store'])->middleware('permission:inventory.manage');
            Route::put('/{uid}', [InventoryWarehouseController::class, 'update'])->middleware('permission:inventory.manage');
            Route::delete('/{uid}', [InventoryWarehouseController::class, 'destroy'])->middleware('permission:inventory.manage');
        });
    });

    Route::prefix('quotations')->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->middleware('permission:quotations.read');
        Route::get('/{uid}', [QuotationController::class, 'show'])->middleware('permission:quotations.read');
        Route::post('/', [QuotationController::class, 'store'])->middleware('permission:quotations.create');
        Route::put('/{uid}', [QuotationController::class, 'update'])->middleware('permission:quotations.update');
        Route::post('/{uid}/items', [QuotationController::class, 'addItem'])->middleware('permission:quotations.update');
        Route::put('/items/{itemUid}', [QuotationController::class, 'updateItem'])->middleware('permission:quotations.update');
        Route::delete('/items/{itemUid}', [QuotationController::class, 'destroyItem'])->middleware('permission:quotations.update');
        Route::post('/items/{itemUid}/reserve-stock', [QuotationController::class, 'reserveItemStock'])->middleware('permission:inventory.reserve');
        Route::delete('/items/{itemUid}/reservations/{reservationUid}', [QuotationController::class, 'releaseItemReservation'])->middleware('permission:inventory.reserve');
    });

    Route::prefix('price-books')->group(function () {
        Route::get('/', [PriceBookController::class, 'index'])->middleware('permission:price-books.read');
        Route::get('/{uid}', [PriceBookController::class, 'show'])->middleware('permission:price-books.read');
        Route::post('/', [PriceBookController::class, 'store'])->middleware('permission:price-books.manage');
        Route::put('/{uid}', [PriceBookController::class, 'update'])->middleware('permission:price-books.manage');
        Route::delete('/{uid}', [PriceBookController::class, 'destroy'])->middleware('permission:price-books.manage');
    });

    Route::prefix('commissions')->group(function () {
        Route::get('/rules', [CommissionController::class, 'rules'])->middleware('permission:commissions.read');
        Route::post('/rules', [CommissionController::class, 'storeRule'])->middleware('permission:commissions.manage');
        Route::put('/rules/{uid}', [CommissionController::class, 'updateRule'])->middleware('permission:commissions.manage');
        Route::delete('/rules/{uid}', [CommissionController::class, 'destroyRule'])->middleware('permission:commissions.manage');
        Route::post('/financial-records', [CommissionController::class, 'recordFinancialEvent'])->middleware('permission:commissions.manage');
        Route::get('/entries', [CommissionController::class, 'entries'])->middleware('permission:commissions.read');
        Route::put('/entries/{uid}/pay', [CommissionController::class, 'payEntry'])->middleware('permission:commissions.manage');
        Route::get('/my-summary', [CommissionController::class, 'mySummary'])->middleware('permission:commissions.read');
    });

    Route::post('/search', [SearchController::class, 'search'])->middleware('permission:search.use');
    Route::post('/search/export', [SearchController::class, 'export'])->middleware('permission:search.use');
    Route::get('/dashboard/core', [DashboardController::class, 'core'])->middleware('permission:dashboard.read');

    Route::prefix('custom-fields')->group(function () {
        Route::post('/', [CustomFieldController::class, 'store'])->middleware('permission:custom-fields.manage');
        Route::post('/value', [CustomFieldController::class, 'assign'])->middleware('permission:custom-fields.manage');
    });

    Route::get('/metrics/my-usage', [MetricsController::class, 'myUsage'])->middleware('permission:metrics.read');
    Route::get('/logs', [LogController::class, 'index'])->middleware('permission:logs.read');
});
