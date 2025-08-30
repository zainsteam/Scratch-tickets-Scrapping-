<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class MississippiLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'mslottery.com');
    }

    public function getSiteName(): string
    {
        return 'Mississippi Lottery';
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url'   => $url,
                'state' => 'Mississippi Lottery'
            ];

            // Title
            $data['title'] = $this->extractTitle($crawler);

            // Basic Info
            $basic = $this->extractBasicInfo($crawler);
            $data = array_merge($data, $basic);

            // Prizes
            $data['prizes'] = $this->extractPrizes($crawler);

            // Initial / Remaining prize tickets count (raw numbers)
            $data['initial_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'total'))
                : 0;

            $data['remaining_prizes'] = is_array($data['prizes']) && !empty($data['prizes'])
                ? array_sum(array_column($data['prizes'], 'remaining'))
                : 0;

            // Odds / probability
            $oddsData = $this->extractOdds($crawler);
            $data['odds']        = $oddsData['odds'];
            $data['probability'] = $oddsData['probability'];

            // Image
            $data['image'] = $this->extractImage($crawler);

            return $data;

        } catch (\Exception $e) {
            Log::error('Mississippi Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Mississippi Lottery data', 'url' => $url];
        }
    }

    private function extractTitle(Crawler $crawler): ?string
    {
        try {
            $node = $crawler->filter('h1.entry-title');
            return $node->count() ? trim($node->text()) : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract title (MS): ' . $e->getMessage());
            return null;
        }
    }

    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [
            'price' => null,
            'top_prize' => null,
            'odds' => null,
            'launch_date' => null,
            'game_no' => null,
            'game_status' => null,
            'last_updated' => null,
        ];
    
        try {
            $table = $crawler->filter('.gameinfo table.juxtable');
            if ($table->count()) {
                $rows = $table->filter('tr');
                foreach ($rows as $row) {
                    $cells = (new Crawler($row))->filter('td');
                    if ($cells->count() === 2) {
                        $keyRaw = $cells->eq(0)->text();
                        $value = trim($cells->eq(1)->text());
    
                        Log::info("MS Scraper: Table row key=[$keyRaw], value=[$value]");
    
                        $key = strtolower(trim($keyRaw));
    
                        if (preg_match('/ticket\s*price/i', $key)) {
                            $info['price'] = preg_replace('/[^0-9.]/', '', $value);
                        } elseif (preg_match('/top\s*prize/i', $key)) {
                            $info['top_prize'] = $value;
                        } elseif (preg_match('/overall\s*odds/i', $key)) {
                            $info['odds'] = $value;
                        } elseif (preg_match('/launch/i', $key)) {
                            $info['start_date'] = $value;
                        } elseif (preg_match('/game\s*number/i', $key)) {
                            $info['game_no'] = $value;
                        } elseif (preg_match('/status/i', $key)) {
                            $info['game_status'] = $value;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract basic info (MS): ' . $e->getMessage());
        }
    
        return $info;
    }
    

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        try {
            $tables = $crawler->filter('h4:contains("Game Prize Info") + figure table, figure.wp-block-table table');
            if ($tables->count()) {
                $rows = $tables->filter('tbody tr');
                foreach ($rows as $row) {
                    $cells = (new Crawler($row))->filter('td, th');
                    if ($cells->count() >= 3) {
                        $amount    = trim($cells->eq(0)->text());
                        $total     = (int) preg_replace('/[^0-9]/', '', $cells->eq(1)->text());
                        $remaining = (int) preg_replace('/[^0-9]/', '', $cells->eq(2)->text());

                        if ($amount !== '' && ($total > 0 || $remaining > 0)) {
                            $prizes[] = [
                                'amount'    => $amount,
                                'total'     => $total,
                                'remaining' => $remaining,
                                'paid'      => max($total - $remaining, 0),
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes (MS): ' . $e->getMessage());
        }

        return $prizes;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $odds = null;
        $probability = null;

        try {
            $table = $crawler->filter('.gameinfo table.juxtable');
            if ($table->count()) {
                $rows = $table->filter('tr');
                foreach ($rows as $row) {
                    $cells = (new Crawler($row))->filter('td');
                    if ($cells->count() === 2) {
                        $key   = trim($cells->eq(0)->text());
                        $value = trim($cells->eq(1)->text());
                        if (strtolower($key) === 'overall odds') {
                            if (preg_match('/1\s*:\s*([0-9.]+)/', $value, $m)) {
                                $odds = "1:" . $m[1];
                                $f = (float) $m[1]; // e.g. 3.50
                                $probability = $f > 0 ? (1 / $f) * 100 : null;
                            }
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract odds (MS): ' . $e->getMessage());
        }

        return ['odds' => $odds, 'probability' => $probability];
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // First try <source> inside <picture>
            $source = $crawler->filter('.flexslider picture source')->first();
            if ($source->count()) {
                $srcset = $source->attr('data-srcset') ?: $source->attr('srcset');
                if (!empty($srcset)) {
                    $parts = explode(',', $srcset);
                    $url = trim(explode(' ', $parts[0])[0]);
                    Log::info("MS Scraper: Found image in <source>: " . $url);
                    return $url;
                }
            } else {
                Log::info("MS Scraper: No <source> found inside flexslider picture.");
            }
    
            // Then try <img> inside flexslider
            $carousel = $crawler->filter('.flexslider img')->first();
            if ($carousel->count()) {
                $url = $carousel->attr('data-src') ?: $carousel->attr('src');
                if (!empty($url) && strpos($url, 'data:image') === false) {
                    Log::info("MS Scraper: Found usable <img>: " . $url);
                    return $url;
                }
                Log::info("MS Scraper: Ignored placeholder <img> src: " . $url);
            }
    
            // Fallback: any image in entry-content
            $fallback = $crawler->filter('.entry-content img')->first();
            if ($fallback->count()) {
                $url = $fallback->attr('data-src') ?: $fallback->attr('src');
                if (!empty($url)) {
                    Log::info("MS Scraper: Using fallback entry-content image: " . $url);
                    return $url;
                }
            }
    
            Log::warning("MS Scraper: No valid image found for this page.");
        } catch (\Exception $e) {
            Log::error('Failed to extract image (MS): ' . $e->getMessage());
        }
    
        return null;
    }
    
    
    
    


}
