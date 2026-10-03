<?php

use App\Models\Campaign;
use App\Services\CampaignSender;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn() => \App\Models\SesEvent::prune())->daily()->name('prune-ses-events');

Schedule::call(fn() => app(\App\Services\UndeliveredService::class)->evaluate())->daily()->name('evaluate-undelivered');

Schedule::call(function () {
    Campaign::where('status', 'scheduled')
        ->where('scheduled_at', '<=', now())
        ->each(fn($campaign) => app(CampaignSender::class)->prepare($campaign));
})->everyMinute()->name('dispatch-scheduled-campaigns')->withoutOverlapping();
