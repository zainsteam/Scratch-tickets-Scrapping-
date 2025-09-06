<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class MinnesotaLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'mnlottery.com');
    }

    public function getSiteName(): string
    {
        return 'Minnesota Lottery';
    }

    /**
     * One-shot scrape method for Minnesota Lottery
     */
    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url'   => $url,
                'state' => $this->getSiteName(),
            ];

            // Title
            $data['title'] = $this->extractTitle($crawler);

            
            // Image
            $data['image'] = $this->extractImage($crawler);

            // Basic info
            $basic = $this->extractBasicInfo($crawler);
            $data = array_merge($data, $basic);

            // Prizes
            $data['prizes'] = $this->extractPrizes($crawler);

            // Odds & probability
            $oddsData = $this->extractOdds($crawler);
            $data['odds'] = $oddsData['overall_odds'];
            $data['probability'] = $oddsData['probability'];

            // Aggregates for ROI calculation
            $data['initial_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'total'))
                : 0;
            $data['remaining_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'remaining'))
                : 0;

            Log::info('MN Scraper: Final data assembled', [
                'title' => $data['title'], 
                'game_no' => $data['game_no'], 
                'price' => $data['price'], 
                'odds' => $data['odds'],
                'prizes_count' => count($data['prizes'])
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('Minnesota Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Minnesota Lottery data', 'url' => $url];
        }
    }

    /**
     * Extracts the game title
     */
    public function extractTitle(Crawler $crawler): ?string
    {
        try {
            // Look for the main title
            $titleNode = $crawler->filter('h1');
            if ($titleNode->count()) {
                $title = trim($titleNode->text());
                Log::info('MN Scraper: Title found', ['title' => $title]);
                return $title;
            }

            Log::warning('MN Scraper: Title not found');
        } catch (\Exception $e) {
            Log::error('MN Scraper: Failed to extract title: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Extracts basic game information
     */
    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [
            'price'      => null,
            'game_no'    => null,
            'start_date' => null,
            'end_date'   => null,
            'top_prize'  => null,
        ];

        try {
            // Extract price from the smallest prize amount instead of title
            // This is more reliable than parsing the title
            $info['price'] = $this->extractPriceFromSmallestPrize($crawler);

            // Extract top prize from the page content
            $topPrizeText = $crawler->filter('h2:contains("TOP PRIZE:")');
            if ($topPrizeText->count()) {
                $text = trim($topPrizeText->text());
                if (preg_match('/TOP PRIZE:\s*\$([0-9,]+)/', $text, $matches)) {
                    $info['top_prize'] = str_replace(',', '', $matches[1]);
                    Log::info('MN Scraper: Top prize extracted', ['top_prize' => $info['top_prize']]);
                }
            }

            // Extract end date if game has ended
            $endDateText = $crawler->filter('p:contains("This game ended on")');
            if ($endDateText->count()) {
                $text = trim($endDateText->text());
                if (preg_match('/ended on\s+(\d{2}\/\d{2}\/\d{4})/', $text, $matches)) {
                    $info['end_date'] = $matches[1];
                    Log::info('MN Scraper: End date extracted', ['end_date' => $info['end_date']]);
                }
            }

        } catch (\Exception $e) {
            Log::error('MN Scraper: Failed to extract basic info: ' . $e->getMessage());
        }

        return $info;
    }

    /**
     * Extract price from the smallest prize amount in the prize table
     */
    private function extractPriceFromSmallestPrize(Crawler $crawler): ?string
    {
        try {
            $table = $crawler->filter('table');
            if ($table->count()) {
                $smallestAmount = null;
                
                $table->filter('tbody tr')->each(function (Crawler $row) use (&$smallestAmount) {
                    $cols = $row->filter('td');
                    if ($cols->count() >= 1) {
                        $amountText = trim($cols->eq(0)->text(''));
                        $amount = preg_replace('/[^0-9.]/', '', $amountText);
                        
                        if ($amount !== '' && is_numeric($amount)) {
                            $amountValue = (float) $amount;
                            if ($smallestAmount === null || $amountValue < $smallestAmount) {
                                $smallestAmount = $amountValue;
                            }
                        }
                    }
                });
                
                if ($smallestAmount !== null) {
                    Log::info('MN Scraper: Price extracted from smallest prize', ['price' => $smallestAmount]);
                    return (string) $smallestAmount;
                }
            }
        } catch (\Exception $e) {
            Log::error('MN Scraper: Failed to extract price from smallest prize: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Extracts odds and calculates probability
     */
    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        $oddsValue = null;
        $prob = null;

        try {
            // Look for overall odds in the text
            $allText = $crawler->text('');
            if (preg_match('/overall ticket odds of winning are\s+1\s+in\s+([0-9.]+)/i', $allText, $matches)) {
                $oddsValue = '1:' . $matches[1];
                $val = (float) $matches[1];
                $prob = $val > 0 ? (1 / $val) * 100 : null;
                Log::info('MN Scraper: Overall odds extracted', ['odds' => $oddsValue, 'probability' => $prob]);
            }

            if (!$oddsValue) {
                Log::warning('MN Scraper: No overall odds found');
            }

            $odds['overall_odds'] = $oddsValue;
            $odds['probability'] = $prob;

        } catch (\Exception $e) {
            Log::error('MN Scraper: Failed to extract odds: ' . $e->getMessage());
        }

        return $odds;
    }

    /**
     * Extracts prize table data
     */
    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];

        try {
            // Look for the prize table
            $table = $crawler->filter('table');
            if ($table->count()) {
                $table->filter('tbody tr')->each(function (Crawler $row) use (&$prizes) {
                    $cols = $row->filter('td');
                    if ($cols->count() >= 3) {
                        $amountText = trim($cols->eq(0)->text(''));
                        $oddsText = trim($cols->eq(1)->text(''));
                        $quantityText = trim($cols->eq(2)->text(''));

                        // Clean and parse the values
                        $amount = preg_replace('/[^0-9.]/', '', $amountText);
                        $quantity = (int) preg_replace('/[^0-9]/', '', $quantityText);

                        if ($amount !== '' && $quantity > 0) {
                            $rowData = [
                                'amount'    => '$' . $amount,
                                'total'     => $quantity,
                                'remaining' => $quantity, // Assuming all prizes are available
                                'paid'      => 0, // No paid prizes info available
                                'odds'      => $oddsText,
                            ];
                            $prizes[] = $rowData;
                            Log::info('MN Scraper: Prize row parsed', $rowData);
                        }
                    }
                });

                Log::info('MN Scraper: Prize rows parsed', ['count' => count($prizes)]);
            } else {
                Log::warning('MN Scraper: No prize table found');
            }

        } catch (\Exception $e) {
            Log::error('MN Scraper: Failed to extract prizes: ' . $e->getMessage());
        }

        return $prizes;
    }

    /**
     * Extracts the game image
     */
    public function extractImage(Crawler $crawler): ?string
    {
        try {
            Log::info('MN Scraper: Starting image extraction');
            
            // First priority: Look for scratch ticket images with specific alt text
            $scratchTicketImg = $crawler->filter('img[alt*="Scratch Ticket"], img[alt*="Scratch Game"]');
            Log::info('MN Scraper: scratch ticket img count', ['count' => $scratchTicketImg->count()]);
            
            if ($scratchTicketImg->count()) {
                $src = $scratchTicketImg->attr('src');
                Log::info('MN Scraper: Scratch ticket image src found', ['src' => $src]);
                if (!empty($src)) {
                    Log::info('MN Scraper: Scratch ticket image found', ['src' => $src]);
                    return trim($src);
                }
            }

            // Second priority: Look for images in Full-Ticket-Images folder (actual ticket images)
            $ticketImages = $crawler->filter('img[src*="Full-Ticket-Images"]');
            Log::info('MN Scraper: Full-Ticket-Images count', ['count' => $ticketImages->count()]);
            
            if ($ticketImages->count()) {
                $src = $ticketImages->attr('src');
                Log::info('MN Scraper: Full-Ticket-Images src found', ['src' => $src]);
                if (!empty($src)) {
                    Log::info('MN Scraper: Full-Ticket-Images image found', ['src' => $src]);
                    return trim($src);
                }
            }

            // Third priority: Look for the game image in the figure tag (avoid promotional images)
            $figureImgs = $crawler->filter('figure img');
            Log::info('MN Scraper: figure img count', ['count' => $figureImgs->count()]);
            
            if ($figureImgs->count() > 1) {
                // If multiple images, try to find the one that's NOT promotional
                foreach ($figureImgs as $index => $img) {
                    $imgCrawler = new Crawler($img);
                    $src = $imgCrawler->attr('src');
                    $alt = $imgCrawler->attr('alt');
                    
                    // Skip promotional/2nd chance images
                    if (strpos($src, '2nd-Chance') !== false || strpos($alt, '2nd Chance') !== false) {
                        Log::info('MN Scraper: Skipping promotional image', ['src' => $src, 'alt' => $alt]);
                        continue;
                    }
                    
                    // Prefer scratch ticket images
                    if (strpos($alt, 'Scratch') !== false || strpos($src, 'Full-Ticket-Images') !== false) {
                        Log::info('MN Scraper: Found non-promotional scratch ticket image', ['src' => $src, 'alt' => $alt]);
                        return trim($src);
                    }
                }
            } elseif ($figureImgs->count() === 1) {
                $src = $figureImgs->attr('src');
                Log::info('MN Scraper: Single figure image src found', ['src' => $src]);
                if (!empty($src)) {
                    Log::info('MN Scraper: Single figure image found', ['src' => $src]);
                    return trim($src);
                }
            }

            // Fallback to Open Graph image
            $og = $crawler->filter('meta[property="og:image"]');
            Log::info('MN Scraper: og:image count', ['count' => $og->count()]);
            
            if ($og->count()) {
                $url = $og->attr('content');
                if (!empty($url)) {
                    Log::info('MN Scraper: Image via og:image', ['src' => $url]);
                    return $url;
                }
            }

            // Last resort: Twitter image
            $twitter = $crawler->filter('meta[name="twitter:image"]');
            Log::info('MN Scraper: twitter:image count', ['count' => $twitter->count()]);
            
            if ($twitter->count()) {
                $url = $twitter->attr('content');
                if (!empty($url)) {
                    Log::info('MN Scraper: Image via twitter:image', ['src' => $url]);
                    return $url;
                }
            }

            Log::warning('MN Scraper: No suitable game image found');
        } catch (\Exception $e) {
            Log::error('MN Scraper: Failed to extract image: ' . $e->getMessage());
        }

        return null;
    }
}
