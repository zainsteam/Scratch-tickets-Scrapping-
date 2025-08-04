<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class IndianaLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'hoosierlottery.com');
    }

    public function getSiteName(): string
    {
        return 'Indiana Lottery';
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
            Log::error('Failed to extract basic info from Indiana Lottery: ' . $e->getMessage());
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
            Log::error('Failed to extract odds from Indiana Lottery: ' . $e->getMessage());
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
            Log::error('Failed to extract image from Indiana Lottery: ' . $e->getMessage());
            return null;
        }
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'Indiana Lottery'
            ];

            // Extract title (Indiana specific)
            $titleNode = $crawler->filter('h1, h2, .game-title, .ticket-title, *:contains("COLOSSAL CASH")');
            $data['title'] = $titleNode->count() ? trim($titleNode->text()) : null;

            // Extract image (Indiana specific)
            $imageNode = $crawler->filter('.ticket-image img, .game-image img, img[src*="game"]');
            $data['image'] = $imageNode->count() ? $imageNode->attr('src') : null;

            // Extract price (Indiana specific)
            $priceNode = $crawler->filter('.price, .ticket-price, .game-price, *:contains("Ticket Price:")');
            $data['price'] = $priceNode->count() ? trim($priceNode->text()) : null;

            // Extract game number (Indiana specific)
            $gameNoNode = $crawler->filter('.game-number, .ticket-number, *:contains("Game #")');
            $data['game_no'] = $gameNoNode->count() ? trim($gameNoNode->text()) : null;

            // Extract start date (Indiana specific)
            $startDateNode = $crawler->filter('.start-date, .release-date, *:contains("Sale Date:")');
            $data['start_date'] = $startDateNode->count() ? trim($startDateNode->text()) : null;

            // Extract end date (Indiana specific)
            $endDateNode = $crawler->filter('.end-date, .claim-deadline, *:contains("Reorder Date:")');
            $data['end_date'] = $endDateNode->count() ? trim($endDateNode->text()) : null;

            // Extract prizes (Indiana specific)
            $data['prizes'] = $this->extractPrizes($crawler);

            // Extract odds (Indiana specific)
            $oddsNode = $crawler->filter('.overall-odds, .total-odds, *:contains("Estimated Overall Odds:")');
            $data['odds'] = $oddsNode->count() ? trim($oddsNode->text()) : null;

            return $data;

        } catch (\Exception $e) {
            Log::error('Indiana Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Indiana Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        
        try {
            // Indiana specific: Look for prize table
            $prizeRows = $crawler->filter('table tbody tr');
            
            foreach ($prizeRows as $row) {
                $rowCrawler = new Crawler($row);
                $cells = $rowCrawler->filter('td');
                
                if ($cells->count() >= 3) {
                    $amount = trim($cells->eq(0)->text());
                    $unclaimed = trim($cells->eq(1)->text());
                    $total = trim($cells->eq(2)->text());
                    
                    // Clean numeric values
                    $amount = preg_replace('/[^0-9,]/', '', $amount);
                    $unclaimed = (int) preg_replace('/[^0-9]/', '', $unclaimed);
                    $total = (int) preg_replace('/[^0-9]/', '', $total);
                    
                    if ($amount && $total > 0) {
                        $prizes[] = [
                            'amount' => $amount,
                            'total' => $total,
                            'remaining' => $unclaimed,
                            'paid' => $total - $unclaimed
                        ];
                    }
                }
            }
            
            // If no table found, try to extract from text
            if (empty($prizes)) {
                $text = $crawler->text();
                
                // Extract top prize information
                if (preg_match('/Top Prize:\s*\$([0-9,]+)/', $text, $matches)) {
                    $amount = $matches[1];
                    $prizes[] = [
                        'amount' => $amount,
                        'total' => 1,
                        'remaining' => 1,
                        'paid' => 0
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from Indiana Lottery: ' . $e->getMessage());
        }
        
        return $prizes;
    }
} 