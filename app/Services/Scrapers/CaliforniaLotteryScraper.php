<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class CaliforniaLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'calottery.com');
    }

    public function getSiteName(): string
    {
        return 'California Lottery';
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
            Log::error('Failed to extract basic info from California Lottery: ' . $e->getMessage());
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
            // Primary selector for CA overall odds block
            $container = $crawler->filter('.scratchers-game-detail__info-feature-item--overall-odds');
            if ($container->count()) {
                $strongs = $container->filter('strong');
                if ($strongs->count() >= 2) {
                    $value = trim($strongs->eq(1)->text(''));
                    $value = preg_replace('/[^0-9.]/', '', $value);
                    if ($value !== '') {
                        $result['odds'] = '1:' . $value;
                        $oddsValue = floatval($value);
                        $result['probability'] = $oddsValue > 0 ? (1 / $oddsValue) * 100 : null;
                        Log::info('California odds extracted (primary strongs)', [
                            'odds' => $result['odds'],
                            'probability' => $result['probability']
                        ]);
                        return $result;
                    }
                }

                // Fallback: parse from text like "Overall odds: 1 in 2.71"
                $text = $container->text('');
                if ($text && preg_match('/1\s*in\s*([0-9.]+)/i', $text, $m)) {
                    $result['odds'] = '1:' . $m[1];
                    $oddsValue = floatval($m[1]);
                    $result['probability'] = $oddsValue > 0 ? (1 / $oddsValue) * 100 : null;
                    Log::info('California odds extracted (primary text)', [
                        'text' => $text,
                        'odds' => $result['odds'],
                        'probability' => $result['probability']
                    ]);
                    return $result;
                }
            }

            // Secondary fallbacks
            $fallbackNode = $crawler->filter('.overall-odds, .total-odds');
            if ($fallbackNode->count()) {
                $text = trim($fallbackNode->text());
                if ($text && preg_match('/1\s*[:in]\s*([0-9.]+)/i', $text, $m)) {
                    $result['odds'] = '1:' . $m[1];
                    $oddsValue = floatval($m[1]);
                    $result['probability'] = $oddsValue > 0 ? (1 / $oddsValue) * 100 : null;
                    Log::info('California odds extracted (fallback)', [
                        'text' => $text,
                        'odds' => $result['odds'],
                        'probability' => $result['probability']
                    ]);
                } else {
                    $result['odds'] = $text;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from California Lottery: ' . $e->getMessage());
        }
        
        return $result;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Extract image
            $imageNode = $crawler->filter('.ticket-image img, .game-image img');
            return $imageNode->count() ? $imageNode->attr('src') : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract image from California Lottery: ' . $e->getMessage());
            return null;
        }
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'California Lottery'
            ];

            // Extract title
            $titleNode = $crawler->filter('h1, .game-title, .ticket-title');
            $data['title'] = $titleNode->count() ? trim($titleNode->text()) : null;

            // Extract image
            $imageNode = $crawler->filter('.scratchers-game-detail__card-img');
            $data['image'] = $imageNode->count() ? $imageNode->attr('src') : null;

            // Extract price
            $priceNode = $crawler->filter('.price, .scratchers-game-detail__info-price strong');
            $data['price'] = $priceNode->count() ? trim($priceNode->text()) : null;

            // Extract game number
            $gameNoNode = $crawler->filter('.scratchers-game-detail__info-feature-item--game-number strong');
            $data['game_no'] = $gameNoNode->count() ? trim($gameNoNode->text()) : null;

            // Extract start date
            $startDateNode = $crawler->filter('.start-date, .release-date');
            $data['start_date'] = $startDateNode->count() ? trim($startDateNode->text()) : null;

            // Extract end date
            $endDateNode = $crawler->filter('.end-date, .claim-deadline');
            $data['end_date'] = $endDateNode->count() ? trim($endDateNode->text()) : null;

            // Extract prizes
            $data['prizes'] = $this->extractPrizes($crawler);

            // Extract odds (normalize to 1:X)
            $oddsContainer = $crawler->filter('.scratchers-game-detail__info-feature-item--overall-odds');
            if ($oddsContainer->count()) {
                $strongs = $oddsContainer->filter('strong');
                if ($strongs->count() >= 2) {
                    $value = preg_replace('/[^0-9.]/', '', $strongs->eq(1)->text(''));
                    $data['odds'] = $value !== '' ? '1:' . $value : null;
                    $oddsValue = floatval($value);
                    $data['probability'] = $oddsValue > 0 ? (1 / $oddsValue) * 100 : null;
                    Log::info('California scrape(): odds via strongs', [
                        'odds' => $data['odds'],
                        'probability' => $data['probability']
                    ]);
                } else {
                    $text = $oddsContainer->text('');
                    if ($text && preg_match('/1\s*in\s*([0-9.]+)/i', $text, $m)) {
                        $data['odds'] = '1:' . $m[1];
                        $oddsValue = floatval($m[1]);
                        $data['probability'] = $oddsValue > 0 ? (1 / $oddsValue) * 100 : null;
                        Log::info('California scrape(): odds via text', [
                            'text' => $text,
                            'odds' => $data['odds'],
                            'probability' => $data['probability']
                        ]);
                    } else {
                        $data['odds'] = null;
                    }
                }
            } else {
                $oddsNode = $crawler->filter('.overall-odds, .total-odds');
                $text = $oddsNode->count() ? trim($oddsNode->text()) : null;
                if ($text && preg_match('/1\s*[:in]\s*([0-9.]+)/i', $text, $m)) {
                    $data['odds'] = '1:' . $m[1];
                    $oddsValue = floatval($m[1]);
                    $data['probability'] = $oddsValue > 0 ? (1 / $oddsValue) * 100 : null;
                    Log::info('California scrape(): odds via fallback', [
                        'text' => $text,
                        'odds' => $data['odds'],
                        'probability' => $data['probability']
                    ]);
                } else {
                    $data['odds'] = $text;
                }
            }

            // Calculate additional fields needed for ROI
            $data['initial_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'total'))
                : 0;
            $data['remaining_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'remaining'))
                : 0;

            // Summary log for full California data
            Log::info('California scrape() summary', [
                'url' => $data['url'] ?? null,
                'title' => $data['title'] ?? null,
                'image' => $data['image'] ?? null,
                'price' => $data['price'] ?? null,
                'game_no' => $data['game_no'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'odds' => $data['odds'] ?? null,
                'probability' => $data['probability'] ?? null,
                'initial_prizes' => $data['initial_prizes'] ?? null,
                'remaining_prizes' => $data['remaining_prizes'] ?? null,
                'prizes_count' => is_array($data['prizes']) ? count($data['prizes']) : 0,
                'first_prize' => $data['prizes'][0] ?? null,
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('California Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape California Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        
        try {
            // Target each prize row inside the California prizes table
            $crawler->filter('.odds-available-prizes__table tr.odds-available-prizes__table__body')
                ->each(function (Crawler $row) use (&$prizes) {
                    $cells = $row->filter('td');
                    if ($cells->count() < 3) {
                        return;
                    }

                    // 1) Prize amount (e.g., "$10,000,000")
                    $amountText = trim($cells->eq(0)->text(''));
                    $amount = preg_replace('/[^0-9,]/', '', $amountText);

                    // 2) Odds column exists but we don't use it for prize counts here
                    // $oddsText = trim($cells->eq(1)->text(''));

                    // 3) Remaining column formatted as "X of Y" with spans
                    $remainingCellText = trim($cells->eq(2)->text(''));

                    $remaining = null;
                    $total = null;

                    // Prefer regex: handles spaces/spans in-between
                    if (preg_match('/(\d[\d,]*)\s*of\s*(\d[\d,]*)/i', $remainingCellText, $m)) {
                        $remaining = (int) str_replace(',', '', $m[1]);
                        $total = (int) str_replace(',', '', $m[2]);
                    } else {
                        // Fallback: use spans if available
                        $spans = $cells->eq(2)->filter('span');
                        if ($spans->count() >= 2) {
                            $remaining = (int) str_replace(',', '', trim($spans->eq(0)->text('')));
                            $total = (int) str_replace(',', '', trim($spans->eq($spans->count() - 1)->text('')));
                        }
                    }

                    if ($amount && is_int($total) && $total > 0 && is_int($remaining)) {
                        $prizes[] = [
                            'amount' => $amount,
                            'total' => $total,
                            'remaining' => $remaining,
                            'paid' => max(0, $total - $remaining),
                        ];
                    }
                });
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from California Lottery: ' . $e->getMessage());
        }
        
        return $prizes;
    }
} 