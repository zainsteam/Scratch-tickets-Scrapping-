<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class NewJerseyLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'njlottery.com');
    }

    public function getSiteName(): string
    {
        return 'New Jersey Lottery';
    }

    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [];

        try {
            // Title (Game name)
            $titleNode = $crawler->filterXPath('//h2');
$title = $titleNode->count() ? trim($titleNode->text()) : null;

            // Game Number (look for "Game #" or "01780")
            $gameNoNode = $crawler->filter('*:contains("Game #"), .game-number, .ticket-number');
            $info['game_no'] = $gameNoNode->count() ? trim($gameNoNode->text()) : null;

            // Price ($1, $2, etc.)
            $priceNode = $crawler->filter('.price, .ticket-price, .game-price, *:contains("Price")');
            $info['price'] = $priceNode->count() ? trim($priceNode->text()) : null;

            // Start Date / Release Date
            $startDateNode = $crawler->filter('.start-date, .release-date, *:contains("Start Date"), *:contains("Sale Date")');
            $info['start_date'] = $startDateNode->count() ? trim($startDateNode->text()) : null;

            // End / Expiration Date
            $endDateNode = $crawler->filter('.end-date, .claim-deadline, *:contains("End Date"), *:contains("Claim By")');
            $info['end_date'] = $endDateNode->count() ? trim($endDateNode->text()) : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract basic info from New Jersey Lottery: ' . $e->getMessage());
        }

        return $info;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];

        try {
            // Overall Odds (usually "Overall odds: 1 in 3.45")
            $oddsNode = $crawler->filter('*:contains("Overall Odds"), .overall-odds, .total-odds');
            if ($oddsNode->count()) {
                $text = $oddsNode->first()->text();
                if (preg_match('/1\s*in\s*([0-9.]+)/i', $text, $m)) {
                    $odds['overall_odds'] = '1:' . $m[1];
                    $odds['probability'] = (floatval($m[1]) > 0) ? (1 / floatval($m[1])) * 100 : null;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from New Jersey Lottery: ' . $e->getMessage());
        }

        return $odds;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Main ticket image
            $imageNode = $crawler->filter('.ticket-image img, .game-image img, img[src*="content/dam/portal/images"]');
            if ($imageNode->count()) {
                $src = $imageNode->attr('src');
                return $this->makeAbsoluteNjUrl($src);
            }

            // Fallback: Open Graph image
            $og = $crawler->filter('meta[property="og:image"]');
            if ($og->count()) {
                return $this->makeAbsoluteNjUrl($og->attr('content'));
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract image from New Jersey Lottery: ' . $e->getMessage());
            return null;
        }
    }

    private function makeAbsoluteNjUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') return $trimmed;
        if (str_starts_with($trimmed, '//')) {
            return 'https:' . $trimmed;
        }
        if (str_starts_with($trimmed, '/')) {
            return 'https://www.njlottery.com' . $trimmed;
        }
        return $trimmed;
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];

        try {
            // NJ scratch-off prize tables
            $crawler->filter('table tbody tr')->each(function (Crawler $row) use (&$prizes) {
                $cells = $row->filter('td');
                if ($cells->count() >= 3) {
                    $amount = trim($cells->eq(0)->text());
                    $total = (int) preg_replace('/[^0-9]/', '', $cells->eq(1)->text());
                    $remaining = (int) preg_replace('/[^0-9]/', '', $cells->eq(2)->text());

                    if ($amount && $total > 0) {
                        $prizes[] = [
                            'amount' => preg_replace('/[^0-9,]/', '', $amount),
                            'total' => $total,
                            'remaining' => $remaining,
                            'paid' => $total - $remaining
                        ];
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from New Jersey Lottery: ' . $e->getMessage());
        }

        return $prizes;
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'New Jersey Lottery'
            ];

            // Info
            $basic = $this->extractBasicInfo($crawler);
            $data = array_merge($data, $basic);

            // Image
            $data['image'] = $this->extractImage($crawler);

            // Odds
            $odds = $this->extractOdds($crawler);
            $data = array_merge($data, $odds);

            // Prizes
            $data['prizes'] = $this->extractPrizes($crawler);

            // ROI aggregates
            $data['initial_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'total'))
                : 0;
            $data['remaining_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'remaining'))
                : 0;

            return $data;
        } catch (\Exception $e) {
            Log::error('New Jersey Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape New Jersey Lottery data', 'url' => $url];
        }
    }
}
