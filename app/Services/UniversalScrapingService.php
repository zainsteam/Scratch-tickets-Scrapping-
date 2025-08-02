<?php

namespace App\Services;

use App\Services\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class UniversalScrapingService
{
    private ScraperFactory $scraperFactory;
    
    public function __construct(ScraperFactory $scraperFactory)
    {
        $this->scraperFactory = $scraperFactory;
    }
    
    /**
     * Scrape a single URL using the appropriate scraper
     */
    public function scrapeSingleUrl(string $url): array
    {
        try {
            // Get the appropriate scraper for this URL
            $scraper = $this->scraperFactory->getScraper($url);
            
            // Fetch the page with timeout and retry
            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])
                ->get($url);
                
            if (!$response->ok()) {
                return [
                    'error' => 'Failed to fetch page content',
                    'url' => $url,
                    'site' => $scraper->getSiteName()
                ];
            }
            
            // Parse the HTML
            $html = $response->body();
            $crawler = new Crawler($html);
            
            // Extract data using the appropriate scraper
            $basicInfo = $scraper->extractBasicInfo($crawler);
            $prizes = $scraper->extractPrizes($crawler);
            $odds = $scraper->extractOdds($crawler);
            $image = $scraper->extractImage($crawler);
            
            // Combine all data
            $data = array_merge($basicInfo, $prizes, $odds);
            $data['image'] = $image;
            $data['site'] = $scraper->getSiteName();
            $data['url'] = $url;
            
            return $data;
            
        } catch (\Throwable $e) {
            Log::error('Scraping error: ' . $e->getMessage(), [
                'url' => $url,
                'exception' => $e
            ]);
            
            return [
                'error' => 'Something went wrong while scraping.',
                'message' => $e->getMessage(),
                'url' => $url
            ];
        }
    }
    
    /**
     * Scrape multiple URLs in parallel
     */
    public function scrapeUrlsInParallel(array $urls, int $concurrency = 10): array
    {
        $results = [];
        $chunks = array_chunk($urls, $concurrency);
        
        foreach ($chunks as $chunk) {
            // Use Http::pool with a callable that returns the requests
            $responses = Http::pool(function ($pool) use ($chunk) {
                foreach ($chunk as $url) {
                    $pool->timeout(30)
                        ->retry(3, 1000)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                        ])
                        ->get($url);
                }
            });
            
            // Process responses
            foreach ($responses as $index => $response) {
                try {
                    // Get URL from the original chunk array since effectiveUri() might not be available
                    $url = $chunk[$index] ?? 'unknown-url';
                    
                    // Check if response is an exception
                    if ($response instanceof \Exception) {
                        Log::error('HTTP request failed: ' . $response->getMessage(), [
                            'url' => $url,
                            'exception' => $response
                        ]);
                        continue;
                    }
                    
                    $rawData = $this->processResponse($response, $url);
                    if (!isset($rawData['error'])) {
                        $results[] = $rawData;
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing response: ' . $e->getMessage(), [
                        'url' => $url ?? 'unknown-url',
                        'exception' => $e
                    ]);
                }
            }
            
            // Small delay to be respectful to the server
            usleep(100000); // 0.1 second
        }
        
        return $results;
    }
    
    /**
     * Process a single response
     */
    private function processResponse($response, string $url): array
    {
        if (!$response->ok()) {
            return ['error' => 'Failed to fetch page content', 'url' => $url];
        }

        try {
            // Get the appropriate scraper for this URL
            $scraper = $this->scraperFactory->getScraper($url);
            
            $html = $response->body();
            $crawler = new Crawler($html);
            
            // Extract data using the appropriate scraper
            $basicInfo = $scraper->extractBasicInfo($crawler);
            $prizes = $scraper->extractPrizes($crawler);
            $odds = $scraper->extractOdds($crawler);
            $image = $scraper->extractImage($crawler);
            
            // Combine all data
            $data = array_merge($basicInfo, $prizes, $odds);
            $data['image'] = $image;
            $data['site'] = $scraper->getSiteName();
            $data['url'] = $url;
            
            return $data;
            
        } catch (\Throwable $e) {
            Log::error('Scraping error: ' . $e->getMessage(), [
                'url' => $url,
                'exception' => $e
            ]);
            
            return [
                'error' => 'Something went wrong while scraping.',
                'message' => $e->getMessage(),
                'url' => $url
            ];
        }
    }
    
    /**
     * Get supported domains
     */
    public function getSupportedDomains(): array
    {
        return $this->scraperFactory->getSupportedDomains();
    }
    
    /**
     * Validate if a URL is supported
     */
    public function isUrlSupported(string $url): bool
    {
        try {
            $this->scraperFactory->getScraper($url);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
} 