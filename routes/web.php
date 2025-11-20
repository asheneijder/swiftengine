<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwiftDashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', [SwiftDashboardController::class, 'index'])->name('dashboard');
Route::get('/download/{date}/{type}', [SwiftDashboardController::class, 'downloadCsv'])->name('download.csv');
