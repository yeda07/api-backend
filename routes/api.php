<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccessControlController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CompetitiveIntelligenceController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CrmEntityController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentAlertController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinancialOperationsController;
use App\Http\Controllers\Api\InventoryCategoryController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryProductController;
use App\Http\Controllers\Api\InventoryWarehouseController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PriceBookController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\QuoteController;
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
        Route::get('/{uid}/products', [ProductController::class, 'accountProducts'])->middleware('permission:products.read');
        Route::post('/', [AccountController::class, 'store'])->middleware('permission:accounts.create');
        Route::post('/{uid}/products', [ProductController::class, 'storeAccountProduct'])->middleware('permission:products.install');
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
        Route::get('/account/{accountUid}', [DocumentController::class, 'accountDocuments'])->middleware('permission:documents.read');
        Route::get('/missing/{accountUid}', [DocumentController::class, 'missingDocuments'])->middleware('permission:documents.read');
        Route::get('/download/{uid}', [DocumentController::class, 'download'])->middleware('permission:documents.read');
        Route::get('/{uid}/versions', [DocumentController::class, 'versions'])->middleware('permission:documents.read');
        Route::get('/{uid}', [DocumentController::class, 'show'])->middleware('permission:documents.read');
        Route::post('/', [DocumentController::class, 'upload'])->middleware('permission:documents.create');
        Route::put('/{uid}', [DocumentController::class, 'update'])->middleware('permission:documents.manage');
    });

    Route::prefix('document-types')->group(function () {
        Route::get('/', [DocumentTypeController::class, 'index'])->middleware('permission:documents.read');
        Route::post('/', [DocumentTypeController::class, 'store'])->middleware('permission:documents.manage');
        Route::put('/{uid}', [DocumentTypeController::class, 'update'])->middleware('permission:documents.manage');
    });

    Route::prefix('document-alerts')->group(function () {
        Route::get('/', [DocumentAlertController::class, 'index'])->middleware('permission:documents.read');
        Route::post('/generate', [DocumentAlertController::class, 'generate'])->middleware('permission:documents.manage');
        Route::post('/{uid}/read', [DocumentAlertController::class, 'markAsRead'])->middleware('permission:documents.manage');
    });

    Route::prefix('inventory')->group(function () {
        Route::get('/master', [InventoryController::class, 'master'])->middleware('permission:inventory.read');
        Route::get('/availability', [InventoryController::class, 'availability'])->middleware('permission:inventory.read');
        Route::get('/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.read');
        Route::post('/stocks/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.manage');
        Route::post('/reservations', [InventoryController::class, 'reserve'])->middleware('permission:inventory.reserve');
        Route::get('/reservations/source/{sourceType}/{sourceUid}', [InventoryController::class, 'reservationsBySource'])->middleware('permission:inventory.read');
        Route::delete('/reservations/{uid}', [InventoryController::class, 'releaseReservation'])->middleware('permission:inventory.reserve');
        Route::post('/reservations/{uid}/consume', [InventoryController::class, 'consumeReservation'])->middleware('permission:inventory.reserve');
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
        Route::get('/{uid}/pdf', [QuotationController::class, 'downloadPdf'])->middleware('permission:quotations.read');
        Route::post('/', [QuotationController::class, 'store'])->middleware('permission:quotations.create');
        Route::put('/{uid}', [QuotationController::class, 'update'])->middleware('permission:quotations.update');
        Route::post('/{uid}/send', [QuotationController::class, 'sendPdf'])->middleware('permission:quotations.update');
        Route::post('/{uid}/items', [QuotationController::class, 'addItem'])->middleware('permission:quotations.update');
        Route::put('/items/{itemUid}', [QuotationController::class, 'updateItem'])->middleware('permission:quotations.update');
        Route::delete('/items/{itemUid}', [QuotationController::class, 'destroyItem'])->middleware('permission:quotations.update');
        Route::post('/items/{itemUid}/reserve-stock', [QuotationController::class, 'reserveItemStock'])->middleware('permission:inventory.reserve');
        Route::delete('/items/{itemUid}/reservations/{reservationUid}', [QuotationController::class, 'releaseItemReservation'])->middleware('permission:inventory.reserve');
    });

    Route::prefix('quotes')->group(function () {
        Route::get('/', [QuoteController::class, 'index'])->middleware('permission:quotations.read');
        Route::post('/', [QuoteController::class, 'store'])->middleware('permission:quotations.create');
        Route::post('/{uid}/items', [QuoteController::class, 'addItem'])->middleware('permission:quotations.update');
        Route::post('/{uid}/approve', [QuoteController::class, 'approve'])->middleware('permission:quotations.update');
        Route::post('/{uid}/reject', [QuoteController::class, 'reject'])->middleware('permission:quotations.update');
        Route::post('/{uid}/convert', [QuoteController::class, 'convert'])->middleware('permission:finance.manage');
    });

    Route::prefix('price-books')->group(function () {
        Route::get('/', [PriceBookController::class, 'index'])->middleware('permission:price-books.read');
        Route::get('/{uid}', [PriceBookController::class, 'show'])->middleware('permission:price-books.read');
        Route::post('/', [PriceBookController::class, 'store'])->middleware('permission:price-books.manage');
        Route::put('/{uid}', [PriceBookController::class, 'update'])->middleware('permission:price-books.manage');
        Route::delete('/{uid}', [PriceBookController::class, 'destroy'])->middleware('permission:price-books.manage');
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.read');
        Route::get('/{uid}', [ProductController::class, 'show'])->middleware('permission:products.read');
        Route::get('/{uid}/versions', [ProductController::class, 'versions'])->middleware('permission:products.read');
        Route::get('/{uid}/dependencies', [ProductController::class, 'dependencies'])->middleware('permission:products.read');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.manage');
        Route::post('/{uid}/versions', [ProductController::class, 'storeVersion'])->middleware('permission:products.manage');
        Route::put('/versions/{versionUid}', [ProductController::class, 'updateVersion'])->middleware('permission:products.manage');
        Route::post('/{uid}/dependencies', [ProductController::class, 'storeDependency'])->middleware('permission:products.manage');
        Route::delete('/dependencies/{dependencyUid}', [ProductController::class, 'destroyDependency'])->middleware('permission:products.manage');
        Route::put('/{uid}', [ProductController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/{uid}', [ProductController::class, 'destroy'])->middleware('permission:products.manage');
    });

    Route::prefix('commissions')->group(function () {
        Route::get('/plans', [CommissionController::class, 'plans'])->middleware('permission:commissions.read');
        Route::post('/plans', [CommissionController::class, 'storePlan'])->middleware('permission:commissions.manage');
        Route::put('/plans/{uid}', [CommissionController::class, 'updatePlan'])->middleware('permission:commissions.manage');
        Route::get('/assignments', [CommissionController::class, 'assignments'])->middleware('permission:commissions.read');
        Route::post('/assignments', [CommissionController::class, 'storeAssignment'])->middleware('permission:commissions.manage');
        Route::put('/assignments/{uid}', [CommissionController::class, 'updateAssignment'])->middleware('permission:commissions.manage');
        Route::get('/targets', [CommissionController::class, 'targets'])->middleware('permission:commissions.read');
        Route::post('/targets', [CommissionController::class, 'storeTarget'])->middleware('permission:commissions.manage');
        Route::get('/rules', [CommissionController::class, 'rules'])->middleware('permission:commissions.read');
        Route::post('/rules', [CommissionController::class, 'storeRule'])->middleware('permission:commissions.manage');
        Route::put('/rules/{uid}', [CommissionController::class, 'updateRule'])->middleware('permission:commissions.manage');
        Route::delete('/rules/{uid}', [CommissionController::class, 'destroyRule'])->middleware('permission:commissions.manage');
        Route::post('/financial-records', [CommissionController::class, 'recordFinancialEvent'])->middleware('permission:commissions.manage');
        Route::get('/entries', [CommissionController::class, 'entries'])->middleware('permission:commissions.read');
        Route::put('/entries/{uid}/pay', [CommissionController::class, 'payEntry'])->middleware('permission:commissions.manage');
        Route::get('/my-summary', [CommissionController::class, 'mySummary'])->middleware('permission:commissions.read');
        Route::get('/dashboard/{userUid}', [CommissionController::class, 'dashboard'])->middleware('permission:commissions.read');
        Route::post('/simulate', [CommissionController::class, 'simulate'])->middleware('permission:commissions.read');
        Route::get('/runs', [CommissionController::class, 'runs'])->middleware('permission:commissions.read');
        Route::post('/runs', [CommissionController::class, 'storeRun'])->middleware('permission:commissions.manage');
        Route::post('/runs/{uid}/approve', [CommissionController::class, 'approveRun'])->middleware('permission:commissions.manage');
        Route::post('/runs/{uid}/pay', [CommissionController::class, 'payRun'])->middleware('permission:commissions.manage');
    });

    Route::prefix('competitive-intelligence')->group(function () {
        Route::get('/competitors', [CompetitiveIntelligenceController::class, 'competitors'])->middleware('permission:competitive-intelligence.read');
        Route::post('/competitors', [CompetitiveIntelligenceController::class, 'storeCompetitor'])->middleware('permission:competitive-intelligence.manage');
        Route::put('/competitors/{uid}', [CompetitiveIntelligenceController::class, 'updateCompetitor'])->middleware('permission:competitive-intelligence.manage');
        Route::delete('/competitors/{uid}', [CompetitiveIntelligenceController::class, 'destroyCompetitor'])->middleware('permission:competitive-intelligence.manage');

        Route::get('/battlecards', [CompetitiveIntelligenceController::class, 'battlecards'])->middleware('permission:competitive-intelligence.read');
        Route::get('/competitors/{uid}/battlecards', [CompetitiveIntelligenceController::class, 'battlecardsByCompetitor'])->middleware('permission:competitive-intelligence.read');
        Route::post('/battlecards', [CompetitiveIntelligenceController::class, 'storeBattlecard'])->middleware('permission:competitive-intelligence.manage');
        Route::put('/battlecards/{uid}', [CompetitiveIntelligenceController::class, 'updateBattlecard'])->middleware('permission:competitive-intelligence.manage');
        Route::delete('/battlecards/{uid}', [CompetitiveIntelligenceController::class, 'destroyBattlecard'])->middleware('permission:competitive-intelligence.manage');

        Route::get('/lost-reasons', [CompetitiveIntelligenceController::class, 'lostReasons'])->middleware('permission:competitive-intelligence.read');
        Route::post('/lost-reasons', [CompetitiveIntelligenceController::class, 'storeLostReason'])->middleware('permission:competitive-intelligence.manage');
        Route::put('/lost-reasons/{uid}', [CompetitiveIntelligenceController::class, 'updateLostReason'])->middleware('permission:competitive-intelligence.manage');
        Route::delete('/lost-reasons/{uid}', [CompetitiveIntelligenceController::class, 'destroyLostReason'])->middleware('permission:competitive-intelligence.manage');
        Route::get('/lost-reasons/report', [CompetitiveIntelligenceController::class, 'lostReasonsReport'])->middleware('permission:competitive-intelligence.report');
    });

    Route::prefix('expenses')->group(function () {
        Route::get('/categories', [ExpenseController::class, 'categories'])->middleware('permission:expenses.read');
        Route::post('/categories', [ExpenseController::class, 'storeCategory'])->middleware('permission:expenses.manage');
        Route::put('/categories/{uid}', [ExpenseController::class, 'updateCategory'])->middleware('permission:expenses.manage');
        Route::delete('/categories/{uid}', [ExpenseController::class, 'destroyCategory'])->middleware('permission:expenses.manage');
        Route::get('/suppliers', [ExpenseController::class, 'suppliers'])->middleware('permission:expenses.read');
        Route::post('/suppliers', [ExpenseController::class, 'storeSupplier'])->middleware('permission:expenses.manage');
        Route::put('/suppliers/{uid}', [ExpenseController::class, 'updateSupplier'])->middleware('permission:expenses.manage');
        Route::delete('/suppliers/{uid}', [ExpenseController::class, 'destroySupplier'])->middleware('permission:expenses.manage');
        Route::get('/cost-centers', [ExpenseController::class, 'costCenters'])->middleware('permission:expenses.read');
        Route::post('/cost-centers', [ExpenseController::class, 'storeCostCenter'])->middleware('permission:expenses.manage');
        Route::put('/cost-centers/{uid}', [ExpenseController::class, 'updateCostCenter'])->middleware('permission:expenses.manage');
        Route::delete('/cost-centers/{uid}', [ExpenseController::class, 'destroyCostCenter'])->middleware('permission:expenses.manage');
        Route::get('/report', [ExpenseController::class, 'report'])->middleware('permission:expenses.report');
        Route::get('/profitability', [ExpenseController::class, 'profitability'])->middleware('permission:expenses.report');
        Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expenses.read');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expenses.manage');
        Route::put('/{uid}', [ExpenseController::class, 'update'])->middleware('permission:expenses.manage');
        Route::delete('/{uid}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.manage');
    });

    Route::prefix('purchases')->group(function () {
        Route::get('/payables', [PurchaseOrderController::class, 'payables'])->middleware('permission:purchases.read');
        Route::get('/orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchases.read');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchases.manage');
        Route::get('/orders/{uid}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchases.read');
        Route::get('/orders/{uid}/receipts', [PurchaseOrderController::class, 'receipts'])->middleware('permission:purchases.read');
        Route::put('/orders/{uid}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchases.manage');
        Route::post('/orders/{uid}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:purchases.manage');
        Route::post('/orders/{uid}/receive-partial', [PurchaseOrderController::class, 'receivePartial'])->middleware('permission:purchases.manage');
        Route::post('/orders/{uid}/receive', [PurchaseOrderController::class, 'markReceived'])->middleware('permission:purchases.manage');
        Route::post('/orders/{uid}/payments', [PurchaseOrderController::class, 'registerPayment'])->middleware('permission:purchases.manage');
    });

    Route::prefix('opportunities')->group(function () {
        Route::get('/stages', [OpportunityController::class, 'stages'])->middleware('permission:opportunities.read');
        Route::post('/stages', [OpportunityController::class, 'storeStage'])->middleware('permission:opportunities.manage');
        Route::put('/stages/{uid}', [OpportunityController::class, 'updateStage'])->middleware('permission:opportunities.manage');
        Route::delete('/stages/{uid}', [OpportunityController::class, 'destroyStage'])->middleware('permission:opportunities.manage');
        Route::get('/board', [OpportunityController::class, 'board'])->middleware('permission:opportunities.read');
        Route::get('/summary', [OpportunityController::class, 'summary'])->middleware('permission:opportunities.read');
        Route::get('/', [OpportunityController::class, 'index'])->middleware('permission:opportunities.read');
        Route::post('/', [OpportunityController::class, 'store'])->middleware('permission:opportunities.manage');
        Route::put('/{uid}', [OpportunityController::class, 'update'])->middleware('permission:opportunities.manage');
        Route::delete('/{uid}', [OpportunityController::class, 'destroy'])->middleware('permission:opportunities.manage');
    });

    Route::prefix('finance')->group(function () {
        Route::get('/records', [FinancialOperationsController::class, 'index'])->middleware('permission:finance.read');
        Route::post('/import', [FinancialOperationsController::class, 'import'])->middleware('permission:finance.manage');
        Route::get('/invoices', [FinancialOperationsController::class, 'invoices'])->middleware('permission:finance.read');
        Route::post('/invoices', [FinancialOperationsController::class, 'createInvoice'])->middleware('permission:finance.manage');
        Route::get('/payments', [FinancialOperationsController::class, 'payments'])->middleware('permission:finance.read');
        Route::post('/payments', [FinancialOperationsController::class, 'registerPayment'])->middleware('permission:finance.manage');
        Route::get('/alerts', [FinancialOperationsController::class, 'alerts'])->middleware('permission:finance.read');
        Route::post('/sync-overdue', [FinancialOperationsController::class, 'syncOverdueInvoices'])->middleware('permission:finance.manage');
        Route::get('/credit/{type}/{uid}', [FinancialOperationsController::class, 'creditSummary'])->middleware('permission:finance.read');
        Route::put('/credit/{type}/{uid}', [FinancialOperationsController::class, 'updateCreditProfile'])->middleware('permission:finance.manage');
        Route::get('/customer/{type}/{uid}/summary', [FinancialOperationsController::class, 'customerSummary'])->middleware('permission:finance.read');
        Route::get('/dashboard', [FinancialOperationsController::class, 'dashboard'])->middleware('permission:finance.read');
    });

    Route::prefix('currency')->group(function () {
        Route::get('/rates', [CurrencyController::class, 'rates'])->middleware('permission:finance.read');
        Route::post('/rates', [CurrencyController::class, 'storeRate'])->middleware('permission:finance.manage');
        Route::post('/convert', [CurrencyController::class, 'convert'])->middleware('permission:finance.read');
    });

    Route::prefix('payments')->group(function () {
        Route::post('/', [FinancialOperationsController::class, 'registerPayment'])->middleware('permission:finance.manage');
        Route::get('/{invoiceUid}', [FinancialOperationsController::class, 'paymentHistory'])->middleware('permission:finance.read');
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
