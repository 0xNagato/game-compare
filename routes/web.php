<?php

use App\Http\Controllers\ComparePageController;
use App\Http\Controllers\GamePageController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingPageController::class)->name('home');
Route::get('/compare', ComparePageController::class)->name('compare');
Route::get('/games/{product:slug}', GamePageController::class)->name('games.show');

// Signed media proxy for reliable inline video playback (supports Range requests)
Route::get('/media/play/{name}.{ext}', [MediaStreamController::class, 'play'])
    ->where(['name' => '[A-Za-z0-9_-]+', 'ext' => '(mp4|webm|m4v|mov)'])
    ->middleware('signed')
    ->name('media.play');

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect('/admin'))->name('dashboard');
});
