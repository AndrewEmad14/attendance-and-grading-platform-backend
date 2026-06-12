<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:process-absences')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/attendance_absences.log'));
