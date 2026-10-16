<?php

use Illuminate\Support\Facades\Route;
use OiLab\OiLaravelDrive\Http\Controllers\SignedFileController;
use OiLab\OiLaravelDrive\Http\Controllers\UploadController;

Route::get('files/{file}/download', [SignedFileController::class, 'download'])->name('files.download');
Route::put('uploads/{upload}', [UploadController::class, 'store'])->name('uploads.put');
Route::get('uploads/{upload}', [UploadController::class, 'page'])->name('uploads.page');
