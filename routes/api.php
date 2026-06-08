<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CohortController;
use App\Http\Controllers\Api\LabGroupController;
use App\Http\Controllers\Api\EngagementController;


Route::get('/', function () {
  return response()->json(['message' => 'API is running']);
  });


  Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('tracks/{track}')->group(function () {
      Route::post('cohorts', [CohortController::class, 'store']);
    });

    Route::prefix('cohorts/{cohort}')->group(function () {
      Route::get('lab-groups', [LabGroupController::class, 'index']);
      Route::post('lab-groups', [LabGroupController::class, 'store']);
    });

    Route::prefix('lab-groups/{labGroup}')->group(function () {
      Route::post('students', [LabGroupController::class, 'attachStudent']);
      Route::delete('students/{studentId}', [LabGroupController::class, 'detachStudent']);
      Route::delete('', [LabGroupController::class, 'destroy']);
    });

    Route::prefix('engagements')->group(function () {
      Route::get('', [EngagementController::class, 'index']);
      Route::post('', [EngagementController::class, 'store']);
      Route::get('{engagement}', [EngagementController::class, 'show']);
      Route::patch('{engagement}', [EngagementController::class, 'update']);
      Route::delete('{engagement}', [EngagementController::class, 'destroy']);
    });

    Route::patch('cohorts/{cohort}', [CohortController::class, 'update']);
  });


  Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('me',[AuthController::class, 'me'])->middleware('auth:sanctum');
  });
  