<?php

use App\Http\Controllers\Public\ConfirmController;
use App\Http\Controllers\Public\SubscribeController;
use App\Http\Controllers\Public\SesWebhookController;
use App\Http\Controllers\Public\TrackClickController;
use App\Http\Controllers\Public\TrackOpenController;
use App\Http\Controllers\Public\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// Tracking — no auth, no CSRF. Generous limit: legit recipients open/click often.
Route::middleware('throttle:240,1')->group(function () {
    Route::get('/t/o/{campaignId}/{token}', TrackOpenController::class)->name('track.open');
    Route::get('/t/c/{campaignId}/{token}', TrackClickController::class)->name('track.click');
});

// Unsubscribe / double opt-in — tighter, user-facing pages.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/u/{token}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
    Route::post('/u/{token}', [UnsubscribeController::class, 'confirm'])->name('unsubscribe.confirm');
    Route::get('/c/{token}', ConfirmController::class)->name('confirm.optin');
});

// SES Webhook — signature-validated in the controller, left unthrottled so
// bursts of legitimate SNS notifications are never dropped.
Route::post('/webhook/ses', SesWebhookController::class)->name('webhook.ses');

// Form di iscrizione — strict throttle: public write endpoint, abuse target.
Route::get('/embed/{token}', [SubscribeController::class, 'form'])
    ->middleware('throttle:60,1')->name('subscribe.form');
Route::post('/subscribe/{token}', [SubscribeController::class, 'subscribe'])
    ->middleware('throttle:10,1')->name('subscribe.submit');
Route::options('/subscribe/{token}', fn() => response('', 204)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With')
);
