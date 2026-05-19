<?php

use App\Http\Controllers\Api\AiDraftController;
use App\Http\Controllers\Api\CompanyBankAccountController;
use App\Http\Controllers\Api\CompanyBrandingController;
use App\Http\Controllers\Api\DocumentAttachmentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\MasterDataImportController;
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
    Route::post('/documents/{id}/duplicate', [DocumentController::class, 'duplicate']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    Route::post('/documents/bulk-delete-drafts', [DocumentController::class, 'bulkDeleteDrafts']);
    Route::get('/documents/{id}/pdf', [PdfDownloadController::class, 'show']);

    Route::get('/documents/{document}/attachments', [DocumentAttachmentController::class, 'index']);
    Route::post('/documents/{document}/attachments', [DocumentAttachmentController::class, 'store']);
    Route::patch('/documents/{document}/attachments/reorder', [DocumentAttachmentController::class, 'reorder']);
    Route::delete('/documents/{document}/attachments/{attachment}', [DocumentAttachmentController::class, 'destroy']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments/{payment}/generate-receipt', [PaymentController::class, 'generateReceipt']);

    Route::get('/companies', [MasterDataController::class, 'companies']);
    Route::patch('/companies/{company}', [MasterDataController::class, 'updateCompany']);
    Route::post('/companies/{company}/branding/{kind}', [CompanyBrandingController::class, 'upload'])
        ->whereIn('kind', ['logo', 'stamp', 'signature']);
    Route::delete('/companies/{company}/branding/{kind}', [CompanyBrandingController::class, 'destroy'])
        ->whereIn('kind', ['logo', 'stamp', 'signature']);

    Route::get('/companies/{company}/bank-accounts', [CompanyBankAccountController::class, 'index']);
    Route::post('/companies/{company}/bank-accounts', [CompanyBankAccountController::class, 'store']);
    Route::patch('/companies/{company}/bank-accounts/{account}', [CompanyBankAccountController::class, 'update']);
    Route::delete('/companies/{company}/bank-accounts/{account}', [CompanyBankAccountController::class, 'destroy']);

    Route::get('/customers', [MasterDataController::class, 'customers']);
    Route::post('/customers', [MasterDataController::class, 'storeCustomer']);
    Route::patch('/customers/{customer}', [MasterDataController::class, 'updateCustomer']);
    Route::delete('/customers/{customer}', [MasterDataController::class, 'destroyCustomer']);
    Route::get('/customers/import/template', [MasterDataImportController::class, 'customerTemplate']);
    Route::post('/customers/import', [MasterDataImportController::class, 'importCustomers']);
    Route::get('/products', [MasterDataController::class, 'products']);
    Route::post('/products', [MasterDataController::class, 'storeProduct']);
    Route::patch('/products/{product}', [MasterDataController::class, 'updateProduct']);
    Route::delete('/products/{product}', [MasterDataController::class, 'destroyProduct']);
    Route::get('/products/import/template', [MasterDataImportController::class, 'productTemplate']);
    Route::post('/products/import', [MasterDataImportController::class, 'importProducts']);
    Route::get('/templates', [MasterDataController::class, 'templates']);
    Route::post('/templates', [MasterDataController::class, 'storeTemplate']);
    Route::patch('/templates/{template}', [MasterDataController::class, 'updateTemplate']);
    Route::delete('/templates/{template}', [MasterDataController::class, 'destroyTemplate']);
    Route::get('/numbering-policies', [MasterDataController::class, 'numberingPolicies']);
    Route::post('/numbering-policies', [MasterDataController::class, 'storeNumberingPolicy']);
    Route::patch('/numbering-policies/{policy}', [MasterDataController::class, 'updateNumberingPolicy']);
    Route::delete('/numbering-policies/{policy}', [MasterDataController::class, 'destroyNumberingPolicy']);

    Route::post('/ai/deepseek/parse-draft', [AiDraftController::class, 'parse']);
});
