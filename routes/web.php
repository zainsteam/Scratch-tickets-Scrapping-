<?php
use App\Http\Controllers\ScrapController;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/scrape-dc-lottery', [ScrapController::class, 'getMultipleData']);
Route::get('/export-tickets', [ScrapController::class, 'exportTickets']);
Route::get('/supported-sites', [ScrapController::class, 'getSupportedSites']);
Route::post('/scrape-single', [ScrapController::class, 'scrapeSingleSite']);


