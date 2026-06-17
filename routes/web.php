<?php

use App\Http\Controllers\BlacklistController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\UnlayerController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified', 'check.license'])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-ses', [SettingsController::class, 'testSes'])->name('settings.test-ses');

    // Updates & License
    Route::get('/update/check', [UpdateController::class, 'check'])->name('update.check');
    Route::post('/update/check/force', [UpdateController::class, 'forceCheck'])->name('update.force-check');
    Route::post('/update/apply', [UpdateController::class, 'apply'])->name('update.apply');
    Route::get('/update/changelog', [UpdateController::class, 'changelog'])->name('update.changelog');
    Route::post('/license/check', [UpdateController::class, 'licenseCheck'])->name('license.check');

    // Liste
    Route::get('/lists', [ListController::class, 'index'])->name('lists.index');
    Route::post('/lists', [ListController::class, 'store'])->name('lists.store');
    Route::put('/lists/{list}', [ListController::class, 'update'])->name('lists.update');
    Route::delete('/lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');

    // Iscritti
    Route::get('/lists/{list}/subscribers', [SubscriberController::class, 'index'])->name('lists.subscribers.index');
    Route::post('/lists/{list}/subscribers', [SubscriberController::class, 'store'])->name('lists.subscribers.store');
    Route::put('/lists/{list}/subscribers/{subscriber}', [SubscriberController::class, 'update'])->name('lists.subscribers.update');
    Route::delete('/lists/{list}/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('lists.subscribers.destroy');
    Route::post('/lists/{list}/subscribers/import', [SubscriberController::class, 'import'])->name('lists.subscribers.import');
    Route::get('/lists/{list}/subscribers/export', [SubscriberController::class, 'export'])->name('lists.subscribers.export');
    Route::post('/lists/{list}/subscribers/bulk', [SubscriberController::class, 'bulk'])->name('lists.subscribers.bulk');

    // Unlayer saved blocks
    Route::get('/upload/images', [ImageUploadController::class, 'index'])->name('upload.images');
    Route::post('/upload/images', [ImageUploadController::class, 'store'])->name('upload.image');
    Route::delete('/upload/images/{name}', [ImageUploadController::class, 'destroy'])->name('upload.image.destroy');

    Route::get('/unlayer/blocks', [UnlayerController::class, 'blocks'])->name('unlayer.blocks');
    Route::post('/unlayer/blocks', [UnlayerController::class, 'saveBlock'])->name('unlayer.blocks.save');
    Route::put('/unlayer/blocks/{id}', [UnlayerController::class, 'updateBlock'])->name('unlayer.blocks.update');
    Route::delete('/unlayer/blocks/{id}', [UnlayerController::class, 'deleteBlock'])->name('unlayer.blocks.delete');

    // Blacklist
    Route::get('/blacklist', [BlacklistController::class, 'index'])->name('blacklist.index');
    Route::post('/blacklist', [BlacklistController::class, 'store'])->name('blacklist.store');
    Route::delete('/blacklist/{blacklist}', [BlacklistController::class, 'destroy'])->name('blacklist.destroy');

    // Report
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{campaign}', [ReportController::class, 'show'])->name('reports.show');

    // Campagne
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
    Route::post('/campaigns/{campaign}/send-test', [CampaignController::class, 'sendTest'])->name('campaigns.send-test');
    Route::post('/campaigns/{campaign}/send-now', [CampaignController::class, 'sendNow'])->name('campaigns.send-now');
    Route::post('/campaigns/{campaign}/process-batch', [CampaignController::class, 'processBatch'])->name('campaigns.process-batch');
    Route::post('/campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])->name('campaigns.schedule');
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::get('/campaigns/{campaign}/progress', [CampaignController::class, 'progress'])->name('campaigns.progress');
});

require __DIR__.'/auth.php';
