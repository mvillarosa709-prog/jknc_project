<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExternalAcknowledgmentController;
use App\Http\Controllers\TransmittalController;
use App\Http\Controllers\TransmittalUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login'); // Idagdag ang ->name('login') dito
    Route::post('/admin/login', [AuthController::class, 'store'])->name('login.store')->middleware('throttle:6,1');
});

Route::get('/acknowledgments/{token}', [ExternalAcknowledgmentController::class, 'show'])
    ->name('acknowledgments.show')
    ->middleware('throttle:20,1');
Route::get('/acknowledgments/{token}/documents/{document}', [TransmittalController::class, 'downloadForRecipient'])
    ->whereNumber('document')
    ->name('acknowledgments.documents.download')
    ->middleware('throttle:30,1');
Route::get('/acknowledgments/{token}/receipts/{receipt}/pdf', [TransmittalController::class, 'receiptPdfForRecipient'])
    ->whereNumber('receipt')
    ->name('acknowledgments.receipts.pdf')
    ->middleware('throttle:20,1');
Route::post('/acknowledgments/{token}', [ExternalAcknowledgmentController::class, 'store'])
    ->name('acknowledgments.store')
    ->middleware('throttle:6,1');

Route::get('/', fn () => redirect()->route('transmittals.index'))->name('home');
Route::get('/transmittals', [TransmittalController::class, 'index'])->name('transmittals.index');
Route::get('/transmittals/create', [TransmittalController::class, 'create'])->name('transmittals.create');
Route::get('/transmittals/smart-upload', [TransmittalController::class, 'smartUpload'])->name('transmittals.smart-upload');
Route::post('/transmittals/smart-upload', [TransmittalController::class, 'createFromSmartUpload'])
    ->name('transmittals.smart-upload.store')
    ->middleware('throttle:10,1');
Route::get('/transmittals/{transmittal}/workspace', [TransmittalController::class, 'workspace'])
    ->whereNumber('transmittal')
    ->name('transmittals.workspace');
Route::get('/transmittals/{transmittal}/edit', [TransmittalController::class, 'edit'])
    ->whereNumber('transmittal')
    ->name('transmittals.edit');
Route::put('/transmittals/{transmittal}', [TransmittalController::class, 'update'])
    ->whereNumber('transmittal')
    ->name('transmittals.update')
    ->middleware('throttle:10,1');
Route::post('/transmittals/analyze', [TransmittalController::class, 'analyze'])
    ->name('transmittals.analyze')
    ->middleware('throttle:10,1');
Route::post('/transmittals/uploads', [TransmittalUploadController::class, 'start'])
    ->name('transmittals.uploads.start')
    ->middleware('throttle:30,1');
Route::post('/transmittals/uploads/{uploadId}/chunks/{index}', [TransmittalUploadController::class, 'putChunk'])
    ->whereUuid('uploadId')
    ->whereNumber('index')
    ->name('transmittals.uploads.chunks.store')
    ->middleware('throttle:120,1');
Route::post('/transmittals/uploads/{uploadId}/complete', [TransmittalUploadController::class, 'complete'])
    ->whereUuid('uploadId')
    ->name('transmittals.uploads.complete')
    ->middleware('throttle:30,1');
Route::post('/transmittals/uploads/{uploadId}/analyze', [TransmittalUploadController::class, 'analyze'])
    ->whereUuid('uploadId')
    ->name('transmittals.uploads.analyze')
    ->middleware('throttle:10,1');
Route::post('/transmittals', [TransmittalController::class, 'store'])
    ->name('transmittals.store')
    ->middleware('throttle:10,1');
Route::post('/transmittals/{transmittal}/recall', [TransmittalController::class, 'recall'])
    ->whereNumber('transmittal')
    ->name('transmittals.recall')
    ->middleware('throttle:10,1');
Route::post('/transmittals/{transmittal}/cancel', [TransmittalController::class, 'cancel'])
    ->whereNumber('transmittal')
    ->name('transmittals.cancel')
    ->middleware('throttle:10,1');
Route::get('/transmittals/{transmittal}', [TransmittalController::class, 'show'])->name('transmittals.show');
Route::get('/transmittals/{transmittal}/receipts/{receipt}/pdf', [TransmittalController::class, 'receiptPdf'])
    ->whereNumber('receipt')
    ->name('transmittals.receipts.pdf');
Route::get('/transmittals/{transmittal}/documents/{document}', [TransmittalController::class, 'download'])
    ->whereNumber('document')
    ->name('transmittals.documents.download');
Route::get('/transmittals/{transmittal}/pdf', [TransmittalController::class, 'pdf'])->name('transmittals.pdf');


Route::post('/transmittals/{transmittal}/proofs', [TransmittalController::class, 'uploadProof'])->name('transmittals.proofs.store');
// Inilipat dito para ma-access kahit walang login
Route::post('/transmittals/{transmittal}/transmit', [TransmittalController::class, 'transmit'])
    ->whereNumber('transmittal')
    ->name('transmittals.transmit')
    ->middleware('throttle:10,1');

Route::middleware('auth')->group(function (): void {
    Route::post('/admin/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/transmittals/{transmittal}/approve', [TransmittalController::class, 'approve'])->name('transmittals.approve');
    Route::post('/transmittals/{transmittal}/reject', [TransmittalController::class, 'reject'])->name('transmittals.reject');
    Route::put('/transmittals/{transmittal}/proofs/{proof}', [TransmittalController::class, 'updateProof'])
        ->whereNumber('proof')
        ->name('transmittals.proofs.update');
    Route::delete('/transmittals/{transmittal}', [TransmittalController::class, 'destroy'])->name('transmittals.destroy');
    Route::post('/transmittals/{transmittal}/restore', [TransmittalController::class, 'restore'])
        ->whereNumber('transmittal')
        ->name('transmittals.restore');
    Route::delete('/transmittals/{transmittal}/permanent', [TransmittalController::class, 'forceDelete'])
        ->whereNumber('transmittal')
        ->name('transmittals.force-delete');
    Route::post('/transmittals/{transmittal}/recipients/{recipient}/revoke-link', [TransmittalController::class, 'revokeRecipientLink'])
        ->whereNumber('transmittal')
        ->whereNumber('recipient')
        ->name('transmittals.recipients.revoke-link');
        Route::post('/transmittals/{transmittal}/submit-for-approval', [TransmittalController::class, 'submitForApproval'])->name('transmittals.submit-for-approval');
});