<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class LouisianaLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'louisianalottery.com');
    }

    
    public function getSiteName(): string
    {
        return 'Louisiana Lottery';
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
            Log::error('Failed to extract basic info from Louisiana Lottery: ' . $e->getMessage());
        }
        
        return $info;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $result = [
            'odds' => null,
            'probability' => null,
        ];

        try {
            // Louisiana exposes Overall Odds in a description list: <dt>Overall Odds</dt><dd>1:3.08</dd>
            $overallDt = $crawler->filter('dt:contains("Overall Odds")');
            if ($overallDt->count()) {
                $dd = $overallDt->first()->nextAll()->filter('dd')->first();
                $text = $dd->count() ? trim($dd->text('')) : '';
                if ($text) {
                    // Normalize formats like 1:3.08 or 1 in 3.08
                    if (preg_match('/1\s*[:in]\s*([0-9.]+)/i', $text, $m)) {
                        $result['odds'] = '1:' . $m[1];
                        $val = floatval($m[1]);
                        $result['probability'] = $val > 0 ? (1 / $val) * 100 : null;
                    } else {
                        $result['odds'] = $text;
                    }
                    return $result;
                }
            }

            // Fallback: any text containing Overall Odds
            $any = $crawler->filter('*:contains("Overall Odds")');
            if ($any->count()) {
                $text = $any->first()->text('');
                if ($text && preg_match('/1\s*[:in]\s*([0-9.]+)/i', $text, $m)) {
                    $result['odds'] = '1:' . $m[1];
                    $val = floatval($m[1]);
                    $result['probability'] = $val > 0 ? (1 / $val) * 100 : null;
                } else {
                    $result['odds'] = trim($text);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from Louisiana Lottery: ' . $e->getMessage());
        }
        
        return $result;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // 1) Prefer gallery active panel image (Front)
            $activePanel = $crawler->filter('.tabs__panels__panel[aria-hidden="false"] img');
            if ($activePanel->count()) {
                return $activePanel->attr('src');
            }

            // 2) Fallback to first gallery panel image
            $firstPanel = $crawler->filter('.tabs__panels__panel img')->first();
            if ($firstPanel->count()) {
                return $firstPanel->attr('src');
            }

            // 3) Prefer the hero media image shown on the game page
            $hero = $crawler->filter('.hero__media img');
            if ($hero->count()) {
                return $hero->attr('src');
            }

            // 4) Alternative specific patterns for scratch-off title card
            $titleCard = $crawler->filter('img[src*="/scratch-offs/"][src*="Title-Card"]');
            if ($titleCard->count()) {
                return $titleCard->attr('src');
            }

            // 5) Try on-page generic ticket/game image
            $imageNode = $crawler->filter('.ticket-image img, .game-image img, img[alt="Game Image"], img[src*="uploads/"]');
            if ($imageNode->count()) {
                return $imageNode->attr('src');
            }
            // Fallback to Open Graph image
            $og = $crawler->filter('meta[property="og:image"]');
            if ($og->count()) {
                return $og->attr('content');
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract image from Louisiana Lottery: ' . $e->getMessage());
            return null;
        }
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'Louisiana Lottery'
            ];

            // Extract title
            $titleNode = $crawler->filter('h1, .game-title, .ticket-title');
            $data['title'] = $titleNode->count() ? trim($titleNode->text()) : null;
            if (empty($data['title'])) {
                // Fallback to og:title
                $ogTitle = $crawler->filter('meta[property="og:title"]');
                $data['title'] = $ogTitle->count() ? trim($ogTitle->attr('content')) : null;
            }

            // Extract image
            $data['image'] = $this->extractImage($crawler);

            // Extract price (dt/dd or inline Ticket Price span)
            $price = null;
            $priceDt = $crawler->filter('dt:contains("Ticket Price")');
            if ($priceDt->count()) {
                $price = $priceDt->first()->nextAll()->filter('dd')->first()->text('');
            }
            if (empty($price)) {
                $inlinePrice = $crawler->filter('span:contains("Ticket Price") em');
                if ($inlinePrice->count()) {
                    $price = $inlinePrice->first()->text('');
                }
            }
            $data['price'] = $price ? trim($price) : null;

            // Extract game number (from title like "1581 - 100x")
            $gameNo = null;
            $titleForGame = $data['title'] ?? '';
            if ($titleForGame && preg_match('/(\d{3,})/', $titleForGame, $m)) {
                $gameNo = $m[1];
            }
            if (!$gameNo) {
                $canonical = $crawler->filter('link[rel="canonical"]');
                $href = $canonical->count() ? $canonical->attr('href') : '';
                if ($href && preg_match('#/game/(\d+)-#', $href, $m)) {
                    $gameNo = $m[1];
                }
            }
            $data['game_no'] = $gameNo;

            // Start date (Launch Date)
            $data['start_date'] = null;
            $launchNode = $crawler->filter('li:contains("Launch Date:") time[datetime]');
            if ($launchNode->count()) {
                $iso = trim($launchNode->attr('datetime'));
                if ($iso) {
                    try {
                        $dt = new \DateTime($iso);
                        $data['start_date'] = $dt->format('m/d/Y');
                    } catch (\Exception $e) {
                        $data['start_date'] = null;
                    }
                }
            }

            // End date (claim deadline) if present in closed notice
            $data['end_date'] = null;
            $closedP = $crawler->filter('p:contains("All winning tickets must be claimed by")');
            if ($closedP->count()) {
                $text = $closedP->text('');
                if (preg_match('/claimed by\s+([A-Za-z]{3,}\s+\d{1,2},\s+\d{4})/i', $text, $m)) {
                    try {
                        $dt = new \DateTime($m[1]);
                        $data['end_date'] = $dt->format('m/d/Y');
                    } catch (\Exception $e) {
                        $data['end_date'] = null;
                    }
                }
            }

            // Extract prizes
            $data['prizes'] = $this->extractPrizes($crawler);

            // Extract odds (normalize) and probability
            $odds = $this->extractOdds($crawler);
            $data['odds'] = $odds['odds'] ?? null;
            $data['probability'] = $odds['probability'] ?? null;

            // Aggregate prize counts
            $data['initial_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'total'))
                : 0;
            $data['remaining_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'remaining'))
                : 0;

            return $data;

        } catch (\Exception $e) {
            Log::error('Louisiana Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Louisiana Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
{
    $prizes = [];
    
    try {
        // Target the Odds & Prizes table with known headers
        $tables = $crawler->filter('table');
        $tables->each(function (Crawler $table) use (&$prizes) {
            $headers = $table->filter('thead th');
            if ($headers->count() < 5) {
                return;
            }
            $h0 = trim(strtolower($headers->eq(0)->text('')));
            $h1 = trim(strtolower($headers->eq(1)->text('')));
            $h2 = trim(strtolower($headers->eq(2)->text('')));
            $h3 = trim(strtolower($headers->eq(3)->text('')));
            $h4 = trim(strtolower($headers->eq(4)->text('')));
            
            if (
                str_contains($h0, 'tier') && 
                str_contains($h1, 'odds') && 
                str_contains($h2, 'total') && 
                str_contains($h3, 'claimed') && 
                str_contains($h4, 'remaining')
            ) {
                $table->filter('tbody tr')->each(function (Crawler $row) use (&$prizes) {
                    $cells = $row->filter('td');
                    if ($cells->count() >= 5) {
                        $amountText    = trim($cells->eq(0)->text(''));
                        $totalText     = trim($cells->eq(2)->text(''));
                        $claimedText   = trim($cells->eq(3)->text(''));
                        $remainingText = trim($cells->eq(4)->text(''));

                        // --- Normalize amount ---
                        $cleanedAmount = str_replace([',', '$'], '', $amountText);
                        if (is_numeric($cleanedAmount)) {
                            $amount = (int)$cleanedAmount;
                        } else {
                            $amount = 1; // Replace non-numeric with 1
                        }

                        $total     = (int) str_replace(',', '', preg_replace('/[^0-9,]/', '', $totalText));
                        $claimed   = (int) str_replace(',', '', preg_replace('/[^0-9,]/', '', $claimedText));
                        $remaining = (int) str_replace(',', '', preg_replace('/[^0-9,]/', '', $remainingText));

                        if ($amount && $total > 0) {
                            $prizes[] = [
                                'amount'    => $amount,
                                'total'     => $total,
                                'remaining' => $remaining,
                                'paid'      => $claimed > 0 ? $claimed : max(0, $total - $remaining),
                            ];
                        }
                    }
                });
            }
        });
    } catch (\Exception $e) {
        Log::error('Failed to extract prizes from Louisiana Lottery: ' . $e->getMessage());
    }
    
    return $prizes;
}


    
}