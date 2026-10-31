<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:reconcile')->hourly();
Schedule::command('billing:renew-subscriptions')->dailyAt('00:15');
Schedule::command('connectors:expire-grants')->hourly();
Schedule::command('desktop:prune-handoffs')->dailyAt('03:10');
Schedule::command('gateway:prune-logs')->dailyAt('03:30');
Schedule::command('providers:health-checks')->hourly()->withoutOverlapping();
