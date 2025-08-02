<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

interface BaseScraper
{
    /**
     * Extract basic ticket information from the page
     */
    public function extractBasicInfo(Crawler $crawler): array;
    
    /**
     * Extract prize information from the page
     */
    public function extractPrizes(Crawler $crawler): array;
    
    /**
     * Extract odds and probability information
     */
    public function extractOdds(Crawler $crawler): array;
    
    /**
     * Extract image URL
     */
    public function extractImage(Crawler $crawler): ?string;
    
    /**
     * Get the site name/identifier
     */
    public function getSiteName(): string;
    
    /**
     * Check if this scraper can handle the given URL
     */
    public function canHandle(string $url): bool;
} 