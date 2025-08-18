<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class VirginiaLotteryScraper implements BaseScraper
{
    public function extractBasicInfo(Crawler $crawler): array
    {
        $data = [
            'title' => null,
            'price' => null,
            'game_no' => null,
            'start_date' => null,
            'end_date' => null,
            'top_grand_prize' => null,
        ];

        // Title: h2.title-display, remove trailing game number in <small>
        $titleNode = $crawler->filter('h2.title-display');
        if ($titleNode->count()) {
            $titleText = trim($titleNode->text(''));
            // Remove the game number suffix like "#2150"
            $titleText = preg_replace('/\s*#\d+$/', '', $titleText);
            $data['title'] = $titleText;
            // Extract game number from <small>#2150</small>
            $small = $titleNode->filter('small');
            if ($small->count()) {
                if (preg_match('/#?(\d+)/', $small->text(''), $m)) {
                    $data['game_no'] = $m[1];
                }
            }
        }

        // Price
        $priceNode = $crawler->filter('.ticket-price-display');
        $data['price'] = $priceNode->count() ? trim($priceNode->text('')) : null;

        // Top grand prize
        $topPrizeNode = $crawler->filter('.top-prize-display');
        if ($topPrizeNode->count()) {
            $data['top_grand_prize'] = trim($topPrizeNode->text(''));
        }

        // Start Date
        $startDateNode = $crawler->filterXPath("//label[normalize-space() = 'Start Date']/following-sibling::h2[contains(@class,'start-date-display')][1]");
        $data['start_date'] = $startDateNode->count() ? trim($startDateNode->text('')) : null;

        // End Date
        $endDateNode = $crawler->filterXPath("//label[normalize-space() = 'Last Claim Date']/following-sibling::h2[contains(@class,'start-date-display')][1]");
        if ($endDateNode->count()) {
            $data['end_date'] = trim($endDateNode->text(''));
        } else {
            // Fallback to 'End Date' if Last Claim Date is not present
            $endFallback = $crawler->filterXPath("//label[normalize-space() = 'End Date']/following-sibling::h2[contains(@class,'start-date-display')][1]");
            $data['end_date'] = $endFallback->count() ? trim($endFallback->text('')) : null;
        }

        return $data;
    }
    
    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        $initialPrizes = 0;

        // Prize table: .scratcher-prize-table with headers Prize Amount | Winning Tickets At Start | Winning Tickets Unclaimed
        $table = $crawler->filter('table.scratcher-prize-table');
        if ($table->count()) {
            $table->filter('tbody tr')->each(function (Crawler $row) use (&$prizes, &$initialPrizes) {
                $cells = $row->filter('td');
                if ($cells->count() >= 3) {
                    $amountText = trim($cells->eq(0)->text());
                    $totalText = trim($cells->eq(1)->text());
                    $remainingText = trim($cells->eq(2)->text());

                    $total = (int) preg_replace('/[^0-9]/', '', $totalText);
                    $remaining = (int) preg_replace('/[^0-9]/', '', $remainingText);
                    $paid = max(0, $total - $remaining);
                    $initialPrizes += $total;

                    $prizes[] = [
                        'amount' => preg_replace('/[^0-9.,]/', '', $amountText),
                        'total' => $total,
                        'paid' => $paid,
                        'remaining' => $remaining,
                    ];
                }
            });
        }

        return [
            'prizes' => $prizes,
            'initial_prizes' => $initialPrizes
        ];
    }
    
    public function extractOdds(Crawler $crawler): array
    {
        // Overall odds in .odds-display: "Odds of Winning Overall: 1 in <span>3.43</span>"
        $oddsWrapper = $crawler->filter('p.odds-display');
        $overall = null;
        if ($oddsWrapper->count()) {
            $span = $oddsWrapper->filter('span')->first();
            if ($span->count()) {
                $value = trim($span->text(''));
                $overall = '1 in ' . $value;
            }
        }

        $probability = null;
        if ($overall && preg_match('/1\s*in\s*([0-9.,]+)/i', $overall, $m)) {
            $right = (float) str_replace(',', '', $m[1]);
            $probability = $right > 0 ? (1 / $right) * 100 : null;
        }

        return [
            'odds' => $overall,
            'probability' => $probability
        ];
    }
    
    public function extractImage(Crawler $crawler): ?string
    {
        // Prefer canvas data-front-image-url for the interactive scratcher
        $canvas = $crawler->filter('#interactive-scratcher-container canvas[data-front-image-url]');
        if ($canvas->count()) {
            $src = $canvas->first()->attr('data-front-image-url');
            if ($src) {
                return $src;
            }
        }

        // Fallback: any image within the interactive-scratcher container
        $img = $crawler->filter('#interactive-scratcher-container img');
        if ($img->count()) {
            return $img->first()->attr('src');
        }

        // Global fallback: first image on page that looks like a scratcher asset
        $imgAnywhere = $crawler->filter("img[src*='digital-scratcher-front-images'], img[src*='scratched'], img[src*='unscratched']");
        if ($imgAnywhere->count()) {
            return $imgAnywhere->first()->attr('src');
        }

        return null;
    }
    
    public function getSiteName(): string
    {
        return 'Virginia Lottery';
    }
    
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'valottery.com');
    }
} 