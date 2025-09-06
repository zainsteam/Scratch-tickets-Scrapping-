<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class MichiganLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'michiganlottery.com');
    }

    public function getSiteName(): string
    {
        return 'Michigan Lottery';
    }

    /**
     * One-shot scrape method for Michigan Lottery
     */
    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url'   => $url,
                'state' => $this->getSiteName(),
            ];

            // Debug: Log what HTML we're working with
            $htmlContent = $crawler->html();
            Log::info('MI Scraper: HTML content length', ['length' => strlen($htmlContent)]);
            Log::info('MI Scraper: HTML preview', ['preview' => substr($htmlContent, 0, 500)]);

            // Title (MI specific)
            $data['title'] = $this->extractTitle($crawler);

            // Image (MI specific)
            $data['image'] = $this->extractImage($crawler);

            // Basic info (MI specific)
            $basic = $this->extractBasicInfo($crawler, $url);
            $data = array_merge($data, $basic);

            // Odds & probability (MI specific)
            $odds = $this->extractOdds($crawler);
            $data['odds'] = $odds['overall_odds'] ?? null;
            $data['probability'] = $odds['probability'] ?? null;

            // Prizes (MI specific)
            $data['prizes'] = $this->extractPrizes($crawler);

            // Aggregates for ROI calculation
            $data['initial_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'total'))
                : 0;
            $data['remaining_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'remaining'))
                : 0;

            Log::info('MI Scraper: Final data assembled', [
                'title' => $data['title'], 
                'game_no' => $data['game_no'], 
                'price' => $data['price'], 
                'odds' => $data['odds'],
                'prizes_count' => count($data['prizes'])
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('Michigan Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Michigan Lottery data', 'url' => $url];
        }
    }

    /**
     * Extracts the primary title from the page
     */
    public function extractTitle(Crawler $crawler): ?string
    {
        try {
            // Look for the main title in the landing page
            $titleNode = $crawler->filter('#landing-title');
            if ($titleNode->count()) {
                $title = trim($titleNode->text());
                Log::info('MI Scraper: Title found via #landing-title', ['title' => $title]);
                return $title;
            }

            // Fallback to page title
            $pageTitle = $crawler->filter('title');
            if ($pageTitle->count()) {
                $title = trim($pageTitle->text());
                // Extract just the game name from the full title
                if (preg_match('/^([^-]+)/', $title, $matches)) {
                    $title = trim($matches[1]);
                    Log::info('MI Scraper: Title extracted from page title', ['title' => $title]);
                    return $title;
                }
            }

            Log::warning('MI Scraper: Title not found');
        } catch (\Exception $e) {
            Log::error('MI Scraper: Failed to extract title: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Extracts all basic info needed for ROI calculations
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
            // Price from the game-info-price div
            $priceNode = $crawler->filter('.game-info-price');
            if ($priceNode->count()) {
                $priceText = trim($priceNode->text());
                if (preg_match('/Price:\s*\$([0-9.]+)/', $priceText, $m)) {
                    $info['price'] = $m[1];
                    Log::info('MI Scraper: Price extracted', ['price' => $info['price']]);
                }
            }

            // Game number from page content
            $info['game_no'] = $this->extractGameNumberFromPage($crawler);

            // Top prize from the instant-game-info-top-prize-value
            $topPrizeNode = $crawler->filter('.instant-game-info-top-prize-value');
            if ($topPrizeNode->count()) {
                $info['top_prize'] = trim($topPrizeNode->text());
                Log::info('MI Scraper: Top prize extracted', ['top_prize' => $info['top_prize']]);
            }

            // Start/End dates - Michigan doesn't seem to show these prominently
            $info['start_date'] = null;
            $info['end_date'] = null;

        } catch (\Exception $e) {
            Log::error('MI Scraper: Failed to extract basic info: ' . $e->getMessage());
        }

        return $info;
    }

    /**
     * Extract game number from page content
     */
    private function extractGameNumberFromPage(Crawler $crawler): ?string
    {
        // Try to find game number in any text on the page
        // Michigan URLs typically have format: /games/0633-instore-instant-50000-gold-rush
        $allText = $crawler->text('');
        if (preg_match('/Game\s*#?\s*(\d+)/i', $allText, $matches)) {
            $gameNo = $matches[1];
            Log::info('MI Scraper: Game number found in page content', ['game_no' => $gameNo]);
            return $gameNo;
        }
        
        Log::warning('MI Scraper: Could not extract game number from page content');
        return null;
    }

    /**
     * Extract overall odds and compute probability (%)
     */
    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        $oddsValue = null;
        $prob = null;

        try {
            // Look for the game-info-odds div
            $oddsNode = $crawler->filter('.game-info-odds');
            if ($oddsNode->count()) {
                $text = trim($oddsNode->text());
                if (preg_match('/Overall Odds:\s*1\s*in\s*([0-9.]+)/i', $text, $m)) {
                    $oddsValue = '1:' . $m[1];
                    $val = (float) $m[1];
                    $prob = $val > 0 ? (1 / $val) * 100 : null;
                    Log::info('MI Scraper: Odds extracted from .game-info-odds', ['odds' => $oddsValue, 'probability' => $prob]);
                }
            }

            // If still empty, try scanning full text
            if (!$oddsValue) {
                $all = $crawler->text('');
                if (preg_match('/Overall\s+Odds[^0-9]*1\s*in\s*([0-9.]+)/i', $all, $m)) {
                    $oddsValue = '1:' . $m[1];
                    $val  = (float) $m[1];
                    $prob = $val > 0 ? (1 / $val) * 100 : null;
                    Log::info('MI Scraper: Odds extracted from full text', ['odds' => $oddsValue, 'probability' => $prob]);
                }
            }

            if (!$oddsValue) {
                Log::warning('MI Scraper: No odds found on page');
            }

            // Return in the format expected by the interface
            $odds['overall_odds'] = $oddsValue;
            $odds['probability'] = $prob;

        } catch (\Exception $e) {
            Log::error('MI Scraper: Failed to extract odds: ' . $e->getMessage());
        }

        return $odds;
    }

    /**
     * Extracts the main ticket image
     */
    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Look for the game card logo image
            $img = $crawler->filter('.game-card-logo-image');
            if ($img->count()) {
                $src = $img->attr('src');
                if (!empty($src) && stripos($src, 'data:image') === false) {
                    Log::info('MI Scraper: Image found via .game-card-logo-image', ['src' => $src]);
                    return trim($src);
                }
            }

            // Fallback to any image in the game card container
            $fallback = $crawler->filter('.game-card-container img, .game-card-logo-image-container img')->first();
            if ($fallback->count()) {
                $src = $fallback->attr('src');
                if (!empty($src) && stripos($src, 'data:image') === false) {
                    Log::info('MI Scraper: Fallback image found', ['src' => $src]);
                    return trim($src);
                }
            }

            // Last resort: Open Graph image
            $og = $crawler->filter('meta[property="og:image"]');
            if ($og->count()) {
                $url = $og->attr('content');
                if (!empty($url)) {
                    Log::info('MI Scraper: Image via og:image', ['src' => $url]);
                    return $url;
                }
            }

            Log::warning('MI Scraper: No valid image found on page');
        } catch (\Exception $e) {
            Log::error('MI Scraper: Failed to extract image: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Parse prize table rows from the payout-table
     */
    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];

        try {
            // Look for the specific payout table with class "payout-table"
            $table = $crawler->filter('table.payout-table');
            if (!$table->count()) {
                Log::warning('MI Scraper: No payout table found');
                return $prizes;
            }

            $table->filter('tbody tr')->each(function (Crawler $row) use (&$prizes) {
                // Skip the total row
                if ($row->hasClass('total-row')) {
                    return;
                }

                $cols = $row->filter('td');
                if ($cols->count() >= 3) {
                    $amountText = trim($cols->eq(0)->text(''));
                    $remainingText = trim($cols->eq(1)->text(''));
                    $startText = trim($cols->eq(2)->text(''));

                    // Clean and parse the values
                    $amount = preg_replace('/[^0-9.]/', '', $amountText);
                    $remaining = (int) preg_replace('/[^0-9]/', '', $remainingText);
                    $start = (int) preg_replace('/[^0-9]/', '', $startText);

                    // Calculate total (start) and paid (start - remaining)
                    $total = $start;
                    $paid = $start - $remaining;

                    if ($amount !== '' && $total > 0) {
                        $rowData = [
                            'amount'    => '$' . $amount,
                            'total'     => $total,
                            'remaining' => $remaining,
                            'paid'      => $paid,
                            'column1'   => 0, // Will be calculated by the ROI service
                        ];
                        $prizes[] = $rowData;
                        Log::info('MI Scraper: Prize row parsed', $rowData);
                    }
                }
            });

            Log::info('MI Scraper: Prize rows parsed', ['count' => count($prizes)]);
        } catch (\Exception $e) {
            Log::error('MI Scraper: Failed to extract prizes: ' . $e->getMessage());
        }

        return $prizes;
    }
}
