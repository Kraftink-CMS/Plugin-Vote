<?php

use Illuminate\Support\Facades\Route;
use KraftInk\Plugins\Vote\Http\Controllers\Admin\ReviewController;
use KraftInk\Plugins\Vote\Http\Controllers\Admin\RewardController;
use KraftInk\Plugins\Vote\Http\Controllers\Admin\SiteController;
use KraftInk\Plugins\Vote\Http\Controllers\DeliveryController;
use KraftInk\Plugins\Vote\Http\Controllers\CallbackController;

Route::prefix('admin/plugins/vote')
    ->name('plugins.vote.admin.')
    ->middleware(['web', 'auth', 'permission:admin.access', 'permission:vote.manage'])
    ->group(function () {
        Route::post('/sites/{id}/callback-url', [CallbackController::class, 'showUrl'])
            ->whereNumber('id')
            ->middleware('throttle:sensitive')
            ->name('sites.callback-url');
        Route::get('/sites/{id}/callback-url', [CallbackController::class, 'revealedUrl'])
            ->whereNumber('id')
            ->name('sites.callback-url.result');
        Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
        Route::get('/sites/create', [SiteController::class, 'edit'])->name('sites.create');
        Route::get('/sites/{id}/edit', [SiteController::class, 'edit'])
            ->whereNumber('id')
            ->name('sites.edit');
        Route::post('/sites', [SiteController::class, 'save'])
            ->middleware('throttle:sensitive')
            ->name('sites.store');
        Route::put('/sites/{id}', [SiteController::class, 'save'])
            ->whereNumber('id')
            ->middleware('throttle:sensitive')
            ->name('sites.update');
        Route::delete('/sites/{id}', [SiteController::class, 'destroy'])
            ->whereNumber('id')
            ->name('sites.destroy');
        Route::get('/rewards', [RewardController::class, 'index'])->name('rewards.index');
        Route::get('/rewards/create', [RewardController::class, 'edit'])->name('rewards.create');
        Route::get('/rewards/{id}/edit', [RewardController::class, 'edit'])
            ->whereNumber('id')
            ->name('rewards.edit');
        Route::post('/rewards', [RewardController::class, 'save'])
            ->middleware('throttle:sensitive')
            ->name('rewards.store');
        Route::put('/rewards/{id}', [RewardController::class, 'save'])
            ->whereNumber('id')
            ->middleware('throttle:sensitive')
            ->name('rewards.update');
        Route::delete('/rewards/{id}', [RewardController::class, 'destroy'])
            ->whereNumber('id')
            ->name('rewards.destroy');
        Route::post('/claims/{id}/retry', [RewardController::class, 'retry'])
            ->whereNumber('id')
            ->name('claims.retry');
        Route::get('/reviews', [ReviewController::class, 'index'])
            ->middleware('permission:vote.review')
            ->name('reviews.index');
        Route::post('/reviews/{id}', [ReviewController::class, 'confirm'])
            ->whereNumber('id')
            ->middleware('permission:vote.review')
            ->name('reviews.confirm');

        Route::prefix('deliveries')
            ->name('deliveries.')
            ->group(function () {
                Route::post('/{id}/retry', [DeliveryController::class, 'retry'])
                    ->whereUuid('id')
                    ->middleware('throttle:sensitive')
                    ->name('retry');
                Route::post('/{id}/cancel', [DeliveryController::class, 'cancel'])
                    ->whereUuid('id')
                    ->middleware('throttle:sensitive')
                    ->name('cancel');
            });
    });
