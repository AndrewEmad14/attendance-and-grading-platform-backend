<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:process-absences')
  ->everyMinute()
  ->appendOutputTo(storage_path('logs/attendance_absences.log'));;
