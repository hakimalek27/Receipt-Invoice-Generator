<?php

use App\Http\Controllers\Api\DocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::post('/documents/{id}/issue', [DocumentController::class, 'issue']);
});
