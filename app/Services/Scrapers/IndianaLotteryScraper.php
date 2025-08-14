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
            // Primary: special Indiana scratch simulator image
            $simulator = $crawler->filter('#scratchSimulator');
            if ($simulator->count()) {
                $unscratched = $simulator->attr('data-unscratched');
                if (!empty($unscratched)) {
                    // Decode potential double-encoded entities
                    $url = html_entity_decode(html_entity_decode($unscratched, ENT_QUOTES), ENT_QUOTES);
                    // Normalize relative URLs
                    $absolute = $this->makeAbsoluteIndianaUrl($url);
                    return $this->stripSizeQueryParams($absolute);
                }
            }

            // Secondary: canvas background image in simulator
            $canvas = $crawler->filter('#scratchCanvas[style*="background-image"]');
            if ($canvas->count()) {
                $style = $canvas->attr('style') ?? '';
                if (preg_match('/background-image:\s*url\(([^)]+)\)/i', $style, $m)) {
                    $raw = trim($m[1], "'\"");
                    $raw = html_entity_decode(html_entity_decode($raw, ENT_QUOTES), ENT_QUOTES);
                    $absolute = $this->makeAbsoluteIndianaUrl($raw);
                    return $this->stripSizeQueryParams($absolute);
                }
            }

            // Fallbacks
            // 1) Hidden unscratched image element
            $imageNode = $crawler->filter('#unscratchedImage[src]');
            if ($imageNode->count()) {
                $src = $imageNode->attr('src');
                $src = html_entity_decode(html_entity_decode($src, ENT_QUOTES), ENT_QUOTES);
                $absolute = $this->makeAbsoluteIndianaUrl($src);
                return $this->stripSizeQueryParams($absolute);
            }

            // 2) Any getmedia image
            $imageNode = $crawler->filter('.ticket-image img, .game-image img, img[src*="getmedia/"]');
            if ($imageNode->count()) {
                $src = $imageNode->attr('src');
                $src = html_entity_decode(html_entity_decode($src, ENT_QUOTES), ENT_QUOTES);
                $absolute = $this->makeAbsoluteIndianaUrl($src);
                return $this->stripSizeQueryParams($absolute);
            }

            // 3) Open Graph image as a last resort
            $og = $crawler->filter('meta[property="og:image"]');
            if ($og->count()) {
                $src = $og->attr('content');
                $src = html_entity_decode(html_entity_decode($src, ENT_QUOTES), ENT_QUOTES);
                $absolute = $this->makeAbsoluteIndianaUrl($src);
                return $this->stripSizeQueryParams($absolute);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract image from Indiana Lottery: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert relative/Protocol-relative URLs to absolute for hoosierlottery.com
     */
    private function makeAbsoluteIndianaUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return $trimmed;
        }
        if (str_starts_with($trimmed, '//')) {
            return 'https:' . $trimmed;
        }
        if (str_starts_with($trimmed, '/')) {
            return 'https://hoosierlottery.com' . $trimmed;
        }
        return $trimmed;
    }

    /**
     * Remove width/height query params from image URL, keep others (e.g., ext)
     */
    private function stripSizeQueryParams(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'hoosierlottery.com';
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';
        if ($query !== '') {
            parse_str($query, $qs);
            unset($qs['width'], $qs['height']);
            $query = http_build_query($qs);
        }
        $rebuilt = $scheme . '://' . $host . $path;
        if ($query !== '') {
            $rebuilt .= '?' . $query;
        }
        return $rebuilt;
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'Indiana Lottery'
            ];

            // Extract title (Indiana specific)
            $titleNode = $crawler->filter('h1, h2, .game-title, .ticket-title');
            $data['title'] = $titleNode->count() ? trim($titleNode->text()) : null;

            // Extract image (Indiana specific)
            $data['image'] = $this->extractImage($crawler);

            // Extract price (Indiana specific)
            // Prefer explicit ticket price heading, fallback to generic selectors
            $priceText = null;
            $priceHeading = $crawler->filter('h2:contains("Ticket Price:")');
            if ($priceHeading->count()) {
                $priceText = trim($priceHeading->text(''));
            } else {
                $priceNode = $crawler->filter('.price, .ticket-price, .game-price');
                $priceText = $priceNode->count() ? trim($priceNode->text()) : null;
            }
            $data['price'] = $priceText;

            // Extract game number (Indiana specific)
            $gameNoText = null;
            $gameNoNode = $crawler->filter('.game-identification, .game-number, .ticket-number');
            if ($gameNoNode->count()) {
                $gameNoText = trim($gameNoNode->text(''));
            } else {
                $gameNoContains = $crawler->filter('*:contains("Game #")');
                $gameNoText = $gameNoContains->count() ? trim($gameNoContains->text('')) : null;
            }
            // Normalize to digits only (e.g., "Game #2522" -> "2522")
            if (!empty($gameNoText) && preg_match('/(\d+)/', $gameNoText, $m)) {
                $data['game_no'] = $m[1];
            } else {
                $data['game_no'] = $gameNoText;
            }

            // Extract start date (Indiana specific) - e.g., "Sale Date: 3/5/2024"
            $startDateText = null;
            $startDateNode = $crawler->filter('ul.ticket-price-dates li:contains("Sale Date:")');
            if ($startDateNode->count()) {
                $startDateText = trim($startDateNode->text(''));
            } else {
                $startDateGeneric = $crawler->filter('.start-date, .release-date, *:contains("Sale Date:")');
                $startDateText = $startDateGeneric->count() ? trim($startDateGeneric->text('')) : null;
            }
            if (!empty($startDateText) && preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $startDateText, $m)) {
                $data['start_date'] = $m[1];
            } else {
                $data['start_date'] = null;
            }

            // Indiana pages often show "Reorder Date" not claim deadline; keep end_date null to avoid incorrect expiry
            $data['end_date'] = null;

            // Extract prizes (Indiana specific)
            $data['prizes'] = $this->extractPrizes($crawler);

            // Extract odds (Indiana specific) - normalize to 1:X and compute probability
            $data['odds'] = null;
            $data['probability'] = null;
            // Try the hero header area first
            $oddsHeader = $crawler->filter('h2:contains("Odds:")');
            if ($oddsHeader->count()) {
                $text = $oddsHeader->text('');
                if ($text && preg_match('/1\s*in\s*([0-9.]+)/i', $text, $m)) {
                    $data['odds'] = '1:' . $m[1];
                    $val = floatval($m[1]);
                    $data['probability'] = $val > 0 ? (1 / $val) * 100 : null;
                }
            }
            // Fallback: paragraph with strong text
            if (empty($data['odds'])) {
                $oddsStrong = $crawler->filter('p strong:contains("Estimated Overall Odds")');
                if ($oddsStrong->count()) {
                    $text = $oddsStrong->text('');
                    if ($text && preg_match('/1\s*in\s*([0-9.]+)/i', $text, $m)) {
                        $data['odds'] = '1:' . $m[1];
                        $val = floatval($m[1]);
                        $data['probability'] = $val > 0 ? (1 / $val) * 100 : null;
                    }
                }
            }
            // Last fallback: any element containing the phrase
            if (empty($data['odds'])) {
                $anyOdds = $crawler->filter('*:contains("Overall Odds")');
                if ($anyOdds->count()) {
                    $text = $anyOdds->first()->text('');
                    if ($text && preg_match('/1\s*in\s*([0-9.]+)/i', $text, $m)) {
                        $data['odds'] = '1:' . $m[1];
                        $val = floatval($m[1]);
                        $data['probability'] = $val > 0 ? (1 / $val) * 100 : null;
                    }
                }
            }

            // Aggregate initial and remaining prizes for ROI calculations
            $data['initial_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'total'))
                : 0;
            $data['remaining_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'remaining'))
                : 0;

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