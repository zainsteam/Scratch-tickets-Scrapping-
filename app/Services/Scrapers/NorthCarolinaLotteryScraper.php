<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class NorthCarolinaLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'nclottery.com');
    }

    
    public function getSiteName(): string
    {
        return 'North Carolina Lottery';
    }

    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [];
        
        try {
            // Extract title from the span.title element
            $titleNode = $crawler->filter('span.title');
            if ($titleNode->count()) {
                $titleText = trim($titleNode->text());
                // Remove the game number from title
                $titleText = preg_replace('/\s*#\d+\s*$/', '', $titleText);
                $info['title'] = $titleText;
                
                // Extract game number from the span inside title
                $gameNoNode = $crawler->filter('span.title span');
                if ($gameNoNode->count()) {
                    $gameNoText = trim($gameNoNode->text());
                    if (preg_match('/#(\d+)/', $gameNoText, $matches)) {
                        $info['game_no'] = $matches[1];
                    }
                }
            }

            // Extract ticket price from the price.value element
            $priceNode = $crawler->filter('span.price.value');
            if ($priceNode->count()) {
                $info['price'] = trim($priceNode->text());
            }

            // Extract release date (start date) from status.value
            $statusNode = $crawler->filter('span.status.value');
            if ($statusNode->count()) {
                $statusText = trim($statusNode->text());
                if (preg_match('/Released\s+([A-Za-z]+\s+\d{1,2},\s+\d{4})/i', $statusText, $matches)) {
                    $info['start_date'] = $matches[1];
                }
            }

            // Extract last updated date from table footer
            $footerText = $crawler->text('');
            if (preg_match('/through\s+([A-Za-z]+\s+\d{1,2},\s+\d{4})/i', $footerText, $matches)) {
                $info['last_updated'] = $matches[1];
            }

            // Extract top prize from topprize.value
            $topPrizeNode = $crawler->filter('span.topprize.value');
            if ($topPrizeNode->count()) {
                $info['top_grand_prize'] = trim($topPrizeNode->text());
            }

        } catch (\Exception $e) {
            Log::error('Failed to extract basic info from North Carolina Lottery: ' . $e->getMessage());
        }
        
        return $info;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        
        try {
            // Extract overall odds from odds.value element
            $oddsNode = $crawler->filter('span.odds.value');
            if ($oddsNode->count()) {
                $oddsText = trim($oddsNode->text());
                if (preg_match('/1\s*in\s*([0-9.]+)/i', $oddsText, $matches)) {
                    $odds['overall_odds'] = $oddsText;
                    $odds['odds'] = '1:' . $matches[1];
                    
                    // Calculate probability
                    $value = floatval($matches[1]);
                    $odds['probability'] = $value > 0 ? (1 / $value) * 100 : null;
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from North Carolina Lottery: ' . $e->getMessage());
        }
        
        return $odds;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Look for the game image in the thmb div
            $imageNode = $crawler->filter('.thmb img');
            if ($imageNode->count()) {
                $src = $imageNode->attr('src');
                if ($src) {
                    // Convert relative URL to absolute
                    if (str_starts_with($src, '/')) {
                        return 'https://nclottery.com' . $src;
                    }
                    return $src;
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to extract image from North Carolina Lottery: ' . $e->getMessage());
        }
        
        return null;
    }

    public function extractHowToPlay(Crawler $crawler): ?string
    {
        try {
            // Extract how to play from the howtowin.value element
            $howToPlayNode = $crawler->filter('p.howtowin.value');
            if ($howToPlayNode->count()) {
                return trim($howToPlayNode->text());
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to extract how to play from North Carolina Lottery: ' . $e->getMessage());
        }
        
        return null;
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'North Carolina Lottery',
                'site_name' => $this->getSiteName(),
            ];

            $basic = $this->extractBasicInfo($crawler);
            $data = array_merge($data, $basic);

            $data['image'] = $this->extractImage($crawler);
            $data['prizes'] = $this->extractPrizes($crawler);
            $data['how_to_play'] = $this->extractHowToPlay($crawler);

            // Calculate initial_prizes from the prizes array
            $data['initial_prizes'] = array_sum(array_column($data['prizes'], 'total'));

            $odds = $this->extractOdds($crawler);
            $data = array_merge($data, $odds);

            return $data;

        } catch (\Exception $e) {
            Log::error('North Carolina Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape North Carolina Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        
        try {
            // Find the prize table with the specific class
            $prizeTable = $crawler->filter('table.datatable.prizes');
            
            if ($prizeTable->count()) {
                $prizeTable->filter('tbody tr')->each(function (Crawler $row) use (&$prizes) {
                    $cells = $row->filter('td');
                    
                    if ($cells->count() >= 4) {
                        $amountText = trim($cells->eq(0)->text(''));
                        $oddsText = trim($cells->eq(1)->text(''));
                        $totalText = trim($cells->eq(2)->text(''));
                        $remainingText = trim($cells->eq(3)->text(''));
                        
                        // Skip header rows or empty rows
                        if (empty($amountText) || $amountText === 'Value' || !str_starts_with($amountText, '$')) {
                            return;
                        }
                        
                        // Clean and parse values
                        $amount = $amountText;
                        $total = (int) preg_replace('/[^0-9]/', '', $totalText);
                        $remaining = (int) preg_replace('/[^0-9]/', '', $remainingText);
                        
                        if ($amount && $total > 0) {
                            // Calculate column1 based on Excel formula
                            $ratio = $total > 0 ? $remaining / $total : 0;
                            
                            // Excel formula: IF(OR(E29>2,E29=C29,E29÷C29>0.5),B29×E29,IF(OR(E29=1,E29=2,E29÷C29<0.5),0,B29×E29))
                            if ($remaining > 2 || $remaining === $total || $ratio > 0.5) {
                                $column1 = floatval(preg_replace('/[^0-9.]/', '', $amount)) * $remaining;
                            } elseif ($remaining === 1 || $remaining === 2 || $ratio < 0.5) {
                                $column1 = 0;
                            } else {
                                $column1 = floatval(preg_replace('/[^0-9.]/', '', $amount)) * $remaining;
                            }
                            
                            $paid = max(0, $total - $remaining);
                            $entry = [
                                'amount' => $amount,                                    // Prize Amount By Prize Level
                                'total' => $total,                                     // Number of Prizes at Start of Game
                                'remaining' => $remaining,                             // Estimated Number of Unclaimed Prizes
                                'paid' => $paid,                                       // Paid
                                'column1' => round($column1, 2)                        // Column1
                            ];
                            $prizes[] = $entry;
                        }
                    }
                });
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from North Carolina Lottery: ' . $e->getMessage());
        }
        
        return $prizes;
    }
}