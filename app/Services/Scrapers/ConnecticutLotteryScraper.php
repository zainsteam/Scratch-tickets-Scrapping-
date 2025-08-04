<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class ConnecticutLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'ctlottery.org');
    }

    public function getSiteName(): string
    {
        return 'Connecticut Lottery';
    }

    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [];
        
        try {
            // Extract title
            $titleNode = $crawler->filter('h1, h2, .game-title, .ticket-title');
            $info['title'] = $titleNode->count() ? trim($titleNode->text()) : null;

            // Extract game number
            $gameNoNode = $crawler->filter('.game-number, .ticket-number');
            $info['game_no'] = $gameNoNode->count() ? trim($gameNoNode->text()) : null;

            // Extract price
            $priceNode = $crawler->filter('.price, .ticket-price, .game-price');
            $info['price'] = $priceNode->count() ? trim($priceNode->text()) : null;

            // Extract start date
            $startDateNode = $crawler->filter('.start-date, .release-date');
            $info['start_date'] = $startDateNode->count() ? trim($startDateNode->text()) : null;

            // Extract end date
            $endDateNode = $crawler->filter('.end-date, .claim-deadline');
            $info['end_date'] = $endDateNode->count() ? trim($endDateNode->text()) : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract basic info from Connecticut Lottery: ' . $e->getMessage());
        }
        
        return $info;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        
        try {
            // Extract overall odds
            $oddsNode = $crawler->filter('.overall-odds, .total-odds');
            $odds['overall_odds'] = $oddsNode->count() ? trim($oddsNode->text()) : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from Connecticut Lottery: ' . $e->getMessage());
        }
        
        return $odds;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Extract image
            $imageNode = $crawler->filter('.ticket-image img, .game-image img');
            return $imageNode->count() ? $imageNode->attr('src') : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract image from Connecticut Lottery: ' . $e->getMessage());
            return null;
        }
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'Connecticut Lottery'
            ];

            // Extract title (Connecticut specific)
            $titleNode = $crawler->filter('h1, h2, .game-title, .ticket-title, strong');
            $data['title'] = $titleNode->count() ? trim($titleNode->text()) : null;

            // Extract image (Connecticut specific)
            $imageNode = $crawler->filter('.ticket-image img, .game-image img, img[src*="thumb"]');
            $data['image'] = $imageNode->count() ? $imageNode->attr('src') : null;

            // Extract price (Connecticut specific)
            $priceNode = $crawler->filter('.price, .ticket-price, .game-price, *:contains("Ticket Price:")');
            $data['price'] = $priceNode->count() ? trim($priceNode->text()) : null;

            // Extract game number (Connecticut specific)
            $gameNoNode = $crawler->filter('.game-number, .ticket-number, *:contains("Game #:")');
            $data['game_no'] = $gameNoNode->count() ? trim($gameNoNode->text()) : null;

            // Extract start date (Connecticut specific)
            $startDateNode = $crawler->filter('.start-date, .release-date, *:contains("Start Date:")');
            $data['start_date'] = $startDateNode->count() ? trim($startDateNode->text()) : null;

            // Extract end date (Connecticut specific)
            $endDateNode = $crawler->filter('.end-date, .claim-deadline, *:contains("End Date:")');
            $data['end_date'] = $endDateNode->count() ? trim($endDateNode->text()) : null;

            // Extract prizes (Connecticut specific)
            $data['prizes'] = $this->extractPrizes($crawler);

            // Extract odds (Connecticut specific)
            $oddsNode = $crawler->filter('.overall-odds, .total-odds, *:contains("Overall Odds:")');
            $data['odds'] = $oddsNode->count() ? trim($oddsNode->text()) : null;

            return $data;

        } catch (\Exception $e) {
            Log::error('Connecticut Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Connecticut Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        
        try {
            // Connecticut specific: Look for prize information in text
            $text = $crawler->text();
            
            // Extract top prize information
            if (preg_match('/Top Prize:\s*\$([0-9,]+)\s*\(([0-9]+)\)/', $text, $matches)) {
                $amount = $matches[1];
                $total = (int) $matches[2];
                $remaining = $total; // Assume all remaining for top prize
                
                $prizes[] = [
                    'amount' => $amount,
                    'total' => $total,
                    'remaining' => $remaining,
                    'paid' => 0
                ];
            }
            
            // Look for prize table rows (fallback)
            $prizeRows = $crawler->filter('table tbody tr');
            
            foreach ($prizeRows as $row) {
                $rowCrawler = new Crawler($row);
                $cells = $rowCrawler->filter('td');
                
                if ($cells->count() >= 3) {
                    $amount = trim($cells->eq(0)->text());
                    $total = trim($cells->eq(1)->text());
                    $remaining = trim($cells->eq(2)->text());
                    
                    // Clean numeric values
                    $amount = preg_replace('/[^0-9,]/', '', $amount);
                    $total = (int) preg_replace('/[^0-9]/', '', $total);
                    $remaining = (int) preg_replace('/[^0-9]/', '', $remaining);
                    
                    if ($amount && $total > 0) {
                        $prizes[] = [
                            'amount' => $amount,
                            'total' => $total,
                            'remaining' => $remaining,
                            'paid' => $total - $remaining
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from Connecticut Lottery: ' . $e->getMessage());
        }
        
        return $prizes;
    }
} 