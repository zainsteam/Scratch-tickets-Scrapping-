<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class TexasLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'texaslottery.com');
    }

    
    public function getSiteName(): string
    {
        return 'Texas Lottery';
    }

    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [
            'title' => null,
            'price' => null,
            'game_no' => null,
            'start_date' => null,
            'end_date' => null,
            'top_grand_prize' => null,
        ];
        
        try {
            // Title from h2: "Game No. 2400 - $20 Million Supreme"
            $titleNode = $crawler->filter('h2');
            if ($titleNode->count()) {
                $titleText = trim($titleNode->text());
                $info['title'] = $titleText;
                
                // Extract game number from title
                if (preg_match('/Game No\.\s*(\d+)/', $titleText, $matches)) {
                    $info['game_no'] = $matches[1];
                }
            }

            // Price from the price image alt text or infer from title
            $priceImage = $crawler->filter('img[src*="scratch_price"]');
            if ($priceImage->count()) {
                $altText = $priceImage->attr('alt');
                if (preg_match('/\$(\d+)/', $altText, $matches)) {
                    $info['price'] = '$' . $matches[1];
                }
            }
            
            // If no price from image, try to extract from title
            if (!$info['price'] && $info['title']) {
                if (preg_match('/\$(\d+)\s*Million/', $info['title'], $matches)) {
                    $info['price'] = '$100'; // Texas $100 games are typically $100
                }
            }

            // Top grand prize from the highlighted text
            $topPrizeNode = $crawler->filter('div[style*="text-transform:uppercase"]');
            if ($topPrizeNode->count()) {
                $topPrizeText = trim($topPrizeNode->text());
                if (preg_match('/\$([0-9,]+)/', $topPrizeText, $matches)) {
                    $info['top_grand_prize'] = '$' . $matches[1];
                }
            }

            // Extract date from "Scratch Ticket Prizes Claimed as of" text
            $allParagraphs = $crawler->filter('p');
            foreach ($allParagraphs as $paragraph) {
                $text = trim($paragraph->textContent);
                if (preg_match('/Scratch Ticket Prizes Claimed as of\s+([A-Za-z]+\s+\d{1,2},\s+\d{4})/i', $text, $matches)) {
                    $info['last_updated'] = $matches[1];
                    // Use this as a fallback for start_date if no other start date is found
                    if (!$info['start_date']) {
                        $info['start_date'] = $matches[1];
                    }
                    break;
                }
            }
            
            // Texas doesn't clearly show start/end dates in the fixture
            if (!$info['start_date']) {
                $info['start_date'] = null;
            }
            $info['end_date'] = null;
            
        } catch (\Exception $e) {
            Log::error('Failed to extract basic info from Texas Lottery: ' . $e->getMessage());
        }
        
        return $info;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        
        try {
            // Overall odds: "Overall odds of winning any prize in $20 Million Supreme are 1 in 3.49"
            $allParagraphs = $crawler->filter('p');
            $oddsText = null;
            
            foreach ($allParagraphs as $paragraph) {
                $text = trim($paragraph->textContent);
                if (str_contains($text, 'Overall odds of winning any prize')) {
                    $oddsText = $text;
                    break;
                }
            }
            
            if ($oddsText) {
                if (preg_match('/1\s*in\s*([0-9.]+)/', $oddsText, $matches)) {
                    $odds['overall_odds'] = '1 in ' . $matches[1];
                    
                    // Calculate probability
                    $value = (float) $matches[1];
                    $odds['probability'] = $value > 0 ? (1 / $value) * 100 : null;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from Texas Lottery: ' . $e->getMessage());
        }
        
        return $odds;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Texas uses tabs with Front/Back images
            $frontImage = $crawler->filter('#Front img');
            if ($frontImage->count()) {
                $src = $frontImage->attr('src');
                if ($src) {
                    // Convert relative URL to absolute
                    if (str_starts_with($src, '/')) {
                        return 'https://www.texaslottery.com' . $src;
                    }
                    return $src;
                }
            }
            
            // Fallback to any scratchoff image
            $scratchoffImage = $crawler->filter('img[src*="scratchoffs"]');
            if ($scratchoffImage->count()) {
                $src = $scratchoffImage->first()->attr('src');
                if ($src && str_starts_with($src, '/')) {
                    return 'https://www.texaslottery.com' . $src;
                }
                return $src;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to extract image from Texas Lottery: ' . $e->getMessage());
        }
        
        return null;
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'Texas Lottery'
            ];

            // Title from h2: "Game No. 2400 - $20 Million Supreme"
            $titleNode = $crawler->filter('h2');
            if ($titleNode->count()) {
                $titleText = trim($titleNode->text());
                $data['title'] = $titleText;
                
                // Extract game number from title
                if (preg_match('/Game No\.\s*(\d+)/', $titleText, $matches)) {
                    $data['game_no'] = $matches[1];
                }
            }

            // Image from tabs
            $data['image'] = $this->extractImage($crawler);

            // Price from the price image alt text or infer from title
            $priceImage = $crawler->filter('img[src*="scratch_price"]');
            if ($priceImage->count()) {
                $altText = $priceImage->attr('alt');
                if (preg_match('/\$(\d+)/', $altText, $matches)) {
                    $data['price'] = '$' . $matches[1];
                }
            }
            
            // If no price from image, try to extract from title
            if (!$data['price'] && $data['title']) {
                if (preg_match('/\$(\d+)\s*Million/', $data['title'], $matches)) {
                    $data['price'] = '$100'; // Texas $100 games are typically $100
                }
            }

            // Extract basic info including dates
            $basicInfo = $this->extractBasicInfo($crawler);
            $data = array_merge($data, $basicInfo);

            // Extract prizes
            $data['prizes'] = $this->extractPrizes($crawler);

            // Extract odds
            $oddsData = $this->extractOdds($crawler);
            $data['odds'] = $oddsData['overall_odds'] ?? null;
            $data['probability'] = $oddsData['probability'] ?? null;

            // Top grand prize from the highlighted text
            $topPrizeNode = $crawler->filter('div[style*="text-transform:uppercase"]');
            if ($topPrizeNode->count()) {
                $topPrizeText = trim($topPrizeNode->text());
                if (preg_match('/\$([0-9,]+)/', $topPrizeText, $matches)) {
                    $data['top_grand_prize'] = '$' . $matches[1];
                }
            }

            // Site name
            $data['site'] = $this->getSiteName();

            // Aggregate prize stats
            $data['initial_prizes'] = array_sum(array_column($data['prizes'], 'total'));
            $data['remaining_prizes'] = array_sum(array_column($data['prizes'], 'remaining'));

            return $data;

        } catch (\Exception $e) {
            Log::error('Texas Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Texas Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        
        try {
            // Texas uses table.large-only with specific structure
            $prizeTable = $crawler->filter('table.large-only');
            if ($prizeTable->count()) {
                $prizeTable->filter('tbody tr')->each(function (Crawler $row) use (&$prizes) {
                    $cells = $row->filter('td');
                    
                    if ($cells->count() >= 3) {
                        $amountText = trim($cells->eq(0)->text());
                        $totalText = trim($cells->eq(1)->text());
                        $claimedText = trim($cells->eq(2)->text());
                        
                        // Clean amount (remove $ and commas)
                        $amount = preg_replace('/[^0-9]/', '', $amountText);
                        
                        // Clean total (remove any non-numeric)
                        $total = (int) preg_replace('/[^0-9]/', '', $totalText);
                        
                        // Clean claimed (remove any non-numeric)
                        $claimed = (int) preg_replace('/[^0-9]/', '', $claimedText);
                        
                        // Calculate remaining
                        $remaining = max(0, $total - $claimed);
                        
                        if ($amount && $total > 0) {
                            $prizes[] = [
                                'amount' => $amount,
                                'total' => $total,
                                'remaining' => $remaining,
                                'paid' => $claimed
                            ];
                        }
                    }
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from Texas Lottery: ' . $e->getMessage());
        }
        
        return $prizes;
    }
}