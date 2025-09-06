<?php

namespace App\Services;

use App\Services\Scrapers\BaseScraper;
use App\Services\Scrapers\DCLotteryScraper;
use App\Services\Scrapers\MarylandLotteryScraper;
use App\Services\Scrapers\VirginiaLotteryScraper;
use App\Services\Scrapers\ArkansasLotteryScraper;
use App\Services\Scrapers\CaliforniaLotteryScraper;
use App\Services\Scrapers\ConnecticutLotteryScraper;
use App\Services\Scrapers\IndianaLotteryScraper;
use App\Services\Scrapers\KansasLotteryScraper;
use App\Services\Scrapers\KentuckyLotteryScraper;
use App\Services\Scrapers\LouisianaLotteryScraper;
use App\Services\Scrapers\MississippiLotteryScraper;
use App\Services\Scrapers\MichiganLotteryScraper;
use App\Services\Scrapers\MinnesotaLotteryScraper;
use App\Services\Scrapers\NewJerseyLotteryScraper;
use App\Services\Scrapers\NorthCarolinaLotteryScraper;
use App\Services\Scrapers\SouthCarolinaLotteryScraper;
use App\Services\Scrapers\TexasLotteryScraper;
use App\Services\Scrapers\WestVirginiaLotteryScraper;
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
            new ArkansasLotteryScraper(),
            new CaliforniaLotteryScraper(),
            new ConnecticutLotteryScraper(),
            new IndianaLotteryScraper(),
            new KansasLotteryScraper(),
            new KentuckyLotteryScraper(),
            new LouisianaLotteryScraper(),
            new MississippiLotteryScraper(),
            new MichiganLotteryScraper(),
            new MinnesotaLotteryScraper(),
            new NewJerseyLotteryScraper(),
            new NorthCarolinaLotteryScraper(),
            new SouthCarolinaLotteryScraper(),
            new TexasLotteryScraper(),
            new WestVirginiaLotteryScraper(),
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