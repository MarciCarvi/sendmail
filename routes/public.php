<?php

use App\Http\Controllers\Public\ConfirmController;
use App\Http\Controllers\Public\SubscribeController;
use App\Http\Controllers\Public\SesWebhookController;
use App\Http\Controllers\Public\TrackClickController;
use App\Http\Controllers\Public\TrackOpenController;
use App\Http\Controllers\Public\UnsubscribeController;
use Illuminate\Support\Facades\Route;

// Tracking — no auth, no CSRF
Route::get('/t/o/{campaignId}/{token}', TrackOpenController::class)->name('track.open');
Route::get('/t/c/{campaignId}/{token}', TrackClickController::class)->name('track.click');

// Unsubscribe
Route::get('/u/{token}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
Route::post('/u/{token}', [UnsubscribeController::class, 'confirm'])->name('unsubscribe.confirm');

// Double opt-in confirm
Route::get('/c/{token}', ConfirmController::class)->name('confirm.optin');

// SES Webhook
Route::post('/webhook/ses', SesWebhookController::class)->name('webhook.ses');

// Form di iscrizione
Route::get('/embed/{token}',     [SubscribeController::class, 'form'])->name('subscribe.form');
Route::post('/subscribe/{token}', [SubscribeController::class, 'subscribe'])->name('subscribe.submit');
Route::options('/subscribe/{token}', fn() => response('', 204)
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With')
);
