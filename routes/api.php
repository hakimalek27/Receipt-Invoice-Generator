<?php

use App\Http\Controllers\Api\AiDraftController;
use App\Http\Controllers\Api\DocumentAttachmentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PdfDownloadController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::patch('/documents/{id}', [DocumentController::class, 'update']);
    Route::post('/documents/{id}/issue', [DocumentController::class, 'issue']);
    Route::post('/documents/{id}/void', [DocumentController::class, 'void']);
    Route::post('/documents/{id}/convert', [DocumentController::class, 'convert']);
    Route::get('/documents/{id}/pdf', [PdfDownloadController::class, 'show']);

    Route::get('/documents/{document}/attachments', [DocumentAttachmentController::class, 'index']);
    Route::post('/documents/{document}/attachments', [DocumentAttachmentController::class, 'store']);
    Route::patch('/documents/{document}/attachments/reorder', [DocumentAttachmentController::class, 'reorder']);
    Route::delete('/documents/{document}/attachments/{attachment}', [DocumentAttachmentController::class, 'destroy']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);

    Route::get('/companies', [MasterDataController::class, 'companies']);
    Route::patch('/companies/{company}', [MasterDataController::class, 'updateCompany']);
    Route::get('/customers', [MasterDataController::class, 'customers']);
    Route::post('/customers', [MasterDataController::class, 'storeCustomer']);
    Route::patch('/customers/{customer}', [MasterDataController::class, 'updateCustomer']);
    Route::get('/products', [MasterDataController::class, 'products']);
    Route::post('/products', [MasterDataController::class, 'storeProduct']);
    Route::patch('/products/{product}', [MasterDataController::class, 'updateProduct']);
    Route::get('/templates', [MasterDataController::class, 'templates']);
    Route::post('/templates', [MasterDataController::class, 'storeTemplate']);
    Route::patch('/templates/{template}', [MasterDataController::class, 'updateTemplate']);
    Route::get('/numbering-policies', [MasterDataController::class, 'numberingPolicies']);
    Route::post('/numbering-policies', [MasterDataController::class, 'storeNumberingPolicy']);
    Route::patch('/numbering-policies/{policy}', [MasterDataController::class, 'updateNumberingPolicy']);

    Route::post('/ai/deepseek/parse-draft', [AiDraftController::class, 'parse']);
});
