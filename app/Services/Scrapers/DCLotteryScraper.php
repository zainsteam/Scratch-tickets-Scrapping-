<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class DCLotteryScraper implements BaseScraper
{
    public function extractBasicInfo(Crawler $crawler): array
    {
        $data = [];
        
        // Extract title
        $data['title'] = $crawler->filter('.pageheader__title h1')->count()
            ? trim($crawler->filter('.pageheader__title h1')->text())
            : null;
        
        // Extract basic info fields
        $labels = ['Price', 'Game No', 'Start Date', 'Odds', 'Top Prize Odds'];
        foreach ($labels as $label) {
            $node = $crawler->filter(".pageheader--game__info .field__label:contains(\"$label\")");
            $key = strtolower(str_replace(' ', '_', $label));
            $data[$key] = $node->count()
                ? ($label === 'Start Date'
                    ? $node->first()->siblings()->filter('time')->text('')
                    : $node->first()->siblings()->text(''))
                : null;
        }
        
        // Extract end date (Last Date to Claim)
        $endDateNode = $crawler->filter('.field--name-field-last-date-to-claim .field__item time');
        $data['end_date'] = $endDateNode->count() ? $endDateNode->text('') : null;
        
        return $data;
    }
    
    public function extractPrizes(Crawler $crawler): array
    {
        $prizes = [];
        $initialPrizes = 0;
        
        // Method 1: Try to get the first table specifically
        $targetTable = $this->getFirstTable($crawler);
        
        if ($targetTable && $targetTable->count() > 0) {
            $targetTable->filter('tbody tr')->each(function (Crawler $row) use (&$prizes, &$initialPrizes) {
                $cells = $row->filter('td');
                if ($cells->count() === 4) {
                    $amountText = trim($cells->eq(0)->text(), '$ ');
                    $amount = floatval(preg_replace('/[^0-9.]/', '', $amountText));
                    
                    $total = (int) filter_var($cells->eq(1)->text(), FILTER_SANITIZE_NUMBER_INT);
                    $paid = (int) filter_var($cells->eq(2)->text(), FILTER_SANITIZE_NUMBER_INT);
                    $remaining = (int) filter_var($cells->eq(3)->text(), FILTER_SANITIZE_NUMBER_INT);
                    
                    $initialPrizes += $total;
                    
                    // Calculate column1 based on Excel formula
                    $ratio = $total > 0 ? $remaining / $total : 0;
                    
                    // Excel formula: IF(OR(E29>2,E29=C29,E29÷C29>0.5),B29×E29,IF(OR(E29=1,E29=2,E29÷C29<0.5),0,B29×E29))
                    if ($remaining > 2 || $remaining === $total || $ratio > 0.5) {
                        // First condition: amount × remaining
                        $column1 = $amount * $remaining;
                    } elseif ($remaining === 1 || $remaining === 2 || $ratio < 0.5) {
                        // Second condition: 0
                        $column1 = 0;
                    } else {
                        // Else: amount × remaining
                        $column1 = $amount * $remaining;
                    }
                    
                    $prizes[] = [
                        'amount' => $amountText,
                        'total' => $total,
                        'paid' => $paid,
                        'remaining' => $remaining,
                        'column1' => round($column1, 2)
                    ];
                }
            });
        }
        
        return [
            'prizes' => $prizes,
            'initial_prizes' => $initialPrizes
        ];
    }
    
    /**
     * Get the first table from the page, avoiding multiple tables
     */
    private function getFirstTable(Crawler $crawler): ?Crawler
    {
        // Try different strategies to get the first table
        
        // Strategy 1: Get the first table.views-table
        $firstTable = $crawler->filter('table.views-table')->first();
        if ($firstTable->count() > 0) {
            return $firstTable;
        }
        
        // Strategy 2: Get table by position (first table on page)
        $allTables = $crawler->filter('table');
        if ($allTables->count() > 0) {
            return $allTables->first();
        }
        
        // Strategy 3: Look for table with specific headers or content
        $tablesWithHeaders = $crawler->filter('table')->filter(function (Crawler $table) {
            return $table->filter('th, td')->count() > 0;
        });
        
        if ($tablesWithHeaders->count() > 0) {
            return $tablesWithHeaders->first();
        }
        
        return null;
    }
    
    public function extractOdds(Crawler $crawler): array
    {
        $odds = [];
        
        // Extract odds from the basic info
        $oddsNode = $crawler->filter(".pageheader--game__info .field__label:contains(\"Odds\")");
        $oddsText = $oddsNode->count() ? $oddsNode->first()->siblings()->text('') : null;
        
        $probability = null;
        if (!empty($oddsText) && str_contains($oddsText, ':')) {
            [$left, $right] = explode(':', $oddsText);
            $left = floatval($left);
            $right = floatval(str_replace(',', '', $right));
            if ($left > 0 && $right > 0) {
                $probability = ($right > 0) ? (($left / $right) * 100) : 0;
            }
        }
        
        return [
            'odds' => $oddsText,
            'probability' => $probability
        ];
    }
    
    public function extractImage(Crawler $crawler): ?string
    {
        $imageDiv = $crawler->filter('.ticket-image');
        if ($imageDiv->count()) {
            $style = $imageDiv->attr('style');
            if (preg_match('/background-image:\s*url\((.*?)\)/i', $style, $matches)) {
                $relativeUrl = trim($matches[1], "\"'");
                return 'https://dclottery.com' . $relativeUrl;
            }
        }
        
        return null;
    }
    
    public function getSiteName(): string
    {
        return 'DC Lottery';
    }
    
    public function canHandle(string $url): bool
    {
        return str_contains($url, 'dclottery.com');
    }
} 