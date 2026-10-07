<?php

use Illuminate\Http\Request;
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
use App\Http\Controllers\OfficeServiceController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CashflowAttachmentController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\CashflowRecurrenceController;
use App\Http\Controllers\CashflowSettingController;
use App\Http\Controllers\AssetSettingController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PartyStatementController;
use App\Http\Controllers\ClientPortalStatementController;
use App\Http\Controllers\PriceCalculatorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PublicLeadController;
use App\Http\Controllers\LeadSettingController;
use App\Http\Controllers\LeadCommentController;
use App\Http\Controllers\ProjectAttachmentController;
use App\Http\Controllers\ProjectCommentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectProductController;
use App\Http\Controllers\ProjectTrackingController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\PublicFeedbackController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ClientPortalFeedbackController;
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
use App\Http\Controllers\ClientPortalShipmentController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OfficeAlertController;
use App\Http\Controllers\OfficeBriefingSettingController;
use App\Http\Controllers\OrganisationController;
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

// Public product link
Route::get('/public-products/{token}', [ProductController::class, 'publicShow'])->name('products.public');

/* Public invoice print/client portal link */
Route::get('/public-sales-invoices/{token}', [SalesInvoiceController::class, 'publicShow'])->name('sales-invoices.public');
/* A purchase document's public print link: the token is the key, no login. */
Route::get('/public-purchase-invoices/{token}', [PurchaseInvoiceController::class, 'publicShow'])->name('purchase-invoices.public');

// Public lead enquiry form. Keep outside auth middleware.
Route::get('/lead-enquiry', [PublicLeadController::class, 'create'])->name('leads.public.create');
Route::get('/lead-public/{token}', [PublicLeadController::class, 'show'])->name('leads.public.show');
Route::post('/lead-enquiry', [PublicLeadController::class, 'store'])->name('leads.public.store');

/* A statement of account, sent as a link. Outside auth on purpose: the
   accountant, the vendor's office and the client's finance person are not users
   of this ERP. The token is the whole of the authentication — long, revocable,
   expiring, and every open is counted. */
Route::get('/statement/{token}', [PartyStatementController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('statements.public');

/* The feedback link. Outside auth for the same reason a statement link is: the
   person answering is a client, not a user of this ERP. The token is the whole
   of the authentication — 48 characters, expiring, revocable, every open
   counted — and the constraint keeps `/feedback/export` reading as an office
   page rather than as somebody's token. */
Route::get('/feedback/{token}', [PublicFeedbackController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('feedback.public.show');
Route::post('/feedback/{token}', [PublicFeedbackController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->middleware('throttle:10,1')
    ->name('feedback.public.store');
Route::get('/feedback/{token}/thanks', [PublicFeedbackController::class, 'thanks'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('feedback.public.thanks');

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

        Route::redirect('/dashboard', '/clients');
        Route::get('/search', GlobalSearchController::class)->name('search');

        /* =====================================================================
           SETTINGS — the one place every module's settings live.

           One module with a rail of areas. Each area's screen, validation and
           writes stay with the controller that has always owned them, and
           `App\Services\SettingsDirectory` is the single list both the hub and
           the rail are drawn from — an area cannot be in the menu and missing
           from the hub, or renamed in one and not the other.

           A **setting** here is a rule or a master list that changes how a
           module behaves for everybody. A module's own records — a product, a
           service, a user, a note — are that module's work, not its settings,
           and keep the pages they have. The boundary is written down in
           `docs/settings-module.md`.

           Every URL these five surfaces ever had keeps working: see the
           redirects where the old routes were.
           ===================================================================== */
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

        // Organisation — the company record every document prints from.
        Route::get('/settings/organisation', [OrganisationController::class, 'index'])->name('settings.organisation');
        Route::put('/settings/organisation', [OrganisationController::class, 'update'])->name('settings.organisation.update');
        Route::post('/settings/organisation/addresses', [OrganisationController::class, 'storeAddress'])->name('settings.organisation.addresses.store');
        Route::put('/settings/organisation/addresses/{address}', [OrganisationController::class, 'updateAddress'])->name('settings.organisation.addresses.update');
        Route::delete('/settings/organisation/addresses/{address}', [OrganisationController::class, 'destroyAddress'])->name('settings.organisation.addresses.destroy');
        Route::post('/settings/organisation/contacts', [OrganisationController::class, 'storeContact'])->name('settings.organisation.contacts.store');
        Route::put('/settings/organisation/contacts/{contact}', [OrganisationController::class, 'updateContact'])->name('settings.organisation.contacts.update');
        Route::delete('/settings/organisation/contacts/{contact}', [OrganisationController::class, 'destroyContact'])->name('settings.organisation.contacts.destroy');
        Route::post('/settings/organisation/socials', [OrganisationController::class, 'storeSocial'])->name('settings.organisation.socials.store');
        Route::put('/settings/organisation/socials/{social}', [OrganisationController::class, 'updateSocial'])->name('settings.organisation.socials.update');
        Route::delete('/settings/organisation/socials/{social}', [OrganisationController::class, 'destroySocial'])->name('settings.organisation.socials.destroy');
        Route::post('/settings/organisation/banks', [OrganisationController::class, 'storeBank'])->name('settings.organisation.banks.store');
        Route::put('/settings/organisation/banks/{bank}', [OrganisationController::class, 'updateBank'])->name('settings.organisation.banks.update');
        Route::delete('/settings/organisation/banks/{bank}', [OrganisationController::class, 'destroyBank'])->name('settings.organisation.banks.destroy');

        // Briefings — what the office is told about, and who is emailed.
        Route::get('/settings/briefings', [OfficeBriefingSettingController::class, 'index'])->name('settings.briefings');
        Route::put('/settings/briefings', [OfficeBriefingSettingController::class, 'update'])->name('settings.briefings.update');

        // Leads — the dropdown master data the lead forms offer.
        Route::get('/settings/leads', [LeadSettingController::class, 'index'])->name('settings.leads');
        Route::post('/settings/leads/options', [LeadSettingController::class, 'store'])->name('settings.leads.store');
        Route::put('/settings/leads/options/{option}', [LeadSettingController::class, 'update'])->name('settings.leads.update');
        Route::delete('/settings/leads/options/{option}', [LeadSettingController::class, 'destroy'])->name('settings.leads.destroy');

        // Feedback — the scorecard lines the client scores.
        Route::get('/settings/feedback', [FeedbackController::class, 'settings'])->name('settings.feedback');
        Route::post('/settings/feedback/dimensions', [FeedbackController::class, 'storeDimension'])->name('settings.feedback.dimensions.store');
        Route::patch('/settings/feedback/dimensions/{dimension}', [FeedbackController::class, 'updateDimension'])->name('settings.feedback.dimensions.update');
        Route::delete('/settings/feedback/dimensions/{dimension}', [FeedbackController::class, 'destroyDimension'])->name('settings.feedback.dimensions.destroy');

        // Fixed assets — the classes, and the depreciation recipe they hand out.
        Route::get('/settings/assets', [AssetSettingController::class, 'index'])->name('settings.assets');
        Route::post('/settings/assets/categories', [AssetSettingController::class, 'store'])->name('settings.assets.store');
        Route::put('/settings/assets/categories/{category}', [AssetSettingController::class, 'update'])->name('settings.assets.update');
        Route::delete('/settings/assets/categories/{category}', [AssetSettingController::class, 'destroy'])->name('settings.assets.destroy');

        // Cashflow — the accounts, categories and option lists the ledger is kept in.
        Route::get('/settings/cashflow', [CashflowSettingController::class, 'index'])->name('settings.cashflow');
        Route::post('/settings/cashflow/accounts', [CashflowSettingController::class, 'storeAccount'])->name('settings.cashflow.accounts.store');
        Route::put('/settings/cashflow/accounts/{account}', [CashflowSettingController::class, 'updateAccount'])->name('settings.cashflow.accounts.update');
        Route::delete('/settings/cashflow/accounts/{account}', [CashflowSettingController::class, 'destroyAccount'])->name('settings.cashflow.accounts.destroy');
        Route::post('/settings/cashflow/categories', [CashflowSettingController::class, 'storeCategory'])->name('settings.cashflow.categories.store');
        Route::put('/settings/cashflow/categories/{category}', [CashflowSettingController::class, 'updateCategory'])->name('settings.cashflow.categories.update');
        Route::delete('/settings/cashflow/categories/{category}', [CashflowSettingController::class, 'destroyCategory'])->name('settings.cashflow.categories.destroy');
        Route::post('/settings/cashflow/masters', [CashflowSettingController::class, 'storeMaster'])->name('settings.cashflow.masters.store');
        Route::put('/settings/cashflow/masters/{master}', [CashflowSettingController::class, 'updateMaster'])->name('settings.cashflow.masters.update');
        Route::delete('/settings/cashflow/masters/{master}', [CashflowSettingController::class, 'destroyMaster'])->name('settings.cashflow.masters.destroy');
        Route::get('/office-alerts', [OfficeAlertController::class, 'inbox'])->name('office-alerts.inbox');
        Route::patch('/office-alerts/{office_alert}/seen', [OfficeAlertController::class, 'seen'])->name('office-alerts.seen');
        Route::patch('/office-alerts/{office_alert}/ack', [OfficeAlertController::class, 'ack'])->name('office-alerts.ack');
        Route::patch('/office-alerts/{office_alert}/snooze', [OfficeAlertController::class, 'snooze'])->name('office-alerts.snooze');
        Route::patch('/office-alerts/{office_alert}/popup', [OfficeAlertController::class, 'popupShown'])->name('office-alerts.popup');
        /* Every URL these five surfaces ever had stays open, and the query
           string travels with the reader: on these screens the tab *is* the
           address, and a bookmark of `?tab=categories` that lands on Accounts
           is a broken link, not a redirect. */
        // Settings moved to /settings/briefings. The old URL and its ?tab travel across.
        Route::get('/office-alerts/settings', fn (Request $request) => redirect()->to(route('settings.briefings', $request->query()), 301))->name('office-alerts.settings');

        // Settings moved to /settings/organisation. The old URL and its ?tab travel across.
        Route::get('/organisation', fn (Request $request) => redirect()->to(route('settings.organisation', $request->query()), 301))->name('organisation.settings');

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

        Route::resource('office-services', OfficeServiceController::class);
    
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

        // Purchase Orders & Purchase Bills — one UI, the way sales invoices
        // hold a proforma and a tax invoice. Vocabulary routes first so
        // /purchase-invoices/create is not read as an id.
        Route::post('/purchase-invoices/{purchaseInvoice}/payments', [PurchaseInvoiceController::class, 'recordPayment'])->name('purchase-invoices.payments.store');
        Route::patch('/purchase-invoices/{purchaseInvoice}/status', [PurchaseInvoiceController::class, 'updateStatus'])->name('purchase-invoices.status');
        Route::get('/purchase-invoices/{purchaseInvoice}/print', [PurchaseInvoiceController::class, 'print'])->name('purchase-invoices.print');
        Route::post('/purchase-invoices/{purchaseInvoice}/convert', [PurchaseInvoiceController::class, 'convert'])->name('purchase-invoices.convert');
        Route::post('/purchase-invoices/bulk', [PurchaseInvoiceController::class, 'bulk'])->name('purchase-invoices.bulk');
        Route::resource('purchase-invoices', PurchaseInvoiceController::class);

        /* Old split URLs keep working as a hop onto the one list. */
        Route::redirect('/purchase-orders', '/purchase-invoices?invoice_type=order');
        Route::redirect('/purchase-bills', '/purchase-invoices?invoice_type=bill');

        // Price Calculator
        Route::get('/price-calculator', [PriceCalculatorController::class, 'index'])->name('price-calculator.index');

        // Settings moved to /settings/leads. The old URL and its ?tab travel across.
        Route::get('/leads/settings', fn (Request $request) => redirect()->to(route('settings.leads', $request->query()), 301))->name('leads.settings.index');

        // Lead Comments Management
        Route::post('/leads/{lead}/comments', [LeadCommentController::class, 'store'])->name('leads.comments.store');
        Route::delete('/lead-comments/{comment}', [LeadCommentController::class, 'destroy'])->name('leads.comments.destroy');

        // Lead Management
        Route::post('/leads/quick', [LeadController::class, 'quickStore'])->name('leads.quickStore');
        Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
        Route::get('/leads/{lead}/image', [LeadController::class, 'image'])->name('leads.image');
        Route::resource('leads', LeadController::class);

        // Project Management
        Route::post('/projects/quick', [ProjectController::class, 'quickStore'])->name('projects.quickStore');
        Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status.update');

        /* One route: a project product is created by the invoice or purchase
           document that mentions it (`App\Services\ProjectProducts`) and is not
           deleted from the project — the row carries milestones, comments and
           attachments, and the office edits only what the project owns. */
        Route::put('/project-products/{projectProduct}', [ProjectProductController::class, 'update'])->name('projects.products.update');

        Route::post('/projects/{project}/comments', [ProjectCommentController::class, 'store'])->name('projects.comments.store');
        Route::put('/project-comments/{projectComment}', [ProjectCommentController::class, 'update'])->name('projects.comments.update');
        Route::delete('/project-comments/{projectComment}', [ProjectCommentController::class, 'destroy'])->name('projects.comments.destroy');

        Route::post('/projects/{project}/attachments', [ProjectAttachmentController::class, 'store'])->name('projects.attachments.store');
        Route::patch('/project-attachments/{projectAttachment}', [ProjectAttachmentController::class, 'update'])->name('projects.attachments.update');
        Route::delete('/project-attachments/{projectAttachment}', [ProjectAttachmentController::class, 'destroy'])->name('projects.attachments.destroy');

        Route::post('/projects/{project}/tracking', [ProjectTrackingController::class, 'store'])->name('projects.tracking.store');
        Route::put('/project-tracking/{trackingUpdate}', [ProjectTrackingController::class, 'update'])->name('projects.tracking.update');
        Route::delete('/project-tracking/{trackingUpdate}', [ProjectTrackingController::class, 'destroy'])->name('projects.tracking.destroy');

    
        Route::post('/projects/{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('projects.milestones.store');
        Route::post('/projects/{project}/milestones/defaults', [ProjectMilestoneController::class, 'generateDefaults'])->name('projects.milestones.defaults');
        Route::patch('/project-milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('projects.milestones.update');
        Route::delete('/project-milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('projects.milestones.destroy');
    
        /* Feedback Management.

           The link and the answer are two rows, so they are two resources: an
           ask is issued, reminded and revoked; an answer is read, consented and
           acted on. The vocabulary routes come before the wildcards, and every
           route name here is `feedback.*` so the sidebar can light one item for
           the whole module. */
        Route::get('/feedback/export', [FeedbackController::class, 'export'])->name('feedback.export');
        // Settings moved to /settings/feedback. The old URL and its ?tab travel across.
        Route::get('/feedback/settings', fn (Request $request) => redirect()->to(route('settings.feedback', $request->query()), 301))->name('feedback.settings');
        Route::post('/projects/{project}/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
        Route::get('/feedback/asks/{feedbackRequest}', [FeedbackController::class, 'show'])->name('feedback.show');
        Route::patch('/feedback-requests/{feedbackRequest}/revoke', [FeedbackController::class, 'revoke'])->name('feedback.revoke');
        Route::patch('/feedback-requests/{feedbackRequest}/shared', [FeedbackController::class, 'markShared'])->name('feedback.shared');
        Route::post('/feedback-requests/{feedbackRequest}/remind', [FeedbackController::class, 'remind'])->name('feedback.remind');
        Route::delete('/feedback-requests/{feedbackRequest}', [FeedbackController::class, 'destroy'])->name('feedback.destroy');
        Route::patch('/feedback-responses/{feedbackResponse}/consent', [FeedbackController::class, 'updateConsent'])->name('feedback.consent');
        Route::post('/feedback-responses/{feedbackResponse}/actions', [FeedbackController::class, 'storeAction'])->name('feedback.actions.store');
        Route::patch('/feedback-actions/{feedbackAction}', [FeedbackController::class, 'updateAction'])->name('feedback.actions.update');
        Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');

        Route::resource('projects', ProjectController::class);

        // Cashflow Management
        Route::get('/cashflows/reports', [CashflowController::class, 'reports'])->name('cashflows.reports');
        Route::get('/cashflows/reports/pdf', [CashflowController::class, 'downloadPdf'])->name('cashflows.reports.pdf');
        Route::get('/cashflows/reports/export', [CashflowController::class, 'exportReport'])->name('cashflows.reports.export');

        // Settings moved to /settings/cashflow. The old URL and its ?tab travel across.
        Route::get('/cashflows/settings', fn (Request $request) => redirect()->to(route('settings.cashflow', $request->query()), 301))->name('cashflows.settings.index');

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

        // Fixed assets — the register a private limited company has to keep.
        /* The prefix is `/fixed-assets`, not `/assets`: `public/assets` is the
           static directory the web server serves itself, and a route at
           `/assets` would never be reached. The register's own vocabulary
           routes come before the resource-style ones below, so `/fixed-assets/
           export` is never read as an asset whose id is "export". */
        Route::get('/fixed-assets/export', [FixedAssetController::class, 'export'])->name('assets.export');
        Route::get('/fixed-assets/depreciation', [FixedAssetController::class, 'depreciation'])->name('assets.depreciation');
        Route::get('/fixed-assets/depreciation/export', [FixedAssetController::class, 'depreciationExport'])->name('assets.depreciation.export');
        Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->name('assets.index');
        Route::post('/fixed-assets', [FixedAssetController::class, 'store'])->name('assets.store');
        Route::get('/fixed-assets/{asset}', [FixedAssetController::class, 'show'])->name('assets.show');
        Route::put('/fixed-assets/{asset}', [FixedAssetController::class, 'update'])->name('assets.update');
        Route::delete('/fixed-assets/{asset}', [FixedAssetController::class, 'destroy'])->name('assets.destroy');
        /* The doors on one asset: hand it over, take it back, log a repair,
           verify it in person, dispose of it. Each one is a sentence in the
           register's history, so each one has its own route and its own writer. */
        Route::post('/fixed-assets/{asset}/allocate', [FixedAssetController::class, 'allocate'])->name('assets.allocate');
        Route::patch('/fixed-assets/{asset}/take-back', [FixedAssetController::class, 'takeBack'])->name('assets.takeBack');
        Route::post('/fixed-assets/{asset}/maintenance', [FixedAssetController::class, 'maintain'])->name('assets.maintain');
        Route::patch('/fixed-assets/{asset}/verify', [FixedAssetController::class, 'verify'])->name('assets.verify');
        Route::patch('/fixed-assets/{asset}/dispose', [FixedAssetController::class, 'dispose'])->name('assets.dispose');

        /* --------------------------------------------------------- recurring
           Standing payments: salary, rent, the monthly suppliers. A rule is
           written as a draft, approved once, and from then on it asks for each
           date on the day the money is due — see
           `docs/recurring-cashflow.md`.

           These routes are declared **before** `Route::resource('cashflows')`
           below, and that is load-bearing: `/cashflows/recurring` would
           otherwise be read as `/cashflows/{cashflow}` with an id of
           "recurring", and the module's front page would be a 404. The documents
           and statements blocks above sit here for the same reason.
        */
        Route::prefix('cashflows/recurring')->name('cashflows.recurring.')->group(function () {
            Route::get('/', [CashflowRecurrenceController::class, 'index'])->name('index');
            Route::post('/', [CashflowRecurrenceController::class, 'store'])->name('store');
            Route::get('/{recurrence}', [CashflowRecurrenceController::class, 'show'])->whereNumber('recurrence')->name('show');
            Route::put('/{recurrence}', [CashflowRecurrenceController::class, 'update'])->whereNumber('recurrence')->name('update');
            Route::delete('/{recurrence}', [CashflowRecurrenceController::class, 'destroy'])->whereNumber('recurrence')->name('destroy');

            /* The rule's own doors: ask, decide, hold, stop. */
            Route::patch('/{recurrence}/request', [CashflowRecurrenceController::class, 'requestApproval'])->whereNumber('recurrence')->name('request');
            Route::patch('/{recurrence}/approve', [CashflowRecurrenceController::class, 'approve'])->whereNumber('recurrence')->name('approve');
            Route::patch('/{recurrence}/send-back', [CashflowRecurrenceController::class, 'sendBack'])->whereNumber('recurrence')->name('sendBack');
            Route::patch('/{recurrence}/pause', [CashflowRecurrenceController::class, 'pause'])->whereNumber('recurrence')->name('pause');
            Route::patch('/{recurrence}/resume', [CashflowRecurrenceController::class, 'resume'])->whereNumber('recurrence')->name('resume');
            Route::patch('/{recurrence}/end', [CashflowRecurrenceController::class, 'end'])->whereNumber('recurrence')->name('end');

            /* And the two answers to one date's ask. */
            Route::patch('/{recurrence}/occurrences/{occurrence}/approve', [CashflowRecurrenceController::class, 'approveOccurrence'])
                ->whereNumber('recurrence')->whereNumber('occurrence')->name('occurrences.approve');
            Route::patch('/{recurrence}/occurrences/{occurrence}/skip', [CashflowRecurrenceController::class, 'skipOccurrence'])
                ->whereNumber('recurrence')->whereNumber('occurrence')->name('occurrences.skip');
        });

        Route::post('/cashflows/saved-views', [CashflowController::class, 'storeSavedView'])->name('cashflows.saved-views.store');
        Route::delete('/cashflows/saved-views/{savedView}', [CashflowController::class, 'destroySavedView'])->name('cashflows.saved-views.destroy');
        Route::post('/cashflows/quick', [CashflowController::class, 'quickStore'])->name('cashflows.quickStore');
        Route::post('/cashflows/accounts', [CashflowController::class, 'storeAccount'])->name('cashflows.accounts.store');
        Route::post('/cashflows/categories', [CashflowController::class, 'storeCategory'])->name('cashflows.categories.store');
        Route::post('/cashflows/bulk', [CashflowController::class, 'bulk'])->name('cashflows.bulk');
        Route::get('/cashflows/{cashflow}/voucher', [CashflowController::class, 'voucher'])->name('cashflows.voucher');
        Route::get('/cashflows/{cashflow}/expense-statement', [CashflowController::class, 'expenseStatement'])->name('cashflows.expenseStatement');
        Route::post('/cashflows/{cashflow}/duplicate', [CashflowController::class, 'duplicate'])->name('cashflows.duplicate');
        Route::patch('/cashflows/{cashflow}/status', [CashflowController::class, 'updateStatus'])->name('cashflows.status');
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

    /* Notes — the sticky notes off your desk, and the one page in this file
       that belongs to the *login* rather than to a role. It sits outside the
       `office` group, because an employee keeps notes too and the middleware
       would turn them around at the door; and outside `/my`, because `/my` is
       one person's record and this is whoever is signed in. So it is the only
       screen both halves of the application may open — which is exactly why
       the note routes take a note id and never a user id: the person is the
       session, and `NoteController::mine()` looks the row up inside their own
       notes. */
    Route::prefix('notes')->name('notes.')->group(function () {
        Route::get('/', [NoteController::class, 'index'])->name('index');
        Route::post('/', [NoteController::class, 'store'])->name('store');
        Route::get('/{note}/edit', [NoteController::class, 'edit'])->name('edit');
        Route::put('/{note}', [NoteController::class, 'update'])->name('update');
        /* The two toggles are one URL each: the button's label and the note's
           own state decide the direction, so there is nothing to keep in step. */
        Route::patch('/{note}/pin', [NoteController::class, 'pin'])->name('pin');
        Route::patch('/{note}/archive', [NoteController::class, 'archive'])->name('archive');
        Route::delete('/{note}', [NoteController::class, 'destroy'])->name('destroy');
    });

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
        /* The same ask, answered from inside their own workspace: the office
           issues it either way, and both doors write the same row. */
        Route::get('/feedback', [ClientPortalFeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/feedback/{feedbackRequest}', [ClientPortalFeedbackController::class, 'show'])->name('feedback.show');
        Route::post('/feedback/{feedbackRequest}', [ClientPortalFeedbackController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('feedback.store');
        Route::get('/kyc', [ClientPortalKycController::class, 'show'])->name('kyc.show');

        Route::get('/notifications', [ClientPortalNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [ClientPortalNotificationController::class, 'markRead'])->name('notifications.read');
        Route::patch('/notifications/read-all', [ClientPortalNotificationController::class, 'markAllRead'])->name('notifications.readAll');
    });
});
