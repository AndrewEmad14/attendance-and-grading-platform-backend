<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CohortController;


Route::get('/', function () {
  return response()->json(['message' => 'API is running']);
  });


  Route::middleware('auth:sanctum')->group(function () {
      Route::prefix('tracks/{track}')->group(function () {
          Route::post('cohorts', [CohortController::class, 'store']);
      });
  });


  Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('me',[AuthController::class, 'me'])->middleware('auth:sanctum');
  });
  