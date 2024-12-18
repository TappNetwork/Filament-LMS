<?php

use Tapp\FilamentLms\Http\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware('web')->group(function () {
    Route::get('lms/certificates/{course}/download', [CertificateController::class, 'download'])->name('filament-lms::certificates.download');
    Route::get('lms/certificates/{course}/{user}', [CertificateController::class, 'show'])->name('filament-lms::certificates.show');
});