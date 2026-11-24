<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Phase 4 (§9.1): past_due flip, daily 00:05 Asia/Dhaka.
Schedule::command('subscriptions:mark-past-due')->dailyAt('00:05')->timezone('Asia/Dhaka');
