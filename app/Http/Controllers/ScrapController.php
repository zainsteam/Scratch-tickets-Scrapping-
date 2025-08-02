<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Exports\TicketsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\UniversalScrapingService;
use App\Services\ScraperFactory;

use App\Exports\TicketsByPriceExport;

class ScrapController extends Controller
{
    private UniversalScrapingService $scrapingService;
    
    public function __construct(UniversalScrapingService $scrapingService)
    {
        $this->scrapingService = $scrapingService;
    }

    public function exportTickets()
    {
        $scraper = new ScrapController(app(UniversalScrapingService::class));
        $tickets = json_decode($scraper->getMultipleData()->getContent(), true);
    
        if (empty($tickets)) {
            return response()->json(['error' => 'No tickets available to export.']);
        }
    
        $grouped = collect($tickets)
            ->filter(fn($t) => isset($t['price']) && $t['price'] !== null)
            ->groupBy('price');
    
        return Excel::download(new TicketsExport($grouped), 'tickets_by_price.xlsx');
    }

    public function getMultipleData()
    {
        set_time_limit(0); // remove time limit temporarily
        
        // Mixed URLs from different lottery sites
        $urls = [
            'https://dclottery.com/dc-scratchers/100-loaded',
            'https://dclottery.com/dc-scratchers/fireball-5s',
            'https://dclottery.com/dc-scratchers/20-roaring-cash',
            'https://dclottery.com/dc-scratchers/mystery-multiplier',
            'https://dclottery.com/dc-scratchers/power-cash-10x',
            'https://dclottery.com/dc-scratchers/lucky-loot-hd',
            'https://dclottery.com/dc-scratchers/electric-diamonds',
            'https://dclottery.com/dc-scratchers/50-or-100',
            'https://dclottery.com/dc-scratchers/double-sided-dollar',
            'https://dclottery.com/dc-scratchers/tic-tac-multiplier',
            'https://dclottery.com/dc-scratchers/jumbo-bucks-supreme',
            'https://dclottery.com/dc-scratchers/202-2nd-edition',
            'https://dclottery.com/dc-scratchers/71121',
            'https://dclottery.com/dc-scratchers/power-cash-2x',
            'https://dclottery.com/dc-scratchers/king-cash-multiplier',
            'https://dclottery.com/dc-scratchers/win-big',
            'https://dclottery.com/dc-scratchers/holiday-riches',
            'https://dclottery.com/dc-scratchers/extreme-500x-fortune-0',
            'https://dclottery.com/dc-scratchers/1000000-money-maker',
            'https://dclottery.com/dc-scratchers/300x',
            'https://dclottery.com/dc-scratchers/200x-0',
            'https://dclottery.com/dc-scratchers/fortune',
            'https://dclottery.com/dc-scratchers/fire-ice',
            'https://dclottery.com/dc-scratchers/win-it-all-0',
            'https://dclottery.com/dc-scratchers/mega-bucks',
            'https://dclottery.com/dc-scratchers/20x-cash-0',
            'https://dclottery.com/dc-scratchers/blackjack-tripler',
            'https://dclottery.com/dc-scratchers/lucky',
            'https://dclottery.com/dc-scratchers/dc-love',
            'https://dclottery.com/dc-scratchers/loteria-1',
            'https://dclottery.com/dc-scratchers/one-word-crossword',
            'https://dclottery.com/dc-scratchers/high-voltage-bingo',
            'https://dclottery.com/dc-scratchers/100-mayhem',
            'https://dclottery.com/dc-scratchers/500-mayhem',
            'https://dclottery.com/dc-scratchers/50-mayhem-0',
            'https://dclottery.com/dc-scratchers/lucky-letter-crossword',
            'https://dclottery.com/dc-scratchers/20x-0',
            'https://dclottery.com/dc-scratchers/ultimate-riches',
            'https://dclottery.com/dc-scratchers/platinum-diamond-spectacular',
            'https://dclottery.com/dc-scratchers/100x-cash-0',
            'https://dclottery.com/dc-scratchers/full-5000s',
            'https://dclottery.com/dc-scratchers/snow-much-fun',
            'https://dclottery.com/dc-scratchers/winter-winnings',
            'https://dclottery.com/dc-scratchers/cash-blast',
            'https://dclottery.com/dc-scratchers/monopoly-1',
            'https://dclottery.com/dc-scratchers/50x-cash',
            'https://dclottery.com/dc-scratchers/diamond-dollars',
            'https://dclottery.com/dc-scratchers/triple-777-0',
            'https://dclottery.com/dc-scratchers/red-hot-riches',
            'https://dclottery.com/dc-scratchers/100-grand',
            'https://dclottery.com/dc-scratchers/win-it-all',
            'https://dclottery.com/dc-scratchers/aces-8s',
            'https://dclottery.com/dc-scratchers/strike-it-rich',
            'https://dclottery.com/dc-scratchers/lightening-7s',
            'https://dclottery.com/dc-scratchers/50x-0',
            'https://dclottery.com/dc-scratchers/double-cash-doubler',
            'https://dclottery.com/dc-scratchers/break-bank',
            'https://dclottery.com/dc-scratchers/uno',
            'https://dclottery.com/dc-scratchers/emerald-green-8s',
            'https://dclottery.com/dc-scratchers/cah-tastic-doubler',
            'https://dclottery.com/dc-scratchers/monopoly-0',
            'https://dclottery.com/dc-scratchers/15x-cashword-0',
            'https://dclottery.com/dc-scratchers/5-star-crossword',
            'https://dclottery.com/dc-scratchers/twisted-treasure',
            'https://dclottery.com/dc-scratchers/bingo-plus',
            'https://dclottery.com/dc-scratchers/electric-8s',
            'https://dclottery.com/dc-scratchers/10x-cash',
            'https://dclottery.com/dc-scratchers/monopoly',
            'https://dclottery.com/dc-scratchers/triple-333-0',
            'https://dclottery.com/dc-scratchers/match-2-win',
            'https://dclottery.com/dc-scratchers/power-cash',
            'https://dclottery.com/dc-scratchers/holiday-wishes',
            'https://dclottery.com/dc-scratchers/hit-1000',
            'https://dclottery.com/dc-scratchers/2025',
            'https://dclottery.com/dc-scratchers/1-40th-anniversary',
            'https://dclottery.com/dc-scratchers/2024-make-my-year',
            'https://dclottery.com/dc-scratchers/massive-money-blowout',
            'https://dclottery.com/dc-scratchers/easy-money',
            'https://dclottery.com/dc-scratchers/money-talks',
            'https://dclottery.com/dc-scratchers/stocking-stuffer',
            'https://dclottery.com/dc-scratchers/100-frenzy',
            'https://dclottery.com/dc-scratchers/hit-500',
            'https://dclottery.com/dc-scratchers/hit-100',
            'https://dclottery.com/dc-scratchers/5x-cash',
            'https://dclottery.com/dc-scratchers/double-deuces',
            'https://dclottery.com/dc-scratchers/sapphire-6s',
            'https://dclottery.com/dc-scratchers/topaz-7s',
            'https://dclottery.com/dc-scratchers/dc-cah',
            'https://dclottery.com/dc-scratchers/mad-money',
            'https://dclottery.com/dc-scratchers/hit-50',
            'https://dclottery.com/dc-scratchers/10-million-cash-extravaganza',
            'https://dclottery.com/dc-scratchers/capital-fortune',
            'https://dclottery.com/dc-scratchers/100x-cash',
            'https://dclottery.com/dc-scratchers/100x-1',
            'https://dclottery.com/dc-scratchers/triple-777',
            'https://dclottery.com/dc-scratchers/lady-luck-doubler',
            'https://dclottery.com/dc-scratchers/10x-0',
            'https://dclottery.com/dc-scratchers/5x-cash-0',
            'https://dclottery.com/dc-scratchers/hot-8',
            'https://dclottery.com/dc-scratchers/lucky-7s',
            'https://dclottery.com/dc-scratchers/red-hot-double-doubler-1',
        ];

        $results = $this->scrapingService->scrapeUrlsInParallel($urls);
        
        // Process results and calculate metrics
        $processedResults = [];
        foreach ($results as $rawData) {
            if (!isset($rawData['error'])) {
                $result = $this->calculateTicketMetrics($rawData);
                // Only add non-null results (skip expired tickets)
                if ($result !== null) {
                    $processedResults[] = $result;
                }
            }
        }

        $processedResults = $this->processAllTickets($processedResults);

        return response()->json($processedResults);
    }

    public function getSupportedSites()
    {
        return response()->json([
            'supported_sites' => $this->scrapingService->getSupportedDomains()
        ]);
    }

    public function scrapeSingleSite(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        if (!$this->scrapingService->isUrlSupported($request->url)) {
            return response()->json([
                'error' => 'This URL is not supported. Please check supported sites.',
                'supported_sites' => $this->scrapingService->getSupportedDomains()
            ], 400);
        }

        $rawData = $this->scrapingService->scrapeSingleUrl($request->url);
        
        if (!isset($rawData['error'])) {
            $result = $this->calculateTicketMetrics($rawData);
            if ($result === null) {
                return response()->json([
                    'error' => 'This ticket has expired (end date is in the past)',
                    'url' => $request->url
                ], 400);
            }
            return response()->json($result);
        }

        return response()->json($rawData, 500);
    }

    private function calculateTicketMetrics($data)
    {
        // Defensive: Ensure 'url' key exists
        if (empty($data['url'])) {
            $data['url'] = 'unknown-url';
        }
        
        // Check if end date is in the past - skip expired tickets
        if (!empty($data['end_date'])) {
            try {
                $endDate = Carbon::createFromFormat('m/d/Y', $data['end_date']);
                $today = Carbon::now();
                
                if ($endDate->isPast()) {
                    // Return null to indicate this ticket should be skipped
                    return null;
                }
            } catch (\Exception $e) {
                // If date parsing fails, continue with the ticket
                Log::warning('Failed to parse end date: ' . $data['end_date'], ['url' => $data['url']]);
            }
        }
        
        // Cache expensive calculations
        static $calculationCache = [];
        $cacheKey = md5($data['url'] . serialize($data['prizes']));
        
        if (isset($calculationCache[$cacheKey])) {
            return $calculationCache[$cacheKey];
        }

        $remainingPrizes = 0;
        if (isset($data['prizes'][0])) {
            $firstRemaining = (int) $data['prizes'][0]['remaining'];
            $firstTotal = (int) $data['prizes'][0]['total'];
    
            if ($firstRemaining >= 3 || $firstRemaining === $firstTotal) {
                $remainingPrizes = array_sum(array_column($data['prizes'], 'remaining'));
            } else {
                $remainingPrizes = array_sum(array_column(array_slice($data['prizes'], 1), 'remaining'));
            }
        }
    
        $data['remaining_prizes'] = $remainingPrizes;
    
        // Optimize array operations
        $sumProduct = 0;
        $sumOfAllCost = 0;
        $firstPrizeRemaining = $data['prizes'][0]['remaining'] ?? 0;
        $firstPrizeTotal = $data['prizes'][0]['total'] ?? 0;
        
        foreach ($data['prizes'] as $index => $prize) {
            $amount = floatval(preg_replace('/[^0-9.]/', '', $prize['amount']));
            $sumProduct += $amount * $prize['total'];
            
            // Calculate column1 and sumOfAllCost in one loop
            $column1 = ($prize['remaining'] >= 3 || $prize['remaining'] === $prize['total']) ? $amount * $prize['remaining'] : 0;
            $data['prizes'][$index]['column1'] = round($column1, 2);
            
            if ($index === 0) {
                if ($firstPrizeRemaining >= 3 || $firstPrizeRemaining === $firstPrizeTotal) {
                    $sumOfAllCost += $column1;
                }
            } else {
                $sumOfAllCost += $column1;
            }
        }
    
        $data['price'] = $data['price'] ? preg_replace('/[^0-9.]/', '', $data['price']) : null;
        $ticketPrice = $data['price'] ? floatval($data['price']) : 0;
    
        $excelRatioV2 = null;
        if (($data['probability'] ?? 0) > 0 && $ticketPrice > 0) {
            $denominator = ($data['initial_prizes'] / ($data['probability'] / 100)) * $ticketPrice;
            if ($denominator > 0) {
                $excelRatioV2 = $sumProduct / $denominator;
            }
        }
    
        $costToBuyAllRemaining = ($data['probability'] > 0 && $ticketPrice > 0)
            ? round(($data['remaining_prizes'] / ($data['probability'] / 100)) * $ticketPrice)
            : null;
    
        $totalRemainingTickets = ($data['probability'] > 0)
            ? round($data['remaining_prizes'] / ($data['probability'] / 100))
            : null;
    
        $score = ($totalRemainingTickets && $costToBuyAllRemaining !== null)
            ? round(($sumOfAllCost - $costToBuyAllRemaining) / $totalRemainingTickets, 2)
            : null;
    
        $finalPercentage = ($costToBuyAllRemaining > 0)
            ? round(($sumOfAllCost / $costToBuyAllRemaining) * 100, 2)
            : null;

        // Optimize grand prize calculation
        $topGrandPrize = null;
        $initialGrandPrize = null;
        $currentGrandPrize = null;

        if (!empty($data['prizes'])) {
            $highestAmount = 0;
            $highestPrize = null;
            
            foreach ($data['prizes'] as $prize) {
                $amount = floatval(preg_replace('/[^0-9.]/', '', $prize['amount']));
                if ($amount > $highestAmount) {
                    $highestAmount = $amount;
                    $highestPrize = $prize;
                }
            }

            if ($highestPrize) {
                $topGrandPrize = '$' . number_format($highestAmount);
                $initialGrandPrize = $highestPrize['total'] ?? null;
                $currentGrandPrize = $highestPrize['remaining'] ?? null;
            }
        }

        $result = [
            'title' => $data['title']." #".$data['game_no'],
            'image' => $data['image'],
            'price' => $data['price'],
            'game_no' => $data['game_no'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'prizes' => $data['prizes'],
            'initial_ROI' => $excelRatioV2 ? round($excelRatioV2 * 100, 2) : null,
            'score' => $score,
            'current_ROI' => $finalPercentage ?? null,
            'url' => $data['url'],
            'ranking' => null,
            'type' => [],
            'state' => $data['site'] ?? "Washington DC",
            'top_grand_prize' => $topGrandPrize,
            'initial_grand_prize' => $initialGrandPrize,    
            'current_grand_prize' => $currentGrandPrize, 
            'grand_prize_left' =>  ($currentGrandPrize / $initialGrandPrize) * 100,
        ];

        // Cache the result
        $calculationCache[$cacheKey] = $result;
        
        return $result;
    }

    private function processAllTickets($results)
    {
        $collection = collect($results);
        
        // Pre-filter and sort collections once
        $validTickets = $collection->filter(fn($ticket) => isset($ticket['current_ROI']));
        
        // 🥇 Top 10 by ROI
        $top10 = $validTickets->sortByDesc('current_ROI')->values()->take(10);

        // 🆕 Newly Released This Month
        $currentMonthStart = Carbon::now()->startOfMonth();
        $newly = $validTickets
            ->filter(function ($ticket) use ($currentMonthStart) {
                if (empty($ticket['start_date'])) {
                    return false;
                }
                try {
                    $date = Carbon::createFromFormat('m/d/Y', $ticket['start_date']);
                    return $date->greaterThanOrEqualTo($currentMonthStart);
                } catch (\Exception $e) {
                    return false;
                }
            })
            ->sortByDesc('current_ROI')
            ->values();

        // 💰 Grand Prize (Top 10 by Highest Top Prize)
        $grand = $collection
            ->filter(fn($ticket) => isset($ticket['top_grand_prize']))
            ->sortByDesc(function ($ticket) {
                // 1st: Sort by top grand prize (highest to lowest)
                return floatval(preg_replace('/[^0-9.]/', '', $ticket['top_grand_prize']));
            })
            ->sortByDesc(function ($ticket) {
                // 2nd: If grand prizes are same, sort by ticket price (highest to lowest)
                return floatval(preg_replace('/[^0-9.]/', '', $ticket['price'] ?? '0'));
            })
            ->sortByDesc(function ($ticket) {
                // 3rd: If grand prizes and prices are same, sort by grand prizes remaining % (highest to lowest)
                return floatval($ticket['grand_prize_left'] ?? 0);
            })
            ->values()
            ->take(10);
            
        // Debug: Log all 3 conditions sorting
        Log::info('All 3 Conditions Debug - Grand Prize + Price + Remaining % Sorting', [
            'top_10_grand_prizes' => $grand->pluck('top_grand_prize')->toArray(),
            'top_10_titles' => $grand->pluck('title')->toArray(),
            'top_10_prices' => $grand->pluck('price')->toArray(),
            'top_10_remaining' => $grand->pluck('grand_prize_left')->toArray(),
            '300x_in_top_10' => $grand->contains(function ($ticket) {
                return str_contains($ticket['title'] ?? '', '300X');
            }),
            'money_maker_in_top_10' => $grand->contains(function ($ticket) {
                return str_contains($ticket['title'] ?? '', 'Money Maker');
            }),
            '300x_rank' => $grand->search(function ($ticket) {
                return str_contains($ticket['title'] ?? '', '300X');
            }),
            'money_maker_rank' => $grand->search(function ($ticket) {
                return str_contains($ticket['title'] ?? '', 'Money Maker');
            }),
            'ultimate_riches_rank' => $grand->search(function ($ticket) {
                return str_contains($ticket['title'] ?? '', 'Ultimate Riches');
            }),
            'all_tickets_with_680k' => $collection->filter(fn($ticket) => 
                isset($ticket['top_grand_prize']) && 
                $ticket['top_grand_prize'] === '$680,000'
            )->pluck('title')->toArray(),
            'total_680k_count' => $collection->filter(fn($ticket) => 
                isset($ticket['top_grand_prize']) && 
                $ticket['top_grand_prize'] === '$680,000'
            )->count()
        ]);
            
        // Debug: Log grand collection details
        Log::info('Grand Collection Debug', [
            'total_tickets' => $collection->count(),
            'tickets_with_grand_prize' => $collection->filter(fn($ticket) => isset($ticket['top_grand_prize']))->count(),
            'grand_collection_size' => $grand->count(),
            'grand_collection_titles' => $grand->pluck('title')->toArray(),
            'grand_collection_prizes' => $grand->pluck('top_grand_prize')->toArray(),
            '300x_in_grand' => $grand->contains(function ($ticket) {
                return str_contains($ticket['title'] ?? '', '300X');
            }),
            'money_maker_in_grand' => $grand->contains(function ($ticket) {
                return str_contains($ticket['title'] ?? '', 'Money Maker');
            }),
            'all_680k_tickets' => $collection->filter(fn($ticket) => 
                isset($ticket['top_grand_prize']) && 
                $ticket['top_grand_prize'] === '$680,000'
            )->pluck('title')->toArray(),
            '300x_ticket_data' => $collection->first(function ($ticket) {
                return str_contains($ticket['title'] ?? '', '300X');
            })
        ]);

        // 🏷 Assign Types and Rankings efficiently
        // Create lookup arrays for faster searching (filter out null/empty URLs)
        $top10Urls = $top10->pluck('url')->filter(function($url) {
            return is_string($url) && !empty($url);
        })->values()->flip()->toArray();
        $newlyUrls = $newly->pluck('url')->filter(function($url) {
            return is_string($url) && !empty($url);
        })->values()->flip()->toArray();
        $grandUrls = $grand->pluck('url')->filter(function($url) {
            return is_string($url) && !empty($url);
        })->values()->flip()->toArray();

        foreach ($results as &$ticket) {
            $ticket['type'] = [];
            $ticket['ranking'] = [];

            // Skip tickets without valid URLs
            if (empty($ticket['url'])) {
                continue;
            }

            // 🎯 Top 10 ROI
            if (isset($top10Urls[$ticket['url']])) {
                $ticket['type'][] = 'top 10';
                $ticket['ranking']['top 10'] = $top10Urls[$ticket['url']] + 1;
            }

            // 🆕 Newly Released
            if (isset($newlyUrls[$ticket['url']])) {
                $ticket['type'][] = 'newly';
                $ticket['ranking']['newly'] = $newlyUrls[$ticket['url']] + 1;
            }

            // 💰 Grand Prize
            if (isset($grandUrls[$ticket['url']])) {
                $ticket['type'][] = 'grand';
                $ticket['ranking']['grand'] = $grandUrls[$ticket['url']] + 1;
                
                // Debug: Log grand prize ranking for specific tickets
                if (str_contains($ticket['title'] ?? '', '300X') || str_contains($ticket['title'] ?? '', 'Money Maker')) {
                    Log::info('Grand Prize Ranking Debug', [
                        'title' => $ticket['title'],
                        'grand_ranking' => $ticket['ranking']['grand'],
                        'grand_type' => in_array('grand', $ticket['type']),
                        'url' => $ticket['url']
                    ]);
                }
            }
        }

        return $results;
    }


    
}

