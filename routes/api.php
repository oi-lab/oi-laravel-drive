<?php

use Illuminate\Support\Facades\Route;
use OiLab\OiLaravelDrive\Http\Controllers\DriveContentsController;
use OiLab\OiLaravelDrive\Http\Controllers\DriveController;
use OiLab\OiLaravelDrive\Http\Controllers\FileController;
use OiLab\OiLaravelDrive\Http\Controllers\FileFolderController;
use OiLab\OiLaravelDrive\Http\Controllers\FolderController;

Route::get('drives', [DriveController::class, 'index'])->name('drives.index');
Route::post('drives', [DriveController::class, 'store'])->name('drives.store');
Route::get('drives/{drive}', [DriveController::class, 'show'])->name('drives.show');
Route::patch('drives/{drive}', [DriveController::class, 'update'])->name('drives.update');
Route::delete('drives/{drive}', [DriveController::class, 'destroy'])->name('drives.destroy');

Route::get('contents', [DriveContentsController::class, 'contents'])->name('contents');
Route::get('search', [DriveContentsController::class, 'search'])->name('search');
Route::get('trash', [DriveContentsController::class, 'trash'])->name('trash');
Route::delete('trash', [DriveContentsController::class, 'emptyTrash'])->name('trash.empty');

Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
Route::get('folders/{folder}', [FolderController::class, 'show'])->name('folders.show');
Route::patch('folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
Route::post('folders/{folder}/restore', [FolderController::class, 'restore'])->name('folders.restore');

Route::post('files', [FileController::class, 'store'])->name('files.store');
Route::get('files/{file}', [FileController::class, 'show'])->name('files.show');
Route::get('files/{file}/content', [FileController::class, 'content'])->name('files.content');
Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');
Route::patch('files/{file}', [FileController::class, 'update'])->name('files.update');
Route::delete('files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
Route::post('files/{file}/restore', [FileController::class, 'restore'])->name('files.restore');
Route::post('files/{file}/folders', [FileFolderController::class, 'store'])->name('files.folders.store');
Route::delete('files/{file}/folders/{folder}', [FileFolderController::class, 'destroy'])->name('files.folders.destroy');
