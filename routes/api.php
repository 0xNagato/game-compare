<?php

use App\Http\Controllers\Api\CompareSeriesController;
use App\Http\Controllers\Api\GameDetailController;
use App\Http\Controllers\Api\GeoCountriesController;
use App\Http\Controllers\Api\MapChoroplethController;
use App\Http\Controllers\Api\PlatformsIndexController;
use App\Http\Controllers\Api\ProductIndexController;
use App\Http\Controllers\Api\ProductPriceInsightsController;
use App\Http\Controllers\Api\ProductVendorCompareController;
use App\Http\Controllers\Api\RegionsIndexController;
use App\Http\Controllers\Api\SidebarMetaController;
use App\Http\Controllers\Api\TopGamesController;
use App\Http\Controllers\Api\VendorCompareController;
use Illuminate\Support\Facades\Route;

Route::get('/top', TopGamesController::class)->name('api.top');
Route::get('/games/{uid}', GameDetailController::class)->name('api.games.show');
Route::get('/products', ProductIndexController::class)->name('api.products.index');
Route::get('/compare', CompareSeriesController::class)->name('api.compare');
Route::get('/compare/vendors', VendorCompareController::class)->name('api.compare.vendors');
Route::get('/map/choropleth', MapChoroplethController::class)->name('api.map.choropleth');
Route::get('/regions', RegionsIndexController::class)->name('api.regions.index');
Route::get('/geo/countries', GeoCountriesController::class)->name('api.geo.countries');
Route::get('/sidebar', SidebarMetaController::class)->name('api.sidebar');
Route::get('/platforms', PlatformsIndexController::class)->name('api.platforms.index');
Route::get('/games/{product:slug}/compare', [ProductPriceInsightsController::class, 'compare'])->name('api.games.compare');
Route::get('/games/{product:slug}/history', [ProductPriceInsightsController::class, 'history'])->name('api.games.history');
Route::get('/games/{product:slug}/vendors', ProductVendorCompareController::class)->name('api.games.vendors');
