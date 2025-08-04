<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class ArkansasLotteryScraper implements BaseScraper
{
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'myarkansaslottery.com');
    }

    public function getSiteName(): string
    {
        return 'Arkansas Lottery';
    }

    public function extractBasicInfo(Crawler $crawler): array
    {
        $info = [];
        
        try {
            // Extract title (Arkansas specific)
            $titleNode = $crawler->filter('.field-item h1.layout-center, h1.layout-center, .field-item h1, h1, h2, .game-title, .ticket-title');
            $title = $titleNode->count() ? trim($titleNode->text()) : null;
            
            // Log the raw title for debugging
            if ($title) {
                Log::info('Arkansas raw title: ' . $title);
            }
            
            // Clean the title - extract only the jackpot amount
            if ($title) {
                // Try multiple patterns to extract clean title
                if (preg_match('/\$([0-9,]+)\s*Jackpot/', $title, $matches)) {
                    $info['title'] = '$' . $matches[1] . ' Jackpot';
                } elseif (preg_match('/\$([0-9,]+)/', $title, $matches)) {
                    $info['title'] = '$' . $matches[1] . ' Jackpot';
                } else {
                    $info['title'] = $title;
                }
            }
            
            // If no title found, try to extract from text content
            if (!$info['title']) {
                $text = $crawler->text();
                if (preg_match('/\$([0-9,]+)\s*Jackpot/', $text, $matches)) {
                    $info['title'] = '$' . $matches[1] . ' Jackpot';
                }
            }

            // Extract game number
            $gameNoNode = $crawler->filter('.game-number, .ticket-number, *:contains("Game No.")');
            $info['game_no'] = $gameNoNode->count() ? trim($gameNoNode->text()) : null;

            // Extract price
            $priceNode = $crawler->filter('.price, .ticket-price, .game-price, *:contains("Ticket price:")');
            $info['price'] = $priceNode->count() ? trim($priceNode->text()) : null;

            // Extract start date
            $startDateNode = $crawler->filter('.start-date, .release-date, *:contains("Launch Date:")');
            $info['start_date'] = $startDateNode->count() ? trim($startDateNode->text()) : null;

            // Extract end date
            $endDateNode = $crawler->filter('.end-date, .claim-deadline, *:contains("Last Redeem Date:")');
            $info['end_date'] = $endDateNode->count() ? trim($endDateNode->text()) : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract basic info from Arkansas Lottery: ' . $e->getMessage());
        }
        
        return $info;
    }

    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        
        try {
            // Extract overall odds
            $oddsNode = $crawler->filter('.overall-odds, .total-odds, *:contains("Overall odds of winning:")');
            $odds['overall_odds'] = $oddsNode->count() ? trim($oddsNode->text()) : null;

            // Extract prize range
            $rangeNode = $crawler->filter('*:contains("Prize range:")');
            $odds['prize_range'] = $rangeNode->count() ? trim($rangeNode->text()) : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract odds from Arkansas Lottery: ' . $e->getMessage());
        }
        
        return $odds;
    }

    public function extractImage(Crawler $crawler): ?string
    {
        try {
            // Extract image (Arkansas specific)
            $imageNode = $crawler->filter('.ticket-image img, .game-image img, img[src*="game"]');
            return $imageNode->count() ? $imageNode->attr('src') : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract image from Arkansas Lottery: ' . $e->getMessage());
            return null;
        }
    }

    public function scrape(Crawler $crawler, string $url): array
    {
        try {
            $data = [
                'url' => $url,
                'state' => 'Arkansas Lottery'
            ];

            // Extract title (Arkansas specific)
            $titleNode = $crawler->filter('.field-item h1.layout-center, h1.layout-center, .field-item h1, h1, h2, .game-title, .ticket-title');
            $title = $titleNode->count() ? trim($titleNode->text()) : null;
            
            // Log the raw title for debugging
            if ($title) {
                Log::info('Arkansas raw title: ' . $title);
            }
            
            // Clean the title - extract only the jackpot amount
            if ($title) {
                // Try multiple patterns to extract clean title
                if (preg_match('/\$([0-9,]+)\s*Jackpot/', $title, $matches)) {
                    $data['title'] = '$' . $matches[1] . ' Jackpot';
                } elseif (preg_match('/\$([0-9,]+)/', $title, $matches)) {
                    $data['title'] = '$' . $matches[1] . ' Jackpot';
                } else {
                    $data['title'] = $title;
                }
            }
            
            // If no title found, try to extract from text content
            if (!$data['title']) {
                $text = $crawler->text();
                if (preg_match('/\$([0-9,]+)\s*Jackpot/', $text, $matches)) {
                    $data['title'] = '$' . $matches[1] . ' Jackpot';
                }
            }

            // Extract image (Arkansas specific)
            $imageNode = $crawler->filter('.field-name-field-ticket-front img, .ticket-image img, .game-image img, img[src*="game"], img[src*="instant"]');
            $data['image'] = $imageNode->count() ? $imageNode->attr('src') : null;

            // Extract price (Arkansas specific)
            $priceNode = $crawler->filter('.field-name-field-ticket-price .field-item');
            $price = $priceNode->count() ? trim($priceNode->text()) : null;
            
            // Ensure price has $ symbol
            if ($price && !str_contains($price, '$')) {
                $price = '$' . $price;
            }
            $data['price'] = $price;

            // Extract game number (Arkansas specific)
            $gameNoNode = $crawler->filter('.field-name-field-game-number strong, .field-name-field-game-number .field-item');
            $data['game_no'] = $gameNoNode->count() ? trim($gameNoNode->text()) : null;

            // Extract start date (Arkansas specific) - Launch Date
            $startDateNode = $crawler->filter('p.layout-3col__col-3 span');
            $startDate = $startDateNode->count() ? trim($startDateNode->text()) : null;
            
            // Log raw start date for debugging
            Log::info('Arkansas raw start date: ' . $startDate);
            
            // Clean and validate start date
            if ($startDate && $startDate !== 'To Be Determined' && !preg_match('/[<>{}()\[\]]/', $startDate)) {
                $data['start_date'] = $startDate;
            } else {
                $data['start_date'] = null;
            }

            // Extract end date (Arkansas specific) - Last Sell Date
            $endDateNode = $crawler->filter('p.layout-3col__col-1 span');
            $endDate = $endDateNode->count() ? trim($endDateNode->text()) : null;
            
            // Log the raw end date for debugging
            Log::info('Arkansas raw end date: ' . $endDate);
            
            // Clean and validate end date
            if ($endDate && $endDate !== 'To Be Determined' && !preg_match('/[<>{}()\[\]]/', $endDate)) {
                $data['end_date'] = $endDate;
            } else {
                $data['end_date'] = null;
            }

            // Extract prizes (Arkansas specific)
            $data['prizes'] = $this->extractPrizes($crawler);

            // Extract odds (Arkansas specific)
            $oddsNode = $crawler->filter('.field-name-field-game-odds .field-item');
            $oddsText = $oddsNode->count() ? trim($oddsNode->text()) : null;
            
            // Clean odds text - extract only the odds value
            if ($oddsText && preg_match('/1 in ([0-9.]+)/', $oddsText, $matches)) {
                $data['odds'] = '1:' . $matches[1];
            } else {
                $data['odds'] = null;
            }

            // Log the complete data object
            Log::info('Arkansas complete data: ' . json_encode($data, JSON_PRETTY_PRINT));
            
            // Set site name
            $data['site'] = $this->getSiteName();
            
            // Calculate additional fields for ROI calculation
            $data['initial_prizes'] = array_sum(array_column($data['prizes'], 'total'));
            $data['remaining_prizes'] = array_sum(array_column($data['prizes'], 'remaining'));
            
            // Extract probability from odds (convert "1:2.96" to percentage)
            if ($data['odds'] && preg_match('/1:([0-9.]+)/', $data['odds'], $matches)) {
                $oddsValue = floatval($matches[1]);
                // Calculate probability: 1 / odds * 100
                $data['probability'] = $oddsValue > 0 ? (1 / $oddsValue) * 100 : 0;
            } else {
                $data['probability'] = 0;
            }
            
            // Log calculation variables
            Log::info('Arkansas calculation variables:');
            Log::info('Arkansas initial_prizes: ' . $data['initial_prizes']);
            Log::info('Arkansas remaining_prizes: ' . $data['remaining_prizes']);
            Log::info('Arkansas probability: ' . $data['probability']);
            Log::info('Arkansas price: ' . ($data['price'] ?? 'null'));
            Log::info('Arkansas odds: ' . ($data['odds'] ?? 'null'));
            
            // Log individual fields for debugging
            Log::info('Arkansas title: ' . ($data['title'] ?? 'null'));
            Log::info('Arkansas image: ' . ($data['image'] ?? 'null'));
            Log::info('Arkansas price: ' . ($data['price'] ?? 'null'));
            Log::info('Arkansas game_no: ' . ($data['game_no'] ?? 'null'));
            Log::info('Arkansas start_date: ' . ($data['start_date'] ?? 'null'));
            Log::info('Arkansas end_date: ' . ($data['end_date'] ?? 'null'));
            Log::info('Arkansas odds: ' . ($data['odds'] ?? 'null'));
            Log::info('Arkansas prizes count: ' . count($data['prizes'] ?? []));
            Log::info('Arkansas URL: ' . ($data['url'] ?? 'null'));
            Log::info('Arkansas state: ' . ($data['site'] ?? 'null'));

            return $data;

        } catch (\Exception $e) {
            Log::error('Arkansas Lottery scraping failed: ' . $e->getMessage(), ['url' => $url]);
            return ['error' => 'Failed to scrape Arkansas Lottery data', 'url' => $url];
        }
    }

    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        
        try {
            // Arkansas specific: Look for prize table with proper structure
            $prizeRows = $crawler->filter('table tbody tr');
            
            foreach ($prizeRows as $row) {
                $rowCrawler = new Crawler($row);
                $cells = $rowCrawler->filter('td');
                
                if ($cells->count() >= 5) {
                    $amount = trim($cells->eq(0)->text());
                    $total = trim($cells->eq(1)->text());
                    $remaining = trim($cells->eq(2)->text());
                    
                    // Clean numeric values - handle comma-separated numbers
                    $amount = preg_replace('/[^0-9,.]/', '', $amount);
                    $total = (int) preg_replace('/[^0-9]/', '', $total);
                    $remaining = (int) preg_replace('/[^0-9]/', '', $remaining);
                    
                    if ($amount && $total > 0) {
                        $prizes[] = [
                            'amount' => $amount,
                            'total' => $total,
                            'remaining' => $remaining,
                            'paid' => $total - $remaining
                        ];
                    }
                }
            }
            
            // If no table found, try to extract from text
            if (empty($prizes)) {
                $text = $crawler->text();
                
                // Extract top prize information
                if (preg_match('/\$([0-9,]+)\s*Jackpot/', $text, $matches)) {
                    $amount = $matches[1];
                    $prizes[] = [
                        'amount' => $amount,
                        'total' => 1,
                        'remaining' => 1,
                        'paid' => 0
                    ];
                }
            }
            
            // Log for debugging
            Log::info('Arkansas prizes extracted: ' . count($prizes));
            
        } catch (\Exception $e) {
            Log::error('Failed to extract prizes from Arkansas Lottery: ' . $e->getMessage());
        }
        
        return $prizes;
    }
} 