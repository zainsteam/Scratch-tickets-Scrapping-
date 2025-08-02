<?php

namespace App\Services;

use App\Services\Scrapers\BaseScraper;
use App\Services\Scrapers\DCLotteryScraper;
use App\Services\Scrapers\MarylandLotteryScraper;
use App\Services\Scrapers\VirginiaLotteryScraper;
use InvalidArgumentException;

class ScraperFactory
{
    private array $scrapers = [];
    
    public function __construct()
    {
        // Register all available scrapers
        $this->scrapers = [
            new DCLotteryScraper(),
            new MarylandLotteryScraper(),
            new VirginiaLotteryScraper(),
            // Add more scrapers here as needed
        ];
    }
    
    /**
     * Get the appropriate scraper for a given URL
     */
    public function getScraper(string $url): BaseScraper
    {
        foreach ($this->scrapers as $scraper) {
            if ($scraper->canHandle($url)) {
                return $scraper;
            }
        }
        
        throw new InvalidArgumentException("No scraper found for URL: $url");
    }
    
    /**
     * Get all available scrapers
     */
    public function getAllScrapers(): array
    {
        return $this->scrapers;
    }
    
    /**
     * Register a new scraper
     */
    public function registerScraper(BaseScraper $scraper): void
    {
        $this->scrapers[] = $scraper;
    }
    
    /**
     * Get supported domains
     */
    public function getSupportedDomains(): array
    {
        return array_map(function ($scraper) {
            return [
                'name' => $scraper->getSiteName(),
                'scraper' => get_class($scraper)
            ];
        }, $this->scrapers);
    }
} 