<?php

use Illuminate\Support\Facades\Route;
use RsmMonaem\MetaAdsAttribution\Controllers\MetaAttributionDashboardController;

Route::group([
    'prefix' => config('meta-attribution.dashboard.prefix', 'admin/meta-attribution'),
    'middleware' => config('meta-attribution.dashboard.middleware', ['web']),
], function () {
    Route::get('/', [MetaAttributionDashboardController::class, 'index'])->name('meta-attribution.dashboard');
    Route::post('/retry/{id}', [MetaAttributionDashboardController::class, 'retryEvent'])->name('meta-attribution.retry');
});
