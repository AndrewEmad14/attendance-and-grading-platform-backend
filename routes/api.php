<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CourseController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cohorts/{cohortId}/courses',  [CourseController::class, 'index']);
    Route::post('/cohorts/{cohortId}/courses', [CourseController::class, 'store']);
    Route::get('/courses/{course}',            [CourseController::class, 'show']);
    Route::patch('/courses/{course}',          [CourseController::class, 'update']);
    Route::delete('/courses/{course}',         [CourseController::class, 'destroy']);
});