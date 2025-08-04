<?php

$states = [
    'Kentucky' => 'kylottery.com',
    'Louisiana' => 'louisianalottery.com',
    'Mississippi' => 'mslotteryhome.com',
    'New Jersey' => 'njlottery.com',
    'North Carolina' => 'nclottery.com',
    'South Carolina' => 'sceducationlottery.com',
    'Texas' => 'texaslottery.com',
    'West Virginia' => 'wvlottery.com'
];

foreach ($states as $state => $domain) {
    $className = str_replace(' ', '', $state) . 'LotteryScraper';
    $stateName = $state . ' Lottery';
    
    $content = "<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class {$className} implements BaseScraper
{
    public function canHandle(string \$url): bool
    {
        return str_contains(\$url, '{$domain}');
    }

    public function scrape(Crawler \$crawler, string \$url): array
    {
        try {
            \$data = [
                'url' => \$url,
                'state' => '{$stateName}'
            ];

            // Extract title
            \$titleNode = \$crawler->filter('h1, .game-title, .ticket-title');
            \$data['title'] = \$titleNode->count() ? trim(\$titleNode->text()) : null;

            // Extract image
            \$imageNode = \$crawler->filter('.ticket-image img, .game-image img');
            \$data['image'] = \$imageNode->count() ? \$imageNode->attr('src') : null;

            // Extract price
            \$priceNode = \$crawler->filter('.price, .ticket-price, .game-price');
            \$data['price'] = \$priceNode->count() ? trim(\$priceNode->text()) : null;

            // Extract game number
            \$gameNoNode = \$crawler->filter('.game-number, .ticket-number');
            \$data['game_no'] = \$gameNoNode->count() ? trim(\$gameNoNode->text()) : null;

            // Extract start date
            \$startDateNode = \$crawler->filter('.start-date, .release-date');
            \$data['start_date'] = \$startDateNode->count() ? trim(\$startDateNode->text()) : null;

            // Extract end date
            \$endDateNode = \$crawler->filter('.end-date, .claim-deadline');
            \$data['end_date'] = \$endDateNode->count() ? trim(\$endDateNode->text()) : null;

            // Extract prizes
            \$data['prizes'] = \$this->extractPrizes(\$crawler);

            // Extract odds
            \$oddsNode = \$crawler->filter('.overall-odds, .total-odds');
            \$data['odds'] = \$oddsNode->count() ? trim(\$oddsNode->text()) : null;

            return \$data;

        } catch (\Exception \$e) {
            Log::error('{$stateName} scraping failed: ' . \$e->getMessage(), ['url' => \$url]);
            return ['error' => 'Failed to scrape {$stateName} data', 'url' => \$url];
        }
    }

    private function extractPrizes(Crawler \$crawler): array
    {
        \$prizes = [];
        
        try {
            // Look for prize table rows
            \$prizeRows = \$crawler->filter('table tbody tr');
            
            foreach (\$prizeRows as \$row) {
                \$rowCrawler = new Crawler(\$row);
                \$cells = \$rowCrawler->filter('td');
                
                if (\$cells->count() >= 3) {
                    \$amount = trim(\$cells->eq(0)->text());
                    \$total = trim(\$cells->eq(1)->text());
                    \$remaining = trim(\$cells->eq(2)->text());
                    
                    // Clean numeric values
                    \$amount = preg_replace('/[^0-9,]/', '', \$amount);
                    \$total = (int) preg_replace('/[^0-9]/', '', \$total);
                    \$remaining = (int) preg_replace('/[^0-9]/', '', \$remaining);
                    
                    if (\$amount && \$total > 0) {
                        \$prizes[] = [
                            'amount' => \$amount,
                            'total' => \$total,
                            'remaining' => \$remaining,
                            'paid' => \$total - \$remaining
                        ];
                    }
                }
            }
        } catch (\Exception \$e) {
            Log::error('Failed to extract prizes from {$stateName}: ' . \$e->getMessage());
        }
        
        return \$prizes;
    }
}";

    file_put_contents("app/Services/Scrapers/{$className}.php", $content);
    echo "Created: {$className}.php\n";
}

echo "\nAll scrapers created successfully!\n";
?> 