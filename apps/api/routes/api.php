<?php

use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminCredentialController;
use App\Http\Controllers\Admin\AdminEmailController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPricingController;
use App\Http\Controllers\Admin\AdminProviderController;
use App\Http\Controllers\Admin\AdminSystemController;
use App\Http\Controllers\Admin\AdminTeamController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ClipController;
use App\Http\Controllers\Api\EditController;
use App\Http\Controllers\Api\FilmController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\RateCardController;
use App\Http\Controllers\Api\RegistryController;
use App\Http\Controllers\Api\RemixController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\WorkflowTemplateController;
use App\Styles\StylePresets;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Public pricing for the marketing page. No sign-in and no margin.
    Route::get('/pricing', [PricingController::class, 'index']);

    // Payment provider webhooks. Authenticated by each provider's signature, not by a user token.
    Route::post('/webhooks/flutterwave', [PaymentWebhookController::class, 'flutterwave'])->middleware('throttle:300,1');
    Route::post('/webhooks/paddle', [PaymentWebhookController::class, 'paddle'])->middleware('throttle:300,1');

    // Super admin sign-in for /securegate. Throttled hard; every failure gets the same answer.
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/user', [AuthController::class, 'me']);

        Route::get('/billing', [BillingController::class, 'show']);

        Route::get('/clip-sources', [ClipController::class, 'index']);
        Route::post('/clip-sources', [ClipController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/clip-sources/{source}', [ClipController::class, 'show']);
        Route::post('/clip-sources/{source}/runs', [ClipController::class, 'startRun'])->middleware('throttle:30,1');
        Route::post('/clip-sources/{source}/transcript', [ClipController::class, 'importTranscript'])->middleware('throttle:30,1');
        Route::get('/clip-runs/{run}', [ClipController::class, 'showRun']);

        Route::post('/clip-sources/{source}/remixes', [RemixController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/remixes/{remix}', [RemixController::class, 'show']);

        Route::get('/usage/{type}/{id}', [UsageController::class, 'show']);

        // Editing: split, captions, trims.
        Route::post('/clip-sources/{source}/split', [EditController::class, 'split'])->middleware('throttle:20,1');
        Route::get('/clip-sources/{source}/captions', [EditController::class, 'sourceCaptions']);
        Route::get('/clips/{clip}/captions', [EditController::class, 'clipCaptions']);
        Route::post('/clips/{clip}/trim', [EditController::class, 'trimClip'])->middleware('throttle:30,1');
        Route::post('/generations/{generation}/trim', [EditController::class, 'trimGeneration'])->middleware('throttle:30,1');

        // Full videos: start one, then follow its progress.
        Route::get('/productions', [ProductionController::class, 'index']);
        Route::post('/productions', [ProductionController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/productions/{production}', [ProductionController::class, 'show']);
        Route::delete('/productions/{production}', [ProductionController::class, 'destroy']);

        // The Director Lab: long films in chapters, planned by a director, with a board of references.
        Route::get('/films', [FilmController::class, 'index']);
        Route::post('/films', [FilmController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/films/{film}', [FilmController::class, 'show']);
        Route::post('/films/{film}/cancel', [FilmController::class, 'cancel']);

        // A reference image for a run. The provider fetches it, so it is stored where it can be reached.
        Route::post('/uploads/reference', [ReferenceController::class, 'store'])->middleware('throttle:30,1');

        // The skills library. Each skill tells the model how to work when it is selected.
        Route::get('/skills', [SkillController::class, 'index']);
        Route::get('/model-options', [ModelController::class, 'index']);

        // The menus for environment, character and look.
        Route::get('/styles', fn () => response()->json(StylePresets::catalog()));

        // What an action will cost, before the user presses it.
        Route::post('/quote', [QuoteController::class, 'quote'])->middleware('throttle:120,1');
        Route::post('/billing/plans', [BillingController::class, 'checkoutPlan']);
        Route::post('/billing/packs', [BillingController::class, 'checkoutPack']);
        Route::post('/billing/cancel', [BillingController::class, 'cancel']);
        Route::post('/billing/country', [BillingController::class, 'country']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::apiResource('projects', ProjectController::class);

        Route::get('/activity', [ActivityController::class, 'index']);

        Route::get('/tools', [ToolController::class, 'index']);
        Route::post('/tools/{tool}/run', [ToolController::class, 'run'])->middleware('throttle:60,1');
        Route::get('/generations', [GenerationController::class, 'index']);
        Route::get('/generations/{generation}', [GenerationController::class, 'show']);
        Route::delete('/generations/{generation}', [GenerationController::class, 'destroy']);

        Route::get('/projects/{project}/workflow', [WorkflowController::class, 'show']);
        Route::post('/projects/{project}/start', [WorkflowController::class, 'start']);

        Route::post('/workflows/{workflow}/pause', [WorkflowController::class, 'pause']);
        Route::post('/workflows/{workflow}/resume', [WorkflowController::class, 'resume']);
        Route::post('/workflows/{workflow}/cancel', [WorkflowController::class, 'cancel']);
        Route::patch('/workflows/{workflow}/tasks/{task}', [WorkflowController::class, 'updateTask']);

        Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve']);
        Route::post('/approvals/{approval}/revise', [ApprovalController::class, 'revise']);
        Route::post('/approvals/{approval}/regenerate', [ApprovalController::class, 'regenerate']);
        Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject']);

        Route::get('/rate-card', [RateCardController::class, 'show']);

        Route::get('/providers', [RegistryController::class, 'providers']);
        Route::get('/models', [RegistryController::class, 'models']);
        Route::get('/workflow-templates', [WorkflowTemplateController::class, 'index']);

        // Owner-only. Non-admins get 404 from the super_admin middleware.
        Route::middleware('super_admin')->prefix('admin')->group(function () {
            Route::get('/overview', [AdminOverviewController::class, 'index']);

            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{user}', [AdminUserController::class, 'show'])->withTrashed();
            Route::patch('/users/{user}', [AdminUserController::class, 'update'])->withTrashed();
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
            Route::post('/users/{user}/restore', [AdminUserController::class, 'restore']);
            Route::post('/users/{user}/credits', [AdminUserController::class, 'credit']);

            Route::get('/providers', [AdminProviderController::class, 'index']);
            Route::patch('/providers/{provider}', [AdminProviderController::class, 'update']);
            Route::patch('/providers/{provider}/models/{model}', [AdminProviderController::class, 'updateModel']);
            Route::post('/providers/{provider}/sync', [AdminProviderController::class, 'sync']);

            Route::get('/pricing', [AdminPricingController::class, 'show']);
            Route::get('/rate-card', [RateCardController::class, 'owner']);
            Route::patch('/pricing/margin', [AdminPricingController::class, 'updateMargin']);
            Route::patch('/pricing/promo', [AdminPricingController::class, 'updatePromo']);
            Route::patch('/pricing/prices/{price}', [AdminPricingController::class, 'updatePrice']);
            Route::post('/pricing/sync', [AdminPricingController::class, 'sync']);

            Route::get('/email/templates', [AdminEmailController::class, 'templates']);
            Route::post('/email/templates', [AdminEmailController::class, 'storeTemplate']);
            Route::patch('/email/templates/{template}', [AdminEmailController::class, 'updateTemplate']);
            Route::delete('/email/templates/{template}', [AdminEmailController::class, 'destroyTemplate']);
            Route::post('/email/templates/{template}/test', [AdminEmailController::class, 'test']);
            Route::get('/email/logs', [AdminEmailController::class, 'logs']);

            Route::get('/credentials', [AdminCredentialController::class, 'index']);
            Route::put('/credentials/{name}', [AdminCredentialController::class, 'update'])->where('name', '[A-Z_]+');
            Route::delete('/credentials/{name}', [AdminCredentialController::class, 'destroy'])->where('name', '[A-Z_]+');
            Route::post('/credentials/{name}/test', [AdminCredentialController::class, 'test'])->where('name', '[A-Z_]+');

            Route::get('/system', [AdminSystemController::class, 'show']);
            Route::patch('/system', [AdminSystemController::class, 'update']);
            Route::post('/system/test-email', [AdminSystemController::class, 'testEmail']);

            Route::get('/payments', [AdminPaymentController::class, 'show']);
            Route::get('/usage/{type}/{id}', [UsageController::class, 'owner']);
            Route::post('/billing/plans/{plan}/flutterwave', [AdminPaymentController::class, 'createFlutterwavePlans']);

            Route::get('/billing', [AdminBillingController::class, 'show']);
            Route::post('/billing/plans', [AdminBillingController::class, 'storePlan']);
            Route::patch('/billing/plans/{plan}', [AdminBillingController::class, 'updatePlan']);
            Route::delete('/billing/plans/{plan}', [AdminBillingController::class, 'destroyPlan']);
            Route::post('/billing/packs', [AdminBillingController::class, 'storePack']);
            Route::patch('/billing/packs/{pack}', [AdminBillingController::class, 'updatePack']);
            Route::delete('/billing/packs/{pack}', [AdminBillingController::class, 'destroyPack']);
            Route::get('/billing/purchases', [AdminBillingController::class, 'purchases']);
            Route::post('/billing/purchases/{purchase}/paid', [AdminBillingController::class, 'markPaid']);
            Route::post('/billing/purchases/{purchase}/failed', [AdminBillingController::class, 'markFailed']);
            Route::post('/billing/users/{user}/plan', [AdminBillingController::class, 'grantPlan']);
            Route::get('/billing/subscriptions', [AdminBillingController::class, 'subscriptions']);
            Route::post('/billing/subscriptions/{subscription}/cancel', [AdminBillingController::class, 'cancelSubscription']);

            Route::get('/team', [AdminTeamController::class, 'index']);
            Route::post('/team', [AdminTeamController::class, 'store']);
            Route::delete('/team/{user}', [AdminTeamController::class, 'destroy']);

            Route::get('/audit', [AdminAuditController::class, 'index']);
        });
    });
});
