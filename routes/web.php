<?php

use App\Filament\Auth\Pages\PhoneLogin;
use App\Http\Controllers\AssetQrLabelPrintController;
use App\Http\Controllers\CameraUploadController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PublicAssetQrController;
use App\Http\Controllers\StoredFileController;
use App\Http\Controllers\SumoQrLabelController;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the bootstrap/app.php routing configuration and
| will be assigned to the "web" middleware group.
|
*/

Route::redirect('/', 'admin');

Route::get('phone-login', PhoneLogin::class)
    ->middleware([
        DisableBladeIconComponents::class,
        DispatchServingFilamentEvent::class,
    ])
    ->name('phone-login');

Route::get('asset-transfer/{id}/download', [PdfController::class, 'downloadAssetTransfer'])->middleware(['auth'])->name('asset-transfer.download');
Route::get('task-completion/{id}/download', [PdfController::class, 'downloadTaskCompletion'])->middleware(['auth'])->name('task-completion.download');
Route::get('task-completion/{id}/preview', [PdfController::class, 'previewTaskCompletion'])->middleware(['auth'])->name('task-completion.preview');
Route::post('admin/camera-upload', [CameraUploadController::class, 'upload'])->middleware(['auth'])->name('admin.camera-upload');
Route::get('stored-file/download', [StoredFileController::class, 'download'])
    ->middleware(['auth', 'signed'])
    ->name('stored-file.download');

Route::get('assets/{asset}/qr-label/print', [AssetQrLabelPrintController::class, 'show'])
    ->middleware(['auth'])
    ->name('assets.qr-label.print');
Route::get('assets/qr-labels/print', [AssetQrLabelPrintController::class, 'bulk'])
    ->middleware(['auth'])
    ->name('assets.qr-labels.print');

Route::get('qr/{qr}', [PublicAssetQrController::class, 'show'])->name('qr.show');
Route::post('qr/{qr}/location', [PublicAssetQrController::class, 'storeLocation'])
    ->middleware('throttle:60,1')
    ->name('qr.location');

// QR pengajuan barang dan QR barang (modul pindahan sumo-web).
Route::get('item-requests/{itemRequest}/qr-labels', [SumoQrLabelController::class, 'itemRequest'])
    ->middleware(['auth'])
    ->name('item-requests.qr-labels');
Route::get('products/{product}/qr-label', [SumoQrLabelController::class, 'product'])
    ->middleware(['auth'])
    ->name('products.qr-label');
