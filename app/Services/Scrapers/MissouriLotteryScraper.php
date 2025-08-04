<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class MissouriLotteryScraper implements BaseScraper
{
    public function extractBasicInfo(Crawler $crawler): array
    {
        $data = [];
        
        // Extract title - Missouri Lottery typically uses h1 or .game-title
        $data['title'] = $crawler->filter('h1, .game-title, .ticket-title')->count()
            ? trim($crawler->filter('h1, .game-title, .ticket-title')->first()->text())
            : null;
        
        // Extract price - Look for price in various formats
        $priceSelectors = [
            '.price, .ticket-price, .game-price',
            '.field--name-price .field__item',
            '.game-info .price',
            'span:contains("$")'
        ];
        
        $data['price'] = null;
        foreach ($priceSelectors as $selector) {
            $priceNode = $crawler->filter($selector);
            if ($priceNode->count() > 0) {
                $priceText = trim($priceNode->first()->text());
                $data['price'] = preg_replace('/[^0-9.]/', '', $priceText);
                break;
            }
        }
        
        // Extract game number
        $gameSelectors = [
            '.game-number, .ticket-number',
            '.field--name-game-number .field__item',
            'span:contains("Game")',
            '.game-info .number'
        ];
        
        $data['game_no'] = null;
        foreach ($gameSelectors as $selector) {
            $gameNode = $crawler->filter($selector);
            if ($gameNode->count() > 0) {
                $data['game_no'] = trim($gameNode->first()->text());
                break;
            }
        }
        
        // Extract start date
        $startDateSelectors = [
            '.start-date, .release-date',
            '.field--name-start-date .field__item time',
            '.game-info .start-date',
            'time[datetime]'
        ];
        
        $data['start_date'] = null;
        foreach ($startDateSelectors as $selector) {
            $dateNode = $crawler->filter($selector);
            if ($dateNode->count() > 0) {
                $data['start_date'] = $dateNode->first()->text('');
                break;
            }
        }
        
        // Extract end date (Last Date to Claim)
        $endDateSelectors = [
            '.end-date, .claim-deadline',
            '.field--name-end-date .field__item time',
            '.game-info .end-date',
            'time:contains("Claim")'
        ];
        
        $data['end_date'] = null;
        foreach ($endDateSelectors as $selector) {
            $endDateNode = $crawler->filter($selector);
            if ($endDateNode->count() > 0) {
                $data['end_date'] = $endDateNode->first()->text('');
                break;
            }
        }
        
        return $data;
    }
    
    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        $initialPrizes = 0;
        
        // Get the first prize table
        $targetTable = $this->getFirstTable($crawler);
        
        if ($targetTable && $targetTable->count() > 0) {
            $targetTable->filter('tbody tr')->each(function (Crawler $row) use (&$prizes, &$initialPrizes) {
                $cells = $row->filter('td');
                
                // Missouri Lottery typically has 4-5 columns: Prize, Total, Paid, Remaining, (sometimes Odds)
                if ($cells->count() >= 4) {
                    $amountText = trim($cells->eq(0)->text(), '$ ');
                    $amount = floatval(preg_replace('/[^0-9.]/', '', $amountText));
                    
                    $total = (int) filter_var($cells->eq(1)->text(), FILTER_SANITIZE_NUMBER_INT);
                    $paid = (int) filter_var($cells->eq(2)->text(), FILTER_SANITIZE_NUMBER_INT);
                    $remaining = (int) filter_var($cells->eq(3)->text(), FILTER_SANITIZE_NUMBER_INT);
                    
                    $initialPrizes += $total;
                    $column1 = ($remaining >= 3 || $remaining === $total) ? $amount * $remaining : 0;
                    
                    $prizes[] = [
                        'amount' => $amountText,
                        'total' => $total,
                        'paid' => $paid,
                        'remaining' => $remaining,
                        'column1' => round($column1, 2)
                    ];
                }
            });
        }
        
        return [
            'prizes' => $prizes,
            'initial_prizes' => $initialPrizes
        ];
    }
    
    /**
     * Get the first table from the page, avoiding multiple tables
     */
    private function getFirstTable(Crawler $crawler): ?Crawler
    {
        // Strategy 1: Get the first table with prize data
        $firstTable = $crawler->filter('table')->first();
        if ($firstTable->count() > 0) {
            return $firstTable;
        }
        
        // Strategy 2: Look for table with specific headers
        $tablesWithHeaders = $crawler->filter('table')->filter(function (Crawler $table) {
            return $table->filter('th, td')->count() > 0;
        });
        
        if ($tablesWithHeaders->count() > 0) {
            return $tablesWithHeaders->first();
        }
        
        // Strategy 3: Look for div-based prize structure
        $prizeDivs = $crawler->filter('.prize-table, .prizes, .prize-list');
        if ($prizeDivs->count() > 0) {
            return $prizeDivs->first();
        }
        
        return null;
    }
    
    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        
        // Extract overall odds
        $overallOddsSelectors = [
            '.overall-odds, .total-odds',
            '.field--name-odds .field__item',
            '.game-info .odds',
            'span:contains("Overall Odds")'
        ];
        
        foreach ($overallOddsSelectors as $selector) {
            $oddsNode = $crawler->filter($selector);
            if ($oddsNode->count() > 0) {
                $odds['overall_odds'] = trim($oddsNode->first()->text());
                break;
            }
        }
        
        // Extract top prize odds
        $topPrizeOddsSelectors = [
            '.top-prize-odds, .grand-prize-odds',
            '.field--name-top-prize-odds .field__item',
            '.game-info .top-odds',
            'span:contains("Top Prize Odds")'
        ];
        
        foreach ($topPrizeOddsSelectors as $selector) {
            $topOddsNode = $crawler->filter($selector);
            if ($topOddsNode->count() > 0) {
                $odds['top_prize_odds'] = trim($topOddsNode->first()->text());
                break;
            }
        }
        
        return $odds;
    }
    
    public function extractImage(Crawler $crawler): ?string
    {
        // Look for ticket image in various formats
        $imageSelectors = [
            '.ticket-image img, .game-image img',
            '.field--name-field-ticket-image img',
            '.game-info img',
            'img[src*="ticket"], img[src*="game"]',
            'img[alt*="ticket"], img[alt*="game"]'
        ];
        
        foreach ($imageSelectors as $selector) {
            $imageNode = $crawler->filter($selector);
            if ($imageNode->count() > 0) {
                $src = $imageNode->first()->attr('src');
                if ($src) {
                    // Convert relative URLs to absolute
                    if (strpos($src, 'http') !== 0) {
                        $src = 'https://www.molottery.com' . $src;
                    }
                    return $src;
                }
            }
        }
        
        return null;
    }
    
    public function getSiteName(): string
    {
        return 'Missouri Lottery';
    }
    
    public function canHandle(string $url): bool
    {
        // Check if URL is from Missouri Lottery
        $missouriDomains = [
            'molottery.com',
            'missourilottery.com',
            'www.molottery.com',
            'www.missourilottery.com'
        ];
        
        $urlHost = parse_url($url, PHP_URL_HOST);
        
        foreach ($missouriDomains as $domain) {
            if (strpos($urlHost, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
} 