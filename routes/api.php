<?php

use Illuminate\Support\Facades\Route;
use KraftInk\Plugins\Vote\Http\Controllers\CallbackController;
use KraftInk\Plugins\Vote\Http\Controllers\VoteController;

Route::match(['GET', 'POST'], 'vote/provider-callback/{id}/{secret}', [CallbackController::class, 'handle'])
    ->whereNumber('id')->where('secret', '[a-f0-9]{64}')->middleware('throttle:120,1')->name('plugins.vote.webhooks.providers.handle');

Route::prefix('vote/callback')
    ->name('plugins.vote.webhooks.')
    ->group(function () {
        Route::post('/{id}', [VoteController::class, 'callback'])
            ->whereNumber('id')
            ->middleware('throttle:120,1')
            ->name('sites.handle');
    });
