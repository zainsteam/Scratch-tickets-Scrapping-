<?php
use App\Http\Controllers\ScrapController;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

// Exclude CSRF for API routes
Route::middleware(['web'])->group(function () {
    Route::get('/scrape-dc-lottery', [ScrapController::class, 'getMultipleData']);
    Route::get('/export-tickets', [ScrapController::class, 'exportTickets']);
    Route::get('/supported-sites', [ScrapController::class, 'getSupportedSites']);
    Route::get('/scrape-single', [ScrapController::class, 'scrapeSingleSite']);
    
    // State-wise scraping routes
    Route::get('/scrape-state/{state}', [ScrapController::class, 'scrapeState']);
    
    // State-wise export routes
    Route::get('/export-state/{state}', [ScrapController::class, 'exportStateTickets']);

    // URL Management Routes
Route::get('/api/states', [ScrapController::class, 'getConfiguredStates']);
Route::get('/api/states/urls', [ScrapController::class, 'getGamesListUrls']);
Route::post('/api/validate-url', [ScrapController::class, 'validateUrl'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);


});




