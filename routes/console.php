<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:process-absences')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/attendance_absences.log'));

Schedule::command('billing:process-daily')
    ->everyMinute() // every minute is for testing purposes, change to monthly in production
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing_processing.log'));
