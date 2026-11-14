<?php

declare(strict_types=1);

use Filagora\Newsletters\Http\Controllers\LinkClickController;
use Filagora\Newsletters\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubscriptionController::class, 'show'])->name('newsletter.show');
Route::post('/', [SubscriptionController::class, 'store'])->middleware('throttle:newsletter-signup')->name('newsletter.store');
Route::get('/confirm/{subscriber}', [SubscriptionController::class, 'confirm'])->middleware('signed')->name('newsletter.confirm');
Route::get('/unsubscribe/{subscriber}', [SubscriptionController::class, 'unsubscribe'])->middleware('signed')->name('newsletter.unsubscribe');
Route::get('/click/{newsletter}/{blockKey}', LinkClickController::class)->middleware('signed')->where('blockKey', '[A-Za-z0-9-]+')->name('newsletter.link-click');
