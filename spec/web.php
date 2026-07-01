<?php

use App\Http\Middleware\VerifyDeployToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::prefix('deploy')->middleware(VerifyDeployToken::class)->group(function () {
    Route::post('migrate', function () {
        Artisan::call('migrate', ['--force' => true]);
        return response()->json(['status' => 'ok', 'output' => Artisan::output()]);
    });

    Route::post('optimize', function () {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        Artisan::call('event:cache');
        Artisan::call('filament:optimize');
        return response()->json(['status' => 'ok', 'output' => 'All caches optimized']);
    });

    Route::post('storage-link', function () {
        Artisan::call('storage:link', ['--force' => true]);
        return response()->json(['status' => 'ok', 'output' => Artisan::output()]);
    });

    Route::post('seed', function () {
        Artisan::call('db:seed', ['--force' => true]);
        return response()->json(['status' => 'ok', 'output' => Artisan::output()]);
    });
});
