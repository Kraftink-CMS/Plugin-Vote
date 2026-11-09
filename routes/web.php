<?php

use App\Http\Middleware\NoIndex;
use Illuminate\Support\Facades\Route;
use KraftInk\Plugins\Vote\Http\Controllers\GuestController;
use KraftInk\Plugins\Vote\Http\Controllers\VoteController;

Route::prefix('vote')
    ->name('plugins.vote.public.')
    ->middleware('web')
    ->group(function () {
        Route::middleware('permission:vote.view')->group(function () {
            Route::post('/guests/{id}/start', [GuestController::class, 'start'])
                ->whereNumber('id')
                ->middleware('throttle:10,1')
                ->name('guests.start');
            Route::get('/guests/challenges/{id}', [GuestController::class, 'show'])
                ->whereNumber('id')
                ->middleware(NoIndex::class)
                ->name('guests.show');
            Route::post('/guests/challenges/{id}/verify', [GuestController::class, 'verify'])
                ->whereNumber('id')
                ->middleware('throttle:10,1')
                ->name('guests.verify');
            Route::delete('/guests/session', [GuestController::class, 'forget'])->name('guests.forget');
            Route::get('/', [VoteController::class, 'index'])
                ->middleware('capability:extension,vote')
                ->name('votes.index');
            Route::post('/{id}/start', [VoteController::class, 'start'])
                ->whereNumber('id')
                ->middleware(['auth', 'throttle:20,1'])
                ->name('votes.start');
            Route::get('/challenges/{id}', [VoteController::class, 'challenge'])
                ->whereNumber('id')
                ->middleware('auth')
                ->name('challenges.show');
            Route::post('/challenges/{id}/verify', [VoteController::class, 'verify'])
                ->whereNumber('id')
                ->middleware(['auth', 'throttle:10,1'])
                ->name('challenges.verify');
        });
    });
