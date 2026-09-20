<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\CashflowSettingController;
use App\Http\Controllers\PriceCalculatorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PublicLeadController;
use App\Http\Controllers\LeadSettingController;
use App\Http\Controllers\LeadCommentController;
use App\Http\Controllers\LeadQuoteController;
use App\Http\Controllers\VendorQuoteController;
use App\Http\Controllers\ProjectAttachmentController;
use App\Http\Controllers\ProjectCommentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPaymentController;
use App\Http\Controllers\ProjectProductController;
use App\Http\Controllers\ProjectTrackingController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\PublicProjectController;
use App\Http\Controllers\ClientPortalAuthController;
use App\Http\Controllers\ClientPortalDashboardController;
use App\Http\Controllers\ClientPortalDocumentController;
use App\Http\Controllers\ClientPortalInvoiceController;
use App\Http\Controllers\ClientPortalKycController;
use App\Http\Controllers\ClientPortalManagementController;
use App\Http\Controllers\ClientPortalNotificationController;
use App\Http\Controllers\ClientPortalPaymentController;
use App\Http\Controllers\ClientPortalProductController;
use App\Http\Controllers\ClientPortalProjectController;
use App\Http\Controllers\ClientPortalQuoteController;
use App\Http\Controllers\ClientPortalShipmentController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\SalesInvoiceController;

// Redirect root to login
Route::get('/', fn () => redirect()->route('login'));

// ── Auth ─────────────────────────────────────────────────────
Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Shipment Tracking
Route::get('/track-shipment/{token}', [ShipmentController::class, 'publicTrack'])->name('shipments.publicTrack');

// Client KYC
Route::get('/client-kyc/{token}', [ClientController::class, 'publicKyc'])->name('clients.publicKyc');
Route::post('/client-kyc/{token}', [ClientController::class, 'submitKyc'])->name('clients.publicKyc.submit');

// Public product and quote link
Route::get('/public-products/{token}', [ProductController::class, 'publicShow'])->name('products.public');

/* Public invoice print/client portal link */
Route::get('/public-sales-invoices/{token}', [SalesInvoiceController::class, 'publicShow'])->name('sales-invoices.public');

// Public lead enquiry form. Keep outside auth middleware.
Route::get('/lead-enquiry', [PublicLeadController::class, 'create'])->name('leads.public.create');
Route::get('/lead-public/{token}', [PublicLeadController::class, 'show'])->name('leads.public.show');
Route::post('/lead-enquiry', [PublicLeadController::class, 'store'])->name('leads.public.store');

Route::get('/project-portal/{token}', [PublicProjectController::class, 'show'])->name('projects.public.show');
Route::post('/project-portal/{token}/comments', [PublicProjectController::class, 'storeComment'])->name('projects.public.comments.store');
Route::post('/project-portal/{token}/attachments', [PublicProjectController::class, 'storeAttachment'])->name('projects.public.attachments.store');

// ── Protected ────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    
    Route::get('/clients/{client}/portal', [ClientPortalManagementController::class, 'show'])->name('clients.portal.show');
    Route::post('/clients/{client}/portal', [ClientPortalManagementController::class, 'store'])->name('clients.portal.store');
    Route::post('/clients/{client}/portal/reset-password', [ClientPortalManagementController::class, 'resetPassword'])->name('clients.portal.resetPassword');
    Route::post('/clients/{client}/portal/mark-shared', [ClientPortalManagementController::class, 'markShared'])->name('clients.portal.markShared');
    Route::post('/clients/{client}/portal/notifications', [ClientPortalManagementController::class, 'storeNotification'])->name('clients.portal.notifications.store');
    Route::post('/clients/{client}/portal/invoices', [ClientPortalManagementController::class, 'storeInvoice'])->name('clients.portal.invoices.store');
    Route::delete('/client-portal-invoices/{invoice}', [ClientPortalManagementController::class, 'destroyInvoice'])->name('clients.portal.invoices.destroy');

    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // User Management (CRUD — all handled via modal on index page)
    Route::get('/users',             [UserController::class, 'index'])->name('users.index');
    Route::post('/users',            [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/data', [UserController::class, 'getData'])->name('users.data');
    Route::put('/users/{user}',      [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}',   [UserController::class, 'destroy'])->name('users.destroy');

    // Task Management
    Route::patch('/tasks/mark-all', [TaskController::class, 'markAll'])->name('tasks.markAll');
    Route::patch('/tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status.update');
    Route::resource('tasks', TaskController::class);

    // Shipment Management
    Route::post('/shipments/quick', [ShipmentController::class, 'quickStore'])->name('shipments.quickStore');
    Route::post('/shipments/{shipment}/history', [ShipmentController::class, 'storeHistory'])->name('shipments.history.store');
    Route::post('/shipments/{shipment}/history', [ShipmentController::class, 'storeHistory'])->name('shipments.history.store');
    Route::put('/shipments/history/{history}', [ShipmentController::class, 'updateHistory'])->name('shipments.history.update');
    Route::delete('/shipments/history/{history}', [ShipmentController::class, 'destroyHistory'])->name('shipments.history.destroy');
    Route::post('/shipments/{shipment}/attachments', [ShipmentController::class, 'storeAttachment'])->name('shipments.attachments.store');
    Route::put('/shipments/attachments/{attachment}', [ShipmentController::class, 'updateAttachment'])->name('shipments.attachments.update');
    Route::delete('/shipments/attachments/{attachment}', [ShipmentController::class, 'destroyAttachment'])->name('shipments.attachments.destroy');
    Route::put('/shipments/attachments/{attachment}/toggle-public', [ShipmentController::class, 'togglePublic'])->name('shipments.attachments.toggle-public');
    Route::resource('shipments', ShipmentController::class);

    // Client Management
    Route::post('/clients/quick', [ClientController::class, 'quickStore'])->name('clients.quickStore');
    Route::patch('/clients/{client}/send-kyc', [ClientController::class, 'sendKyc'])->name('clients.sendKyc');
    Route::patch('/clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status.update');
    Route::resource('clients', ClientController::class);

    // Vendor Management
    Route::post('/vendors/quick', [VendorController::class, 'quickStore'])->name('vendors.quickStore');

    Route::post('/vendors/{vendor}/attachments', [VendorController::class, 'storeAttachment'])->name('vendors.attachments.store');
    Route::delete('/vendor-attachments/{attachment}', [VendorController::class, 'destroyAttachment'])->name('vendors.attachments.destroy');

    Route::post('/vendors/{vendor}/payments', [VendorController::class, 'storePayment'])->name('vendors.payments.store');
    Route::put('/vendors/{vendor}/payments/{entry}', [VendorController::class, 'updatePayment'])->name('vendors.payments.update');
    Route::delete('/vendor-payment-entries/{entry}', [VendorController::class, 'destroyPayment'])->name('vendors.payments.destroy');
    Route::delete('/vendor-payment-attachments/{attachment}', [VendorController::class, 'destroyPaymentAttachment'])->name('vendors.payments.attachments.destroy');

    Route::post('/vendors/{vendor}/comments', [VendorController::class, 'storeComment'])->name('vendors.comments.store');
    Route::delete('/vendor-comments/{comment}', [VendorController::class, 'destroyComment'])->name('vendors.comments.destroy');
    Route::resource('vendors', VendorController::class);
    
    // Product Management
    Route::resource('products', ProductController::class);
    Route::post('/products/quick', [ProductController::class, 'quickStore'])->name('products.quickStore');
    
    // Invoice Management
    Route::get('/sales-invoices/{salesInvoice}/print', [SalesInvoiceController::class, 'print'])->name('sales-invoices.print');
    Route::patch('/sales-invoices/{salesInvoice}/mark-sent', [SalesInvoiceController::class, 'markSent'])->name('sales-invoices.markSent');
    Route::delete('/sales-invoice-attachments/{attachment}', [SalesInvoiceController::class, 'destroyAttachment'])->name('sales-invoices.attachments.destroy');
    Route::resource('sales-invoices', SalesInvoiceController::class);

    // Price Calculator
    Route::get('/price-calculator', [PriceCalculatorController::class, 'index'])->name('price-calculator.index');
    
    // Lead Settings Management
    Route::get('/leads/settings', [LeadSettingController::class, 'index'])->name('leads.settings.index');
    Route::post('/leads/settings/options', [LeadSettingController::class, 'store'])->name('leads.settings.store');
    Route::put('/leads/settings/options/{option}', [LeadSettingController::class, 'update'])->name('leads.settings.update');
    Route::delete('/leads/settings/options/{option}', [LeadSettingController::class, 'destroy'])->name('leads.settings.destroy');

    // Lead Comments Management
    Route::post('/leads/{lead}/comments', [LeadCommentController::class, 'store'])->name('leads.comments.store');
    Route::delete('/lead-comments/{comment}', [LeadCommentController::class, 'destroy'])->name('leads.comments.destroy');

    // Lead-Quote Management
    Route::patch('/lead-quotes/{leadQuote}/status', [LeadQuoteController::class, 'updateStatus'])->name('lead-quotes.status.update');
    Route::resource('lead-quotes', LeadQuoteController::class)->parameters([
        'lead-quotes' => 'leadQuote',
    ]);

    // Lead Management
    Route::post('/leads/quick', [LeadController::class, 'quickStore'])->name('leads.quickStore');
    Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
    Route::get('/leads/{lead}/image', [LeadController::class, 'image'])->name('leads.image');
    Route::resource('leads', LeadController::class);

    // Vendor Quote Management
    Route::post('/vendor-quotes/quick', [VendorQuoteController::class, 'quickStore'])->name('vendor-quotes.quickStore');
    Route::get('/vendor-quotes/{vendorQuote}/image', [VendorQuoteController::class, 'image'])->name('vendor-quotes.image');
    Route::resource('vendor-quotes', VendorQuoteController::class)->parameters([
        'vendor-quotes' => 'vendorQuote',
    ]);
    
    // Project Management
    Route::post('/projects/quick', [ProjectController::class, 'quickStore'])->name('projects.quickStore');
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status.update');

    Route::post('/projects/{project}/products', [ProjectProductController::class, 'store'])->name('projects.products.store');
    Route::put('/project-products/{projectProduct}', [ProjectProductController::class, 'update'])->name('projects.products.update');
    Route::delete('/project-products/{projectProduct}', [ProjectProductController::class, 'destroy'])->name('projects.products.destroy');

    Route::post('/projects/{project}/comments', [ProjectCommentController::class, 'store'])->name('projects.comments.store');
    Route::put('/project-comments/{projectComment}', [ProjectCommentController::class, 'update'])->name('projects.comments.update');
    Route::delete('/project-comments/{projectComment}', [ProjectCommentController::class, 'destroy'])->name('projects.comments.destroy');

    Route::post('/projects/{project}/attachments', [ProjectAttachmentController::class, 'store'])->name('projects.attachments.store');
    Route::patch('/project-attachments/{projectAttachment}', [ProjectAttachmentController::class, 'update'])->name('projects.attachments.update');
    Route::delete('/project-attachments/{projectAttachment}', [ProjectAttachmentController::class, 'destroy'])->name('projects.attachments.destroy');

    Route::post('/projects/{project}/tracking', [ProjectTrackingController::class, 'store'])->name('projects.tracking.store');
    Route::put('/project-tracking/{trackingUpdate}', [ProjectTrackingController::class, 'update'])->name('projects.tracking.update');
    Route::delete('/project-tracking/{trackingUpdate}', [ProjectTrackingController::class, 'destroy'])->name('projects.tracking.destroy');

    Route::post('/projects/{project}/payments', [ProjectPaymentController::class, 'store'])->name('projects.payments.store');
    Route::put('/project-payments/{projectPayment}', [ProjectPaymentController::class, 'update'])->name('projects.payments.update');
    Route::delete('/project-payments/{projectPayment}', [ProjectPaymentController::class, 'destroy'])->name('projects.payments.destroy');
    
    Route::post('/projects/{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('projects.milestones.store');
    Route::post('/projects/{project}/milestones/defaults', [ProjectMilestoneController::class, 'generateDefaults'])->name('projects.milestones.defaults');
    Route::patch('/project-milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('projects.milestones.update');
    Route::delete('/project-milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('projects.milestones.destroy');
    
    Route::resource('projects', ProjectController::class);

    // Cashflow Management
    Route::get('/cashflows/reports', [CashflowController::class, 'reports'])->name('cashflows.reports');
    Route::get('/cashflows/reports/pdf', [CashflowController::class, 'downloadPdf'])->name('cashflows.reports.pdf');

    Route::get('/cashflows/settings', [CashflowSettingController::class, 'index'])->name('cashflows.settings.index');
    Route::post('/cashflows/settings/accounts', [CashflowSettingController::class, 'storeAccount'])->name('cashflows.settings.accounts.store');
    Route::put('/cashflows/settings/accounts/{account}', [CashflowSettingController::class, 'updateAccount'])->name('cashflows.settings.accounts.update');
    Route::delete('/cashflows/settings/accounts/{account}', [CashflowSettingController::class, 'destroyAccount'])->name('cashflows.settings.accounts.destroy');
    Route::post('/cashflows/settings/categories', [CashflowSettingController::class, 'storeCategory'])->name('cashflows.settings.categories.store');
    Route::put('/cashflows/settings/categories/{category}', [CashflowSettingController::class, 'updateCategory'])->name('cashflows.settings.categories.update');
    Route::delete('/cashflows/settings/categories/{category}', [CashflowSettingController::class, 'destroyCategory'])->name('cashflows.settings.categories.destroy');
    Route::post('/cashflows/settings/masters', [CashflowSettingController::class, 'storeMaster'])->name('cashflows.settings.masters.store');
    Route::put('/cashflows/settings/masters/{master}', [CashflowSettingController::class, 'updateMaster'])->name('cashflows.settings.masters.update');
    Route::delete('/cashflows/settings/masters/{master}', [CashflowSettingController::class, 'destroyMaster'])->name('cashflows.settings.masters.destroy');

    Route::post('/cashflows/quick', [CashflowController::class, 'quickStore'])->name('cashflows.quickStore');
    Route::post('/cashflows/accounts', [CashflowController::class, 'storeAccount'])->name('cashflows.accounts.store');
    Route::post('/cashflows/categories', [CashflowController::class, 'storeCategory'])->name('cashflows.categories.store');
    Route::resource('cashflows', CashflowController::class);

    // Theme toggle
    Route::post('/theme/toggle', function () {
        session(['theme' => session('theme', 'dark') === 'dark' ? 'light' : 'dark']);
        return back();
    })->name('theme.toggle');
});

Route::prefix('client-portal')->name('client-portal.')->group(function () {
    Route::get('/login', [ClientPortalAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [ClientPortalAuthController::class, 'login'])->name('login.submit');

    Route::middleware('client.portal')->group(function () {
        Route::post('/logout', [ClientPortalAuthController::class, 'logout'])->name('logout');
        Route::get('/change-password', [ClientPortalAuthController::class, 'editPassword'])->name('password.edit');
        Route::post('/change-password', [ClientPortalAuthController::class, 'updatePassword'])->name('password.update');

        Route::get('/', [ClientPortalDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [ClientPortalDashboardController::class, 'index'])->name('dashboard.alias');

        Route::get('/projects', [ClientPortalProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ClientPortalProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}/comments', [ClientPortalProjectController::class, 'storeComment'])->name('projects.comments.store');
        Route::post('/projects/{project}/documents', [ClientPortalProjectController::class, 'upload'])->name('projects.documents.store');

        Route::get('/shipments', [ClientPortalShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/{shipment}', [ClientPortalShipmentController::class, 'show'])->name('shipments.show');
        Route::post('/shipments/{shipment}/comments', [ClientPortalShipmentController::class, 'storeComment'])->name('shipments.comments.store');
        Route::post('/shipments/{shipment}/documents', [ClientPortalShipmentController::class, 'upload'])->name('shipments.documents.store');

        Route::get('/quotations', [ClientPortalQuoteController::class, 'index'])->name('quotes.index');
        Route::get('/quotations/{quote}', [ClientPortalQuoteController::class, 'show'])->name('quotes.show');
        Route::post('/quotations/{quote}/comments', [ClientPortalQuoteController::class, 'storeComment'])->name('quotes.comments.store');

        Route::get('/invoices', [ClientPortalInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [ClientPortalInvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{invoice}/comments', [ClientPortalInvoiceController::class, 'storeComment'])->name('invoices.comments.store');

        Route::get('/products', [ClientPortalProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ClientPortalProductController::class, 'show'])->name('products.show');

        Route::get('/attachments', [ClientPortalDocumentController::class, 'index'])->name('attachments.index');
        Route::post('/attachments', [ClientPortalDocumentController::class, 'store'])->name('attachments.store');
        Route::delete('/attachments/{document}', [ClientPortalDocumentController::class, 'destroy'])->name('attachments.destroy');

        Route::get('/payments', [ClientPortalPaymentController::class, 'index'])->name('payments.index');
        Route::get('/kyc', [ClientPortalKycController::class, 'show'])->name('kyc.show');

        Route::get('/notifications', [ClientPortalNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [ClientPortalNotificationController::class, 'markRead'])->name('notifications.read');
        Route::patch('/notifications/read-all', [ClientPortalNotificationController::class, 'markAllRead'])->name('notifications.readAll');
    });
});
