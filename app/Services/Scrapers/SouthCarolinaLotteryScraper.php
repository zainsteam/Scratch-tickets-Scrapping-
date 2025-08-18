<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class SouthCarolinaLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'sceducationlottery.com');
    }

    public function getSiteName(): string
    {
        return 'South Carolina Lottery';
    }

    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [];
        
        try {
            // Extract title from h1
            $titleNode = $crawler->filter('h1');
            $info['title'] = $titleNode->count() ? trim($titleNode->text()) : null;

            // Extract game number from span after h1 e.g. (GAME #1570)
            $gameNoNode = $crawler->filter('h1 + span');
            if ($gameNoNode->count()) {
                $gameText = trim($gameNoNode->text(''));
                if (preg_match('/GAME\s*#\s*(\d+)/i', $gameText, $m)) {
                    $info['game_no'] = $m[1];
                }
            }

            // Parse info blocks: Last Updated, Price, Start of Game, Last Day to Sell/Claim
            $infoBlocks = $crawler->filter('.info-block');
            foreach ($infoBlocks as $block) {
                $blockCrawler = new Crawler($block);
                $text = trim($blockCrawler->text(''));
                if ($text === '') {
                    continue;
                }
                if (str_contains($text, 'Last Updated')) {
                    if (preg_match('/Last Updated:\s*([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}\s+[0-9]{1,2}:[0-9]{2}\s+[AP]M)/i', $text, $m)) {
                        $info['last_updated'] = $m[1];
                    }
                } elseif (str_contains($text, 'Price:')) {
                    if (preg_match('/Price:\s*\$\s*([0-9]+)/i', $text, $m)) {
                        $info['price'] = '$' . $m[1];
                    }
                } elseif (str_contains($text, 'Start of Game')) {
                    if (preg_match('/Start of Game:\s*([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4})/i', $text, $m)) {
                        $info['start_date'] = $m[1];
                    }
                } elseif (str_contains($text, 'Last Day to Sell')) {
                    if (preg_match('/Last Day to Sell:\s*([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4})/i', $text, $m)) {
                        $info['last_day_to_sell'] = $m[1];
                    }
                } elseif (str_contains($text, 'Last Day to Claim')) {
                    if (preg_match('/Last Day to Claim:\s*([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4})/i', $text, $m)) {
                        $info['last_day_to_claim'] = $m[1];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract basic info from South Carolina Lottery: ' . $e->getMessage());
        }
        
        return $info;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        
        try {
            // Primary: bottom-links block containing odds lines
            $bottomLinks = $crawler->filter('.bottom-links');
            if ($bottomLinks->count()) {
                $text = $bottomLinks->text('');
                if (preg_match('/Overall Odds:\s*1\s*in\s*([0-9.,]+)/i', $text, $m)) {
                    $overall = str_replace(',', '', $m[1]);
                    $odds['overall_odds'] = '1 in ' . $m[1];
                    $odds['odds'] = '1:' . $overall;
                    $val = floatval($overall);
                    $odds['probability'] = $val > 0 ? (1 / $val) * 100 : null;
                }
                if (preg_match('/Top Prize Odds:\s*1\s*in\s*([0-9.,]+)/i', $text, $m2)) {
                    $top = str_replace(',', '', $m2[1]);
                    $odds['top_prize_odds'] = '1 in ' . $m2[1];
                    $odds['top_prize_odds_normalized'] = '1:' . $top;
                }
            }
            // Fallback: any text content
            if (empty($odds)) {
                $text = $crawler->text('');
                if (preg_match('/Overall Odds?:\s*1\s*in\s*([0-9.,]+)/i', $text, $m)) {
                    $overall = str_replace(',', '', $m[1]);
                    $odds['overall_odds'] = '1 in ' . $m[1];
                    $odds['odds'] = '1:' . $overall;
                    $val = floatval($overall);
                    $odds['probability'] = $val > 0 ? (1 / $val) * 100 : null;
                }
                if (preg_match('/Top Prize Odds?:\s*1\s*in\s*([0-9.,]+)/i', $text, $m2)) {
                    $top = str_replace(',', '', $m2[1]);
                    $odds['top_prize_odds'] = '1 in ' . $m2[1];
                    $odds['top_prize_odds_normalized'] = '1:' . $top;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from South Carolina Lottery: ' . $e->getMessage());
        }
        
        return $odds;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Primary: InstantGameUncover image
            $imageNode = $crawler->filter('#InstantGameUncover');
            if ($imageNode->count()) {
                $src = $imageNode->attr('src');
                if ($src && !str_starts_with($src, 'http')) {
                    $src = 'https://www.sceducationlottery.com' . $src;
                }
                return $src;
            }
            // Fallback: any instantgames image
            $fallbackImage = $crawler->filter('img[src*="instantgames"]');
            if ($fallbackImage->count()) {
                $src = $fallbackImage->attr('src');
                if ($src && !str_starts_with($src, 'http')) {
                    $src = 'https://www.sceducationlottery.com' . $src;
                }
                return $src;
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract image from South Carolina Lottery: ' . $e->getMessage());
            return null;
        }
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'South Carolina Education Lottery',
                'site_name' => $this->getSiteName(),
            ];

            $basic = $this->extractBasicInfo($crawler);
            $data = array_merge($data, $basic);

            $data['image'] = $this->extractImage($crawler);
            $data['prizes'] = $this->extractPrizes($crawler);

            // Calculate initial_prizes from the prizes array
            $data['initial_prizes'] = array_sum(array_column($data['prizes'], 'total'));

            $odds = $this->extractOdds($crawler);
            $data = array_merge($data, $odds);

            // Map last_day_to_claim to end_date for compatibility
            if (isset($data['last_day_to_claim'])) {
                $data['end_date'] = $data['last_day_to_claim'];
            }

            $data['how_to_play'] = $this->extractHowToPlay($crawler);

            return $data;

        } catch (\Exception $e) {
            Log::error('South Carolina Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape South Carolina Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        
        try {
            // South Carolina uses an instant-table with detailed columns
            // Support tables with or without <tbody>
            $rows = $crawler->filter('.instant-table tr');
            foreach ($rows as $row) {
                $rowCrawler = new Crawler($row);
                $cells = $rowCrawler->filter('td');
                if ($cells->count() < 5) {
                    continue;
                }
                $amount = trim($cells->eq(0)->text(''));
                if ($amount === '' || stripos($amount, 'Prize Amount') !== false) {
                    continue;
                }
                $unclaimedCount = trim($cells->eq(1)->text(''));
                $unclaimedValue = trim($cells->eq(2)->text(''));
                $totalCount = trim($cells->eq(3)->text(''));
                $totalValue = trim($cells->eq(4)->text(''));

                // Clean numbers
                $remaining = (int) preg_replace('/[^0-9]/', '', $unclaimedCount);
                $total = (int) preg_replace('/[^0-9]/', '', $totalCount);

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
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from South Carolina Lottery: ' . $e->getMessage());
        }
        
        return $prizes;
    }

    private function extractHowToPlay(Crawler $crawler): ?string
    {
        try {
            $ticketExample = $crawler->filter('.ticket-example');
            if ($ticketExample->count()) {
                $paragraphs = $ticketExample->filter('p');
                foreach ($paragraphs as $p) {
                    $pc = new Crawler($p);
                    $txt = trim($pc->text(''));
                    if ($txt !== '' && (stripos($txt, 'Match any of YOUR NUMBERS') !== false || stripos($txt, 'Reveal') !== false)) {
                        return $txt;
                    }
                }
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract how to play from South Carolina Lottery: ' . $e->getMessage());
            return null;
        }
    }

    private function isValidGamePage(Crawler $crawler): bool
    {
        // A valid game page contains a game number indicator or the instant game image or prize table
        if ($crawler->filter('h1 + span')->count() && preg_match('/GAME\s*#/i', $crawler->filter('h1 + span')->text(''))) {
            return true;
        }
        if ($crawler->filter('#InstantGameUncover')->count() > 0) {
            return true;
        }
        if ($crawler->filter('.instant-table tr')->count() > 1) {
            return true;
        }
        return false;
    }
}