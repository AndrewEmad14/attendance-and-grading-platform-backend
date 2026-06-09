<?php

use Illuminate\Support\Facades\Route;
use App\Enums\Role;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CohortController;
use App\Http\Controllers\Api\LabGroupController;
use App\Http\Controllers\Api\EngagementController;
use App\Http\Controllers\Api\BusinessSessionController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\SubmissionController;

//$table->enum('role', ['branch_manager', 'track_admin', 'instructor', 'student']);


Route::get('/', function () {
  return response()->json(['message' => 'API is running']);
  });


  Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('tracks/{track}')->group(function () {
      Route::post('cohorts', [CohortController::class, 'store']);
    });

    Route::get('/cohorts/{cohortId}/courses',  [CourseController::class, 'index']);
    Route::post('/cohorts/{cohortId}/courses', [CourseController::class, 'store']);
    Route::get('/courses/{course}',            [CourseController::class, 'show']);
    Route::patch('/courses/{course}',          [CourseController::class, 'update']);
    Route::delete('/courses/{course}',         [CourseController::class, 'destroy']);

    Route::patch('/submissions/{submission}', [SubmissionController::class, 'grade']);
    Route::post('/submissions/{submission}/override', [SubmissionController::class, 'override']);

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

    Route::prefix('business-sessions')->group(function () {

      Route::get('', [BusinessSessionController::class, 'index']);
      Route::post('', [BusinessSessionController::class, 'store']);
      Route::get('{businessSession}', [BusinessSessionController::class, 'show']);
      
      Route::post('{businessSession}/cohorts', [BusinessSessionController::class, 'enrollCohort']);
      Route::delete('{businessSession}/cohorts/{cohortId}', [BusinessSessionController::class, 'removeCohort']);

      Route::patch('cohorts/{cohort}', [CohortController::class, 'update']);
    });
  });

  Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('me',[AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('logout-all', [AuthController::class, 'logoutAll'])->middleware('auth:sanctum');
  });
  
  