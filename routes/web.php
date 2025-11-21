<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

//Untuk download
Route::get('/download-csv', [DashboardController::class, 'downloadCsv'])->name('download.csv');