<?php

use App\Http\Controllers\RecordIntake\UploadController;
use App\Http\Controllers\Records\ClassifyController;
use App\Http\Controllers\Records\RecordActivityLogController;
use App\Http\Controllers\Records\RecordArchiveController;
use App\Http\Controllers\Records\RecordClassificationController;
use App\Http\Controllers\Records\RecordController;
use App\Http\Controllers\Records\RecordLabelController;
use App\Http\Controllers\Records\RecordDashboardController;
use App\Http\Controllers\Records\RecordFieldController;
use App\Http\Controllers\Records\RecordFileAttachmentController;
use App\Http\Controllers\Records\RecordFilePreviewController;
use App\Http\Controllers\Records\RecordFileReadingController;
use App\Http\Controllers\Records\RecordFileRenderController;
use App\Http\Controllers\Records\RecordIntegrationController;
use App\Http\Controllers\Records\RecordNumberingController;
use App\Http\Controllers\Records\RecordPickerController;
use App\Http\Controllers\Records\RecordPlanLimitsController;
use App\Http\Controllers\Records\RecordReportController;
use App\Http\Controllers\Records\RecordSetupController;
use App\Http\Controllers\Records\RecordSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware('ordo.context')->group(function (): void {
    Route::get('/records/classifications', [RecordClassificationController::class, 'index'])
        ->name('records.classifications');

    Route::post('/records/classifications', [RecordClassificationController::class, 'storeClassification'])
        ->name('records.classifications.store');

    Route::post('/records/classifications/{classification}/indexes', [RecordClassificationController::class, 'storeIndex'])
        ->name('records.classifications.indexes.store');

    Route::get('/records/archive', [RecordArchiveController::class, 'index'])
        ->name('records.archive.index');

    Route::post('/records/{record}/restore', [RecordArchiveController::class, 'restore'])
        ->middleware('ordo.record')
        ->name('records.restore');

    Route::get('/records/reports', [RecordReportController::class, 'index'])
        ->name('records.reports.index');

    Route::post('/records/reports/generate', [RecordReportController::class, 'generate'])
        ->name('records.reports.generate');

    Route::post('/records/reports/runs', [RecordReportController::class, 'store'])
        ->middleware('throttle:exports')
        ->name('records.reports.runs.store');

    Route::get('/records/reports/history', [RecordReportController::class, 'history'])
        ->name('records.reports.history');

    Route::get('/records/reports/export', [RecordReportController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('records.reports.export');

    Route::get('/records/intake/upload', [UploadController::class, 'create'])
        ->name('records.intake.upload');

    Route::post('/records/intake', [UploadController::class, 'store'])
        ->name('records.intake.store');

    Route::get('/records/unclassified', [ClassifyController::class, 'index'])
        ->name('records.unclassified');

    Route::post('/records/unclassified/bulk', [ClassifyController::class, 'bulkStore'])
        ->name('records.unclassified.bulk');

    Route::post('/records/unclassified/{intake}', [ClassifyController::class, 'store'])
        ->name('records.unclassified.file');

    Route::get('/records/unclassified/{intake}/preview', [ClassifyController::class, 'preview'])
        ->name('records.unclassified.preview');

    Route::delete('/records/unclassified/{intake}', [ClassifyController::class, 'destroy'])
        ->name('records.unclassified.destroy');

    Route::get('/records', [RecordController::class, 'index'])
        ->name('records.index');

    Route::get('/records/dashboard', [RecordDashboardController::class, 'index'])
        ->name('records.dashboard');

    Route::get('/records/export', [RecordController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('records.export');

    Route::get('/records/logs', [RecordActivityLogController::class, 'index'])
        ->name('records.logs');

    Route::get('/records/logs/export', [RecordActivityLogController::class, 'export'])
        ->middleware('throttle:exports')
        ->name('records.logs.export');

    Route::delete('/records/links/{link}', [RecordPickerController::class, 'destroy'])
        ->name('records.links.destroy');

    Route::post('/records/integration/simulate', [RecordIntegrationController::class, 'simulate'])
        ->name('records.integration.simulate');

    Route::get('/records/{record}/links/picker', [RecordPickerController::class, 'index'])
        ->middleware(['ordo.record', 'throttle:picker'])
        ->name('records.links.picker');

    Route::post('/records/{record}/links', [RecordPickerController::class, 'store'])
        ->middleware('ordo.record')
        ->name('records.links.store');

    Route::get('/records/setup/{tab}', [RecordSetupController::class, 'tab'])
        ->whereIn('tab', RecordSetupController::TABS)
        ->name('records.setup.tab');

    Route::post('/records/sources', [RecordSourceController::class, 'store'])
        ->name('records.sources.store');

    Route::post('/records/fields', [RecordFieldController::class, 'store'])
        ->name('records.fields.store');

    Route::put('/records/plan-limits', [RecordPlanLimitsController::class, 'update'])
        ->name('records.plan-limits.update');

    Route::put('/records/numbering', [RecordNumberingController::class, 'update'])
        ->name('records.numbering.update');

    Route::put('/records/{record}', [RecordController::class, 'update'])
        ->middleware('ordo.record')
        ->name('records.update');

    Route::post('/records/{record}/archive', [RecordController::class, 'archive'])
        ->middleware('ordo.record')
        ->name('records.archive');

    Route::post('/records/{record}/files/supporting', [RecordFileAttachmentController::class, 'supportingFile'])
        ->middleware('ordo.record')
        ->name('records.files.supporting');

    Route::post('/records/{record}/files/version', [RecordFileAttachmentController::class, 'version'])
        ->middleware('ordo.record')
        ->name('records.files.version');

    Route::post('/records/files/upload-as-record', [RecordFileAttachmentController::class, 'uploadAsRecord'])
        ->name('records.files.upload-as-record');

    Route::post('/records/files/{file}/re-read', [RecordFileReadingController::class, 'reRead'])
        ->name('records.files.re-read');

    Route::get('/records/files/{file}/preview', [RecordFilePreviewController::class, 'preview'])
        ->name('records.files.preview');

    Route::get('/records/files/{file}/download', [RecordFilePreviewController::class, 'download'])
        ->name('records.files.download');

    Route::get('/records/files/{file}/rendered', [RecordFileRenderController::class, 'rendered'])
        ->name('records.files.rendered');

    Route::get('/records/files/{file}/status', [RecordFileReadingController::class, 'status'])
        ->name('records.files.status');

    Route::get('/records/{record}/preview', [RecordController::class, 'preview'])
        ->middleware('ordo.record')
        ->name('records.preview');

    Route::get('/records/{record}/label', [RecordLabelController::class, 'show'])
        ->middleware(['ordo.record', 'can:view,record'])
        ->name('records.label');
});
