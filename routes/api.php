<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;


Route::get('/', function () {
  return response()->json(['message' => 'API is running']);
  });





  Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('me',[AuthController::class, 'me'])->middleware('auth:sanctum');
  });
  