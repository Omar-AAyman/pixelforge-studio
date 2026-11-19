<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use App\Http\Middleware\VerifyPixelforgeSignature;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)->middleware(VerifyPixelforgeSignature::class);

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
});

require __DIR__.'/settings.php';
