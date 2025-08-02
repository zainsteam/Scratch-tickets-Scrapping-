<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class MarylandLotteryScraper implements BaseScraper
{
    public function extractBasicInfo(Crawler $crawler): array
    {
        $data = [];
        
        // Maryland uses different selectors
        $data['title'] = $crawler->filter('.game-title h2')->count()
            ? trim($crawler->filter('.game-title h2')->text())
            : null;
        
        // Different field structure
        $data['price'] = $crawler->filter('.game-price .value')->text('');
        $data['game_no'] = $crawler->filter('.game-number .value')->text('');
        $data['start_date'] = $crawler->filter('.game-date .value')->text('');
        
        return $data;
    }
    
    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        $initialPrizes = 0;
        
        // Maryland uses different table structure
        $crawler->filter('.prize-table tr')->each(function (Crawler $row) use (&$prizes, &$initialPrizes) {
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
        $oddsText = $crawler->filter('.odds-info .value')->text('');
        
        $probability = null;
        if (!empty($oddsText) && str_contains($oddsText, ':')) {
            [$left, $right] = explode(':', $oddsText);
            $left = floatval($left);
            $right = floatval(str_replace(',', '', $right));
            if ($left > 0 && $right > 0) {
                $probability = (($left / $right) * 100);
            }
        }
        
        return [
            'odds' => $oddsText,
            'probability' => $probability
        ];
    }
    
    public function extractImage(Crawler $crawler): ?string
    {
        $image = $crawler->filter('.game-image img')->attr('src');
        return $image ?: null;
    }
    
    public function getSiteName(): string
    {
        return 'Maryland Lottery';
    }
    
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'mdlottery.com');
    }
} 