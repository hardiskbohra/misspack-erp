<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeePayslipController;
use App\Http\Controllers\EmployeeWorkspaceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CashflowAttachmentController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\CashflowSettingController;
use App\Http\Controllers\PartyStatementController;
use App\Http\Controllers\ClientPortalStatementController;
use App\Http\Controllers\PriceCalculatorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PublicLeadController;
use App\Http\Controllers\LeadSettingController;
use App\Http\Controllers\LeadCommentController;
use App\Http\Controllers\LeadQuoteController;
use App\Http\Controllers\ProjectAttachmentController;
use App\Http\Controllers\ProjectCommentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPaymentController;
use App\Http\Controllers\ProjectProductController;
use App\Http\Controllers\ProjectTrackingController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\PublicProjectController;
use App\Http\Controllers\ClientPortalAuthController;
use App\Http\Controllers\ClientPortalAccountController;
use App\Http\Controllers\ClientPortalSupportController;
use App\Http\Controllers\ClientPortalSupportManagementController;
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
use App\Http\Controllers\PurchaseInvoiceController;
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
/* A purchase document's public print link: the token is the key, no login. */
Route::get('/public-purchase-invoices/{token}', [PurchaseInvoiceController::class, 'publicShow'])->name('purchase-invoices.public');

// Public lead enquiry form. Keep outside auth middleware.
Route::get('/lead-enquiry', [PublicLeadController::class, 'create'])->name('leads.public.create');
Route::get('/lead-public/{token}', [PublicLeadController::class, 'show'])->name('leads.public.show');
Route::post('/lead-enquiry', [PublicLeadController::class, 'store'])->name('leads.public.store');

Route::get('/project-portal/{token}', [PublicProjectController::class, 'show'])->name('projects.public.show');

/* A statement of account, sent as a link. Outside auth on purpose: the
   accountant, the vendor's office and the client's finance person are not users
   of this ERP. The token is the whole of the authentication — long, revocable,
   expiring, and every open is counted. */
Route::get('/statement/{token}', [PartyStatementController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('statements.public');
Route::post('/project-portal/{token}/comments', [PublicProjectController::class, 'storeComment'])->name('projects.public.comments.store');
Route::post('/project-portal/{token}/attachments', [PublicProjectController::class, 'storeAttachment'])->name('projects.public.attachments.store');

// ── Protected ────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    /* The office's half of the application. Everything that has been built so
       far is here — the ledger, the clients, the shipments, the paperwork — and
       an employee account is not allowed any of it: the middleware turns an
       employee around at the door and sends them to their own workspace. */
    Route::middleware('office')->group(function () {
    
        Route::get('/clients/{client}/portal', [ClientPortalManagementController::class, 'show'])->name('clients.portal.show');
        Route::post('/clients/{client}/portal', [ClientPortalManagementController::class, 'store'])->name('clients.portal.store');
        Route::post('/clients/{client}/portal/reset-password', [ClientPortalManagementController::class, 'resetPassword'])->name('clients.portal.resetPassword');
        Route::post('/clients/{client}/portal/users/{portalUser}/reset-password', [ClientPortalManagementController::class, 'resetPortalUserPassword'])->name('clients.portal.users.resetPassword');
        Route::post('/clients/{client}/portal/users/{portalUser}/mark-shared', [ClientPortalManagementController::class, 'markPortalUserShared'])->name('clients.portal.users.markShared');
        Route::post('/clients/{client}/portal/mark-shared', [ClientPortalManagementController::class, 'markShared'])->name('clients.portal.markShared');
        Route::post('/clients/{client}/portal/notifications', [ClientPortalManagementController::class, 'storeNotification'])->name('clients.portal.notifications.store');
        Route::post('/clients/{client}/portal/documents', [ClientPortalManagementController::class, 'storeDocument'])->name('clients.portal.documents.store');
        Route::get('/clients/{client}/portal/support', [ClientPortalSupportManagementController::class, 'index'])->name('clients.portal.support.index');
        Route::get('/clients/{client}/portal/support/{conversation}', [ClientPortalSupportManagementController::class, 'show'])->name('clients.portal.support.show');
        Route::post('/clients/{client}/portal/support/{conversation}/messages', [ClientPortalSupportManagementController::class, 'reply'])->name('clients.portal.support.reply');
        Route::patch('/clients/{client}/portal/support/{conversation}/status', [ClientPortalSupportManagementController::class, 'updateStatus'])->name('clients.portal.support.status');
        Route::get('/clients/{client}/portal/documents/{document}/file', [ClientPortalManagementController::class, 'downloadDocument'])->name('clients.portal.documents.file');

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // User Management (CRUD — all handled via modal on index page)
        Route::get('/users',             [UserController::class, 'index'])->name('users.index');
        /* The same rows under the same filters, as a spreadsheet. Registered
           before /users/{user} so 'export' is never read as a user id. */
        Route::get('/users/export',      [UserController::class, 'export'])->name('users.export');
        Route::post('/users',            [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}',       [UserController::class, 'show'])->name('users.show');

        /* A person's record: their payslips and their papers. Every action takes
           the person *and* the row, and the controller checks the row belongs to
           the person, so a hand-typed URL cannot reach somebody else's file. */
        Route::post('/users/{user}/payslips', [EmployeePayslipController::class, 'store'])->name('users.payslips.store');
        Route::put('/users/{user}/payslips/{payslip}', [EmployeePayslipController::class, 'update'])->name('users.payslips.update');
        Route::delete('/users/{user}/payslips/{payslip}', [EmployeePayslipController::class, 'destroy'])->name('users.payslips.destroy');
        Route::get('/users/{user}/payslips/{payslip}/file', [EmployeePayslipController::class, 'file'])->name('users.payslips.file');
        /* The slip as paper: rendered from the row, never stored, so the printed
           document cannot be older than the figures it came from. */
        Route::get('/users/{user}/payslips/{payslip}/pdf', [EmployeePayslipController::class, 'pdf'])->name('users.payslips.pdf');
        Route::post('/users/{user}/documents', [EmployeeDocumentController::class, 'store'])->name('users.documents.store');
        Route::patch('/users/{user}/documents/{document}/verify', [EmployeeDocumentController::class, 'verify'])->name('users.documents.verify');
        Route::delete('/users/{user}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('users.documents.destroy');
        Route::get('/users/{user}/documents/{document}/file', [EmployeeDocumentController::class, 'file'])->name('users.documents.file');
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
        Route::get('/shipments/party-lookup', [ShipmentController::class, 'partyLookup'])->name('shipments.party-lookup');
        Route::get('/shipments/{shipment}/shipping-mark', [ShipmentController::class, 'shippingMark'])->name('shipments.shipping-mark');
        Route::get('/shipments/{shipment}/print/{document}', [ShipmentController::class, 'printPack'])->name('shipments.print');
        Route::post('/shipments/saved-views', [ShipmentController::class, 'storeSavedView'])->name('shipments.saved-views.store');
        Route::delete('/shipments/saved-views/{savedView}', [ShipmentController::class, 'destroySavedView'])->name('shipments.saved-views.destroy');
        Route::get('/shipments/stickers', [ShipmentController::class, 'stickers'])->name('shipments.stickers');
        Route::post('/shipments/{shipment}/costs', [ShipmentController::class, 'storeCost'])->name('shipments.costs.store');
        Route::put('/shipments/costs/{cost}', [ShipmentController::class, 'updateCost'])->name('shipments.costs.update');
        Route::delete('/shipments/costs/{cost}', [ShipmentController::class, 'destroyCost'])->name('shipments.costs.destroy');
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
        Route::post('/clients/saved-views', [ClientController::class, 'storeSavedView'])->name('clients.saved-views.store');
        Route::delete('/clients/saved-views/{savedView}', [ClientController::class, 'destroySavedView'])->name('clients.saved-views.destroy');
        Route::patch('/clients/{client}/send-kyc', [ClientController::class, 'sendKyc'])->name('clients.sendKyc');
        Route::patch('/clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status.update');
        Route::resource('clients', ClientController::class);

        // Vendor Management
        /* The list's own vocabulary routes come first: `/vendors/saved-views`
           read by the resource route below is a vendor whose id is
           "saved-views". */
        Route::post('/vendors/quick', [VendorController::class, 'quickStore'])->name('vendors.quickStore');
        Route::post('/vendors/saved-views', [VendorController::class, 'storeSavedView'])->name('vendors.saved-views.store');
        Route::delete('/vendors/saved-views/{savedView}', [VendorController::class, 'destroySavedView'])->name('vendors.saved-views.destroy');

        /* Acting on the list as a whole: one status change across ticked rows,
           and the payable book as a spreadsheet for the accountant. Both are
           literals, so they are registered before the resource route that
           would otherwise read them as a vendor id. */
        Route::patch('/vendors/bulk-status', [VendorController::class, 'bulkStatus'])->name('vendors.bulk-status');
        Route::get('/vendors/payables/export', [VendorController::class, 'exportPayables'])->name('vendors.payables.export');

        Route::post('/vendors/{vendor}/contacts', [VendorController::class, 'storeContact'])->name('vendors.contacts.store');
        Route::put('/vendors/{vendor}/contacts/{contact}', [VendorController::class, 'updateContact'])->name('vendors.contacts.update');
        Route::delete('/vendors/{vendor}/contacts/{contact}', [VendorController::class, 'destroyContact'])->name('vendors.contacts.destroy');

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
        /* The list's own vocabulary routes come first: `/sales-invoices/export`
           read by the resource route below is an invoice whose id is "export". */
        Route::get('/sales-invoices/export', [SalesInvoiceController::class, 'export'])->name('sales-invoices.export');
        /* the month-end paperwork: the same filters, grouped by HSN and rate */
        Route::get('/sales-invoices/gst-export', [SalesInvoiceController::class, 'gstExport'])->name('sales-invoices.gstExport');
        /* one action, many rows — the row menu's own actions, in a loop */
        Route::post('/sales-invoices/bulk', [SalesInvoiceController::class, 'bulk'])->name('sales-invoices.bulk');
        Route::post('/sales-invoices/{salesInvoice}/reminders', [SalesInvoiceController::class, 'logReminder'])->name('sales-invoices.reminders.store');
        Route::post('/sales-invoices/saved-views', [SalesInvoiceController::class, 'storeSavedView'])->name('sales-invoices.saved-views.store');
        Route::delete('/sales-invoices/saved-views/{savedView}', [SalesInvoiceController::class, 'destroySavedView'])->name('sales-invoices.saved-views.destroy');
        Route::get('/sales-invoices/{salesInvoice}/print', [SalesInvoiceController::class, 'print'])->name('sales-invoices.print');
        Route::patch('/sales-invoices/{salesInvoice}/mark-sent', [SalesInvoiceController::class, 'markSent'])->name('sales-invoices.markSent');
        Route::patch('/sales-invoices/{salesInvoice}/portal', [SalesInvoiceController::class, 'togglePortal'])->name('sales-invoices.portal');
        Route::post('/sales-invoices/{salesInvoice}/payments', [SalesInvoiceController::class, 'recordPayment'])->name('sales-invoices.payments.store');
        Route::post('/sales-invoices/{salesInvoice}/duplicate', [SalesInvoiceController::class, 'duplicate'])->name('sales-invoices.duplicate');
        Route::post('/sales-invoices/{salesInvoice}/convert', [SalesInvoiceController::class, 'convert'])->name('sales-invoices.convert');
        Route::delete('/sales-invoice-attachments/{attachment}', [SalesInvoiceController::class, 'destroyAttachment'])->name('sales-invoices.attachments.destroy');
        Route::resource('sales-invoices', SalesInvoiceController::class);

        // Purchase Orders & Purchase Bills
        /* One controller, two documents: the route set decides whether it is
           looking at an order or a bill, the way `/sales-invoices` does for a
           proforma and a tax invoice. The vocabulary routes come first so a
           document action is not read as a document id. */
        foreach ([
            'purchase-orders' => 'order',
            'purchase-bills' => 'bill',
        ] as $purchasePath => $purchaseType) {
            Route::post("/{$purchasePath}/{purchaseInvoice}/payments", [PurchaseInvoiceController::class, 'recordPayment'])->name("{$purchasePath}.payments.store");
            Route::patch("/{$purchasePath}/{purchaseInvoice}/status", [PurchaseInvoiceController::class, 'updateStatus'])->name("{$purchasePath}.status");
            Route::get("/{$purchasePath}/{purchaseInvoice}/print", [PurchaseInvoiceController::class, 'print'])->name("{$purchasePath}.print");
            Route::post("/{$purchasePath}/{purchaseInvoice}/convert", [PurchaseInvoiceController::class, 'convert'])->name("{$purchasePath}.convert");
            /* The list's own work, before the routes that take a document id:
               `/purchase-orders/export` is a file, not an order whose id is
               "export". */
            Route::get("/{$purchasePath}/export", [PurchaseInvoiceController::class, 'export'])->name("{$purchasePath}.export");
            Route::get("/{$purchasePath}/gst-export", [PurchaseInvoiceController::class, 'gstExport'])->name("{$purchasePath}.gstExport");
            /* one action, many rows — the row menu's own actions, in a loop */
            Route::post("/{$purchasePath}/bulk", [PurchaseInvoiceController::class, 'bulk'])->name("{$purchasePath}.bulk");
            Route::post("/{$purchasePath}/saved-views", [PurchaseInvoiceController::class, 'storeSavedView'])->name("{$purchasePath}.saved-views.store");
            Route::delete("/{$purchasePath}/saved-views/{savedView}", [PurchaseInvoiceController::class, 'destroySavedView'])->name("{$purchasePath}.saved-views.destroy");
            Route::get("/{$purchasePath}/create", [PurchaseInvoiceController::class, 'create'])->name("{$purchasePath}.create");
            Route::post("/{$purchasePath}", [PurchaseInvoiceController::class, 'store'])->name("{$purchasePath}.store");
            Route::get("/{$purchasePath}", [PurchaseInvoiceController::class, 'index'])->name("{$purchasePath}.index");
        }
        Route::get('/purchase-orders/{purchaseInvoice}/edit', [PurchaseInvoiceController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('/purchase-orders/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->name('purchase-orders.update');
        Route::delete('/purchase-orders/{purchaseInvoice}', [PurchaseInvoiceController::class, 'destroy'])->name('purchase-orders.destroy');
        Route::get('/purchase-orders/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show'])->name('purchase-orders.show');

        Route::get('/purchase-bills/{purchaseInvoice}/edit', [PurchaseInvoiceController::class, 'edit'])->name('purchase-bills.edit');
        Route::put('/purchase-bills/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->name('purchase-bills.update');
        Route::delete('/purchase-bills/{purchaseInvoice}', [PurchaseInvoiceController::class, 'destroy'])->name('purchase-bills.destroy');
        Route::get('/purchase-bills/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show'])->name('purchase-bills.show');

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
        Route::get('/cashflows/reports/export', [CashflowController::class, 'exportReport'])->name('cashflows.reports.export');

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

        /* The bills behind the ledger. Registered before the resource route so
           /cashflows/documents is the archive rather than a missing entry. A
           document does not have to belong to an entry: /cashflow-attachments
           files one on its own, and linking it to an entry happens later. */
        Route::get('/cashflows/documents', [CashflowAttachmentController::class, 'index'])->name('cashflows.documents');
        /* the same filters, as one file for the accountant */
        Route::get('/cashflows/documents/pack', [CashflowAttachmentController::class, 'pack'])->name('cashflows.documents.pack');
        Route::post('/cashflows/{cashflow}/attachments', [CashflowAttachmentController::class, 'store'])->name('cashflows.attachments.store');
        Route::post('/cashflows/{cashflow}/attachments/link', [CashflowAttachmentController::class, 'link'])->name('cashflows.attachments.link');
        Route::post('/cashflow-attachments', [CashflowAttachmentController::class, 'storeStandalone'])->name('cashflows.attachments.storeStandalone');
        Route::delete('/cashflow-attachments/{attachment}', [CashflowAttachmentController::class, 'destroy'])->name('cashflows.attachments.destroy');

        /* Statements of account: the party-facing half of the ledger. Registered
           before the resource route for the same reason the archive is, or
           /cashflows/statements would be read as an entry id. A link is issued
           here, revoked here, and the statement itself is rebuilt from the ledger
           on every open — never stored, so it cannot go stale. */
        Route::get('/cashflows/statements', [PartyStatementController::class, 'index'])->name('cashflows.statements');
        Route::get('/cashflows/statements/{partyType}/{party}/pdf', [PartyStatementController::class, 'pdf'])
            ->whereIn('partyType', ['client', 'vendor'])
            ->whereNumber('party')
            ->name('cashflows.statements.pdf');
        Route::post('/cashflows/statements/shares', [PartyStatementController::class, 'store'])->name('cashflows.statements.shares.store');
        Route::patch('/cashflow-statement-shares/{share}/revoke', [PartyStatementController::class, 'revoke'])->name('cashflows.statements.shares.revoke');
        Route::delete('/cashflow-statement-shares/{share}', [PartyStatementController::class, 'destroy'])->name('cashflows.statements.shares.destroy');
        Route::get('/cashflows/statements/{partyType}/{party}', [PartyStatementController::class, 'show'])
            ->whereIn('partyType', ['client', 'vendor'])
            ->name('cashflows.statements.show');

        Route::post('/cashflows/saved-views', [CashflowController::class, 'storeSavedView'])->name('cashflows.saved-views.store');
        Route::delete('/cashflows/saved-views/{savedView}', [CashflowController::class, 'destroySavedView'])->name('cashflows.saved-views.destroy');
        Route::post('/cashflows/quick', [CashflowController::class, 'quickStore'])->name('cashflows.quickStore');
        Route::post('/cashflows/accounts', [CashflowController::class, 'storeAccount'])->name('cashflows.accounts.store');
        Route::post('/cashflows/categories', [CashflowController::class, 'storeCategory'])->name('cashflows.categories.store');
        Route::resource('cashflows', CashflowController::class);
    });

    /* What an employee may reach: their own record, from four angles. Every one
       of these reads the signed-in user and never a user id from the URL, which
       is why there is nothing here to guess at. The slips are one page — the
       salary page — and /my/payslips stays as a redirect for old links. */
    Route::prefix('my')->name('my.')->group(function () {
        Route::get('/', [EmployeeWorkspaceController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [EmployeeWorkspaceController::class, 'profile'])->name('profile');
        Route::put('/profile', [EmployeeWorkspaceController::class, 'updateProfile'])->name('profile.update');
        Route::put('/password', [EmployeeWorkspaceController::class, 'updatePassword'])->name('password.update');
        Route::get('/salary', [EmployeeWorkspaceController::class, 'salary'])->name('salary');
        Route::get('/payslips', [EmployeeWorkspaceController::class, 'payslips'])->name('payslips');
        Route::get('/payslips/{payslip}/file', [EmployeeWorkspaceController::class, 'payslipFile'])->name('payslips.file');
        Route::get('/payslips/{payslip}/pdf', [EmployeeWorkspaceController::class, 'payslipPdf'])->name('payslips.pdf');
        Route::get('/documents', [EmployeeWorkspaceController::class, 'documents'])->name('documents');
        Route::post('/documents', [EmployeeWorkspaceController::class, 'storeDocument'])->name('documents.store');
        Route::get('/documents/{document}/file', [EmployeeWorkspaceController::class, 'documentFile'])->name('documents.file');
        Route::delete('/documents/{document}', [EmployeeWorkspaceController::class, 'destroyDocument'])->name('documents.destroy');
    });

    /* Your own account, whoever you are — the page the user menu opens. It sits
       outside the `office` group on purpose: an employee and the office both
       have a name, an address, a mobile number and a password, and the menu in
       the shell is shown to both. Which fields each may write is one rule
       (`EmployeeAccess::ownEditableFields()`), asked by the controller. */
    /* Light or dark, for anybody signed in. It sat inside the `office` group
       while saying so in its own comment — so an employee pressing the top bar's
       Dark button was turned around at the door with an error, and the switch was
       a dead control on every page of their workspace. */
    Route::post('/theme/toggle', function () {
        session(['theme' => session('theme', 'dark') === 'dark' ? 'light' : 'dark']);

        return back();
    })->name('theme.toggle');

    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

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
        Route::get('/account', [ClientPortalAccountController::class, 'index'])->name('account.index');
        Route::put('/account/profile', [ClientPortalAccountController::class, 'updateProfile'])->name('account.profile.update');
        Route::put('/account/password', [ClientPortalAuthController::class, 'updatePassword'])->name('account.password.update');

        Route::get('/projects', [ClientPortalProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ClientPortalProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/attachments/{attachment}', [ClientPortalProjectController::class, 'attachmentFile'])->name('projects.attachments.file');
        Route::post('/projects/{project}/comments', [ClientPortalProjectController::class, 'storeComment'])->name('projects.comments.store');
        Route::post('/projects/{project}/documents', [ClientPortalProjectController::class, 'upload'])->name('projects.documents.store');

        Route::get('/shipments', [ClientPortalShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/{shipment}', [ClientPortalShipmentController::class, 'show'])->name('shipments.show');
        Route::get('/shipments/{shipment}/attachments/{attachment}', [ClientPortalShipmentController::class, 'file'])->name('shipments.attachments.file');
        Route::post('/shipments/{shipment}/comments', [ClientPortalShipmentController::class, 'storeComment'])->name('shipments.comments.store');
        Route::post('/shipments/{shipment}/documents', [ClientPortalShipmentController::class, 'upload'])->name('shipments.documents.store');

        Route::get('/quotations', [ClientPortalQuoteController::class, 'index'])->name('quotes.index');
        Route::get('/quotations/{quote}', [ClientPortalQuoteController::class, 'show'])->name('quotes.show');
        Route::post('/quotations/{quote}/comments', [ClientPortalQuoteController::class, 'storeComment'])->name('quotes.comments.store');

        Route::get('/invoices', [ClientPortalInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/sales/{invoice}/print', [ClientPortalInvoiceController::class, 'printSales'])->name('invoices.sales.print');
        Route::get('/invoices/sales/{invoice}/attachments/{attachment}', [ClientPortalInvoiceController::class, 'salesAttachmentFile'])->name('invoices.sales.attachments.file');
        Route::get('/invoices/sales/{invoice}', [ClientPortalInvoiceController::class, 'showSales'])->name('invoices.sales.show');
        Route::post('/invoices/sales/{invoice}/comments', [ClientPortalInvoiceController::class, 'storeSalesComment'])->name('invoices.sales.comments.store');
        Route::get('/products', [ClientPortalProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ClientPortalProductController::class, 'show'])->name('products.show');

        Route::get('/attachments', [ClientPortalDocumentController::class, 'index'])->name('attachments.index');
        Route::post('/attachments', [ClientPortalDocumentController::class, 'store'])->name('attachments.store');
        Route::get('/attachments/{document}/file', [ClientPortalDocumentController::class, 'file'])->name('attachments.file');
        Route::delete('/attachments/{document}', [ClientPortalDocumentController::class, 'destroy'])->name('attachments.destroy');

        Route::get('/support', [ClientPortalSupportController::class, 'index'])->name('support.index');
        Route::post('/support', [ClientPortalSupportController::class, 'store'])->middleware('throttle:10,1')->name('support.store');
        Route::get('/support/{conversation}', [ClientPortalSupportController::class, 'show'])->name('support.show');
        Route::post('/support/{conversation}/messages', [ClientPortalSupportController::class, 'reply'])->middleware('throttle:20,1')->name('support.messages.store');
        Route::patch('/support/{conversation}/close', [ClientPortalSupportController::class, 'close'])->name('support.close');
        Route::patch('/support/{conversation}/reopen', [ClientPortalSupportController::class, 'reopen'])->name('support.reopen');

        Route::get('/payments', [ClientPortalPaymentController::class, 'index'])->name('payments.index');

        /* Their own statement of account, without needing a link: the same
           document the Share button produces, from the same service. */
        Route::get('/statement', [ClientPortalStatementController::class, 'index'])->name('statement.index');
        Route::get('/kyc', [ClientPortalKycController::class, 'show'])->name('kyc.show');

        Route::get('/notifications', [ClientPortalNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [ClientPortalNotificationController::class, 'markRead'])->name('notifications.read');
        Route::patch('/notifications/read-all', [ClientPortalNotificationController::class, 'markAllRead'])->name('notifications.readAll');
    });
});
