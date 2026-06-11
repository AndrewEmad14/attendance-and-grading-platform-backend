<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:process-absences')->everyMinute();
