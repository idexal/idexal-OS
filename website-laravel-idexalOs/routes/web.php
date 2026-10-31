<?php

use App\Http\Controllers\Admin\AdminAgentPermissionController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminCatalogController;
use App\Http\Controllers\Admin\AdminComboController;
use App\Http\Controllers\Admin\AdminConnectorController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminExtensionController;
use App\Http\Controllers\Admin\AdminFeatureController;
use App\Http\Controllers\Admin\AdminGatewayController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminOrganizationController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPaymentGatewayController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminProviderController;
use App\Http\Controllers\Admin\AdminProviderKeyManagementController;
use App\Http\Controllers\Admin\AdminProxyProviderController;
use App\Http\Controllers\Admin\AdminReadinessController;
use App\Http\Controllers\Admin\AdminReferralController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminSocialLoginController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUsageRequestController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Dashboard\ApiKeyController;
use App\Http\Controllers\Dashboard\BillingController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\DesktopController;
use App\Http\Controllers\Dashboard\DeveloperController;
use App\Http\Controllers\Dashboard\InvoiceController;
use App\Http\Controllers\Dashboard\ModelController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\OrganizationSwitchController;
use App\Http\Controllers\Dashboard\PayPalController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\ReferralController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\SupportController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\Dashboard\UsageController;
use App\Http\Controllers\Desktop\DesktopAuthorizationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\ChangelogController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DocsController;
use App\Http\Controllers\Public\DownloadController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\IntegrationsController;
use App\Http\Controllers\Public\InvitationController;
use App\Http\Controllers\Public\LegalController;
use App\Http\Controllers\Public\PricingController;
use App\Http\Controllers\Public\ProductController;
use App\Http\Controllers\Public\ProvidersController;
use App\Http\Controllers\Public\ResourcesController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\UseCaseController;
use App\Http\Controllers\ReferralLinkController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
Route::get('/products', [ProductController::class, 'index'])->name('products');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/use-cases', [UseCaseController::class, 'index'])->name('use-cases');
Route::get('/use-case/{slug}', [UseCaseController::class, 'show'])->name('use-cases.show');
Route::get('/resources', [ResourcesController::class, 'index'])->name('resources');
Route::view('/enterprise', 'public.enterprise')->name('enterprise');
Route::get('/docs', [DocsController::class, 'index'])->name('docs');
Route::get('/docs/{path}', [DocsController::class, 'show'])->where('path', '.*')->name('docs.show');
Route::get('/api', [DocsController::class, 'api'])->name('api');
Route::view('/developers', 'public.developers')->name('developers');
// The store has one home. /marketplace is the name people try; send it to the
// catalogue instead of maintaining a second, duplicate listing.
Route::redirect('/marketplace', '/integrations', 301)->name('marketplace');
Route::get('/providers', [ProvidersController::class, 'index'])->name('providers');
Route::get('/integrations', [IntegrationsController::class, 'index'])->name('integrations');
Route::get('/integrations/{type}/{slug}', [IntegrationsController::class, 'show'])
    ->whereIn('type', ['skill', 'plugin', 'connector'])
    ->name('integrations.show');
Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');
Route::get('/download', [DownloadController::class, 'index'])->name('download');
Route::view('/security', 'public.security')->name('security');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('invitations.accept');

Route::get('/r/{code}', [ReferralLinkController::class, 'track'])
    ->middleware('throttle:60,1')
    ->name('referrals.track');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->middleware('throttle:30,1')
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->middleware('throttle:30,1')
        ->name('social.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/desktop/authorize', [DesktopAuthorizationController::class, 'create'])->name('desktop.authorize');
    Route::post('/desktop/authorize', [DesktopAuthorizationController::class, 'store'])->name('desktop.authorize.store');
    Route::get('/desktop/complete', [DesktopAuthorizationController::class, 'complete'])->name('desktop.complete');
});

Route::prefix('dashboard')
    ->middleware(['auth', 'verified.required', 'org'])
    ->name('dashboard.')
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::post('/organization/switch', [OrganizationSwitchController::class, 'update'])->name('organization.switch');
        Route::post('/organization', [OrganizationSwitchController::class, 'store'])->name('organization.store');

        Route::get('/usage', [UsageController::class, 'index'])->name('usage');
        Route::get('/usage/export', [UsageController::class, 'export'])->name('usage.export');

        Route::get('/models', [ModelController::class, 'index'])->name('models');
        // Addresses the desktop opens against a Den. They must resolve on the
        // self-hosted platform too, or the desktop's own buttons land on a 404.
        Route::get('/custom-llm-providers', [ModelController::class, 'index'])->name('models.custom-llm-providers');
        Route::get('/library', fn () => redirect()->route('dashboard.models'));
        Route::get('/ai-gateway', fn () => redirect()->route('dashboard.models'));

        Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

        Route::get('/billing', [BillingController::class, 'index'])->name('billing');
        Route::post('/billing/plan', [BillingController::class, 'changePlan'])->name('billing.plan');
        Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::post('/billing/top-up', [BillingController::class, 'topUp'])->name('billing.top-up');
        Route::get('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
        Route::get('/billing/paypal/return', [PayPalController::class, 'return'])->name('billing.paypal.return');
        Route::post('/billing/payment-methods', [BillingController::class, 'storePaymentMethod'])->name('billing.payment-methods.store');
        Route::patch('/billing/payment-methods/{paymentMethod}/default', [BillingController::class, 'makeDefault'])->name('billing.payment-methods.default');
        Route::delete('/billing/payment-methods/{paymentMethod}', [BillingController::class, 'destroyPaymentMethod'])->name('billing.payment-methods.destroy');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('/invoices/{invoice}/paypal', [PayPalController::class, 'start'])->name('invoices.paypal');
        Route::post('/invoices/{invoice}/bank-transfer', [InvoiceController::class, 'bankTransfer'])->name('invoices.bank-transfer');

        Route::get('/team', [TeamController::class, 'index'])->name('team');
        Route::post('/team/invite', [TeamController::class, 'invite'])->name('team.invite');
        Route::patch('/team/members/{membership}', [TeamController::class, 'update'])->name('team.members.update');
        Route::delete('/team/members/{membership}', [TeamController::class, 'destroy'])->name('team.members.destroy');
        Route::delete('/team/invitations/{invitation}', [TeamController::class, 'cancelInvitation'])->name('team.invitations.destroy');

        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals');

        Route::get('/support', [SupportController::class, 'index'])->name('support.index');
        Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');
        Route::get('/support/{supportTicket}', [SupportController::class, 'show'])->name('support.show');
        Route::post('/support/{supportTicket}/reply', [SupportController::class, 'reply'])->name('support.reply');

        Route::get('/developer', [DeveloperController::class, 'index'])->name('developer');
        Route::post('/developer/webhooks', [DeveloperController::class, 'storeWebhook'])->name('developer.webhooks.store');
        Route::patch('/developer/webhooks/{webhookEndpoint}', [DeveloperController::class, 'updateWebhook'])->name('developer.webhooks.update');
        Route::post('/developer/webhooks/{webhookEndpoint}/rotate', [DeveloperController::class, 'rotateWebhook'])->name('developer.webhooks.rotate');
        Route::delete('/developer/webhooks/{webhookEndpoint}', [DeveloperController::class, 'destroyWebhook'])->name('developer.webhooks.destroy');
        Route::get('/developer/webhooks/{webhookEndpoint}/deliveries', [DeveloperController::class, 'deliveries'])->name('developer.webhooks.deliveries');

        Route::get('/desktop', [DesktopController::class, 'index'])->name('desktop');
        Route::delete('/desktop/{desktopSession}', [DesktopController::class, 'destroy'])->name('desktop.destroy');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    });

Route::prefix('admin')
    ->middleware(['auth', 'verified.required', 'admin'])
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('index');
        Route::get('/readiness', [AdminReadinessController::class, 'index'])->name('readiness');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/export', [AdminUserController::class, 'export'])->name('users.export');
        Route::post('/users/bulk', [AdminUserController::class, 'bulk'])->name('users.bulk');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::post('/users/{user}/verify', [AdminUserController::class, 'verifyEmail'])->name('users.verify');
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'sendResetLink'])->name('users.reset');
        Route::post('/users/{user}/revoke-keys', [AdminUserController::class, 'revokeApiKeys'])->name('users.revoke-keys');
        Route::post('/users/{user}/revoke-sessions', [AdminUserController::class, 'revokeSessions'])->name('users.revoke-sessions');

        Route::get('/organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/{organization}', [AdminOrganizationController::class, 'show'])->name('organizations.show');
        Route::patch('/organizations/{organization}/status', [AdminOrganizationController::class, 'updateStatus'])->name('organizations.status');
        Route::patch('/organizations/{organization}/byok', [AdminOrganizationController::class, 'updateByok'])->name('organizations.byok');
        Route::post('/organizations/{organization}/credit', [AdminOrganizationController::class, 'addCredit'])->name('organizations.credit');

        Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [AdminPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [AdminPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('plans.edit');
        Route::patch('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');

        Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [AdminInvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [AdminInvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/download', [AdminInvoiceController::class, 'download'])->name('invoices.download');
        Route::post('/invoices/{invoice}/paid', [AdminInvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::post('/invoices/{invoice}/void', [AdminInvoiceController::class, 'void'])->name('invoices.void');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');

        Route::get('/payment-gateways', [AdminPaymentGatewayController::class, 'edit'])->name('payment-gateways');
        Route::patch('/payment-gateways', [AdminPaymentGatewayController::class, 'update'])->name('payment-gateways.update');

        Route::get('/social-login', [AdminSocialLoginController::class, 'edit'])->name('social-login');
        Route::patch('/social-login', [AdminSocialLoginController::class, 'update'])->name('social-login.update');

        Route::get('/referrals', [AdminReferralController::class, 'index'])->name('referrals');
        Route::patch('/referrals', [AdminReferralController::class, 'update'])->name('referrals.update');
        Route::patch('/referrals/{referral}', [AdminReferralController::class, 'updateReferral'])->name('referrals.update-referral');

        Route::get('/catalog', [AdminCatalogController::class, 'index'])->name('catalog.index');

        Route::get('/combos', [AdminComboController::class, 'index'])->name('combos.index');
        Route::post('/combos/sync', [AdminComboController::class, 'sync'])->name('combos.sync');
        Route::post('/combos', [AdminComboController::class, 'store'])->name('combos.store');
        Route::delete('/combos/{combo}', [AdminComboController::class, 'destroy'])->name('combos.destroy');
        Route::get('/combos/suggest', [AdminComboController::class, 'suggest'])->name('combos.suggest');
        Route::post('/combos/create-advanced', [AdminComboController::class, 'storeAdvanced'])->name('combos.store.advanced');
        Route::patch('/combos/{combo}', [AdminComboController::class, 'update'])->name('combos.update');

        Route::get('/providers', [AdminProviderController::class, 'index'])->name('providers.index');
        Route::get('/providers/create', [AdminProviderController::class, 'create'])->name('providers.create');
        Route::post('/providers', [AdminProviderController::class, 'store'])->name('providers.store');
        Route::get('/providers/{aiProvider}/edit', [AdminProviderController::class, 'edit'])->name('providers.edit');
        Route::patch('/providers/{aiProvider}', [AdminProviderController::class, 'update'])->name('providers.update');
        Route::delete('/providers/{aiProvider}', [AdminProviderController::class, 'destroy'])->name('providers.destroy');
        Route::post('/providers/{aiProvider}/test', [AdminProviderController::class, 'test'])->name('providers.test');
        Route::post('/providers/health-check-all', [AdminProviderController::class, 'checkAllHealth'])->name('providers.health-check-all');
        Route::post('/providers/byok', [AdminProviderController::class, 'updateByok'])->name('providers.byok');
        Route::post('/providers/assignment', [AdminProviderController::class, 'updateAssignment'])->name('providers.assignment');
        Route::post('/providers/{aiProvider}/toggle', [AdminProviderController::class, 'toggle'])->name('providers.toggle');
        Route::post('/providers/{aiProvider}/health-check', [AdminProviderController::class, 'checkHealth'])->name('providers.health-check');
        Route::get('/providers/{aiProvider}/models/fetch', [AdminProviderController::class, 'fetchModels'])->name('providers.models.fetch');
        Route::post('/providers/{aiProvider}/models/import', [AdminProviderController::class, 'importModels'])->name('providers.models.import');
        Route::post('/providers/{aiProvider}/models', [AdminProviderController::class, 'storeModel'])->name('providers.models.store');
        Route::patch('/providers/{aiProvider}/models/{aiModel}', [AdminProviderController::class, 'updateModel'])->name('providers.models.update');
        Route::delete('/providers/{aiProvider}/models/{aiModel}', [AdminProviderController::class, 'destroyModel'])->name('providers.models.destroy');
        Route::post('/providers/{aiProvider}/backup-chain', [AdminProviderController::class, 'updateBackupChain'])->name('providers.backup-chain');

        // Key Management Routes
        Route::post('/providers/rotate-all-keys', [AdminProviderKeyManagementController::class, 'bulkRotateAll'])->name('provider-key-management.rotate-all');
        Route::get('/providers/{aiProvider}/keys', [AdminProviderKeyManagementController::class, 'editKeys'])->name('provider-key-management.edit-keys');
        Route::post('/providers/{aiProvider}/keys', [AdminProviderKeyManagementController::class, 'storeKey'])->name('provider-key-management.store-key');
        Route::post('/providers/{aiProvider}/rotate-keys', [AdminProviderKeyManagementController::class, 'rotateKey'])->name('provider-key-management.rotate-key');
        Route::delete('/providers/{aiProvider}/keys/{keyId}', [AdminProviderKeyManagementController::class, 'deactivateKey'])->name('provider-key-management.deactivate-key');
        Route::post('/providers/{aiProvider}/test-key', [AdminProviderKeyManagementController::class, 'testKey'])->name('provider-key-management.test-key');

        Route::get('/proxy-providers', [AdminProxyProviderController::class, 'index'])->name('proxy-providers.index');
        Route::get('/proxy-providers/create', [AdminProxyProviderController::class, 'create'])->name('proxy-providers.create');
        Route::post('/proxy-providers', [AdminProxyProviderController::class, 'store'])->name('proxy-providers.store');
        Route::get('/proxy-providers/{proxyProvider}/edit', [AdminProxyProviderController::class, 'edit'])->name('proxy-providers.edit');
        Route::patch('/proxy-providers/{proxyProvider}', [AdminProxyProviderController::class, 'update'])->name('proxy-providers.update');
        Route::delete('/proxy-providers/{proxyProvider}', [AdminProxyProviderController::class, 'destroy'])->name('proxy-providers.destroy');

        Route::get('/gateway', [AdminGatewayController::class, 'index'])->name('gateway');
        Route::post('/gateway/usage-requests/{usageRequest}/approve', [AdminUsageRequestController::class, 'approve'])->name('gateway.usage-requests.approve');
        Route::post('/gateway/usage-requests/{usageRequest}/deny', [AdminUsageRequestController::class, 'deny'])->name('gateway.usage-requests.deny');

        Route::get('/features', [AdminFeatureController::class, 'edit'])->name('features');
        Route::patch('/features', [AdminFeatureController::class, 'update'])->name('features.update');

        Route::get('/agent-permissions', [AdminAgentPermissionController::class, 'edit'])->name('agent-permissions');
        Route::patch('/agent-permissions', [AdminAgentPermissionController::class, 'update'])->name('agent-permissions.update');

        Route::get('/connectors', [AdminConnectorController::class, 'edit'])->name('connectors');
        Route::patch('/connectors', [AdminConnectorController::class, 'update'])->name('connectors.update');
        Route::patch('/connectors/requests', [AdminConnectorController::class, 'dismissRequest'])->name('connectors.requests.dismiss');

        Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings');
        Route::patch('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

        Route::get('/audit', [AdminAuditLogController::class, 'index'])->name('audit');

        Route::get('/support', [AdminSupportController::class, 'index'])->name('support.index');
        Route::patch('/support', [AdminSupportController::class, 'update'])->name('support.update');
        Route::get('/support/{supportTicket}', [AdminSupportController::class, 'show'])->name('support.show');
        Route::patch('/support/{supportTicket}', [AdminSupportController::class, 'updateTicket'])->name('support.ticket.update');
        Route::post('/support/{supportTicket}/reply', [AdminSupportController::class, 'reply'])->name('support.reply');
        Route::post('/support/{supportTicket}/ai-draft', [AdminSupportController::class, 'aiDraft'])->name('support.ai-draft');
        Route::post('/support/{supportTicket}/replies/{supportTicketReply}/send', [AdminSupportController::class, 'sendReply'])->name('support.replies.send');

        Route::get('/extensions', [AdminExtensionController::class, 'index'])->name('extensions.index');
        Route::get('/extensions/search', [AdminExtensionController::class, 'search'])->name('extensions.search');
        Route::post('/extensions/install', [AdminExtensionController::class, 'install'])->name('extensions.install');
        Route::post('/extensions/{installationId}/uninstall', [AdminExtensionController::class, 'uninstall'])->name('extensions.uninstall');
        Route::post('/extensions/refresh', [AdminExtensionController::class, 'refresh'])->name('extensions.refresh');
    });

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
Route::post('/webhooks/paypal', PayPalWebhookController::class)->name('webhooks.paypal');
