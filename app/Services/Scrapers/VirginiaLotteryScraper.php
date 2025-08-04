<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class VirginiaLotteryScraper implements BaseScraper
{
    public function extractBasicInfo(Crawler $crawler): array
    {
        $data = [];
        
        // Virginia uses different selectors
        $data['title'] = $crawler->filter('.scratch-off-title')->count()
            ? trim($crawler->filter('.scratch-off-title')->text())
            : null;
        
        // Different field structure
        $data['price'] = $crawler->filter('.ticket-price')->text('');
        $data['game_no'] = $crawler->filter('.game-id')->text('');
        $data['start_date'] = $crawler->filter('.release-date')->text('');
        
        return $data;
    }
    
    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        $initialPrizes = 0;
        
        // Virginia uses different table structure
        $crawler->filter('.prize-breakdown tr')->each(function (Crawler $row) use (&$prizes, &$initialPrizes) {
            $cells = $row->filter('td');
            if ($cells->count() >= 4) {
                $amountText = trim($cells->eq(0)->text(), '$ ');
                $total = (int) filter_var($cells->eq(1)->text(), FILTER_SANITIZE_NUMBER_INT);
                $paid = (int) filter_var($cells->eq(2)->text(), FILTER_SANITIZE_NUMBER_INT);
                $remaining = (int) filter_var($cells->eq(3)->text(), FILTER_SANITIZE_NUMBER_INT);
                
                $initialPrizes += $total;
                $amount = floatval(preg_replace('/[^0-9.]/', '', $amountText));
                $column1 = ($remaining >= 3 || $remaining === $total) ? $amount * $remaining : 0;
                
                $prizes[] = [
                    'amount' => $amountText,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'column1' => round($column1, 2)
                ];
            }
        });
        
        return [
            'prizes' => $prizes,
            'initial_prizes' => $initialPrizes
        ];
    }
    
    public function extractOdds(Crawler $crawler): array
    {
        $oddsText = $crawler->filter('.winning-odds')->text('');
        
        $probability = null;
        if (!empty($oddsText) && str_contains($oddsText, ':')) {
            [$left, $right] = explode(':', $oddsText);
            $left = floatval($left);
            $right = floatval(str_replace(',', '', $right));
            $probability = ($right > 0) ? (($left / $right) * 100) : 0;
        }
        
        return [
            'odds' => $oddsText,
            'probability' => $probability
        ];
    }
    
    public function extractImage(Crawler $crawler): ?string
    {
        $image = $crawler->filter('.ticket-image img')->attr('src');
        return $image ?: null;
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