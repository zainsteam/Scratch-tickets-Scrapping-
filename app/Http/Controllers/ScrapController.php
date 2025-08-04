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
use App\Services\LotteryUrlService;

use App\Exports\TicketsByPriceExport;

class ScrapController extends Controller
{
    private UniversalScrapingService $scrapingService;
    private LotteryUrlService $urlService;
    
    public function __construct(UniversalScrapingService $scrapingService, LotteryUrlService $urlService)
    {
        $this->scrapingService = $scrapingService;
        $this->urlService = $urlService;
    }

    public function exportTickets()
    {
        $scraper = new ScrapController(app(UniversalScrapingService::class), app(LotteryUrlService::class));
        $tickets = json_decode($scraper->getMultipleData()->getContent(), true);
    
        if (empty($tickets)) {
            return response()->json(['error' => 'No tickets available to export.']);
        }
    
        $grouped = collect($tickets)
            ->filter(fn($t) => isset($t['price']) && $t['price'] !== null)
            ->groupBy('price');
    
        return Excel::download(new TicketsExport($grouped), 'tickets_by_price.xlsx');
    }

    /**
     * Export tickets for a specific state
     * 
     * @param string $state The state key (e.g., 'arkansas', 'dc', 'missouri')
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportStateTickets(string $state)
    {
        set_time_limit(0); // Remove time limit for large export operations
        
        try {
            // Validate state
            $stateConfig = $this->urlService->getStateConfig($state);
            if (!$stateConfig) {
                return response()->json([
                    'error' => 'Invalid state',
                    'available_states' => array_keys($this->urlService->getActiveStates())
                ], 400);
            }
            
            if (!($stateConfig['active'] ?? true)) {
                return response()->json([
                    'error' => 'State is not active',
                    'state' => $stateConfig['name']
                ], 400);
            }
            
            Log::info("Starting state export for: {$stateConfig['name']}");
            
            // Get state-specific URLs based on state
            $urls = $this->getStateUrls($state);
            
            if (empty($urls)) {
                return response()->json([
                    'error' => 'No URLs found for state',
                    'state' => $stateConfig['name']
                ], 404);
            }
            
            // Scrape all URLs for this state
            $results = $this->scrapingService->scrapeUrlsInParallel($urls);
            
            if (empty($results)) {
                return response()->json([
                    'error' => 'No data scraped for state',
                    'state' => $stateConfig['name']
                ], 404);
            }
            
            // Process and calculate metrics for each result
            $processedResults = [];
            foreach ($results as $result) {
                if ($result) {
                    $processedResult = $this->calculateTicketMetrics($result);
                    if ($processedResult) {
                        $processedResults[] = $processedResult;
                    }
                }
            }
            
            // Apply ranking algorithms
            $finalResults = $this->processAllTickets($processedResults);
            
            if (empty($finalResults)) {
                return response()->json([
                    'error' => 'No valid tickets found for export',
                    'state' => $stateConfig['name']
                ], 404);
            }
            
            // Group tickets by price for export
            $grouped = collect($finalResults)
                ->filter(fn($t) => isset($t['price']) && $t['price'] !== null)
                ->groupBy('price');
            
            // Generate filename with state name
            $stateName = str_replace(' ', '_', strtolower($stateConfig['name']));
            $filename = "{$stateName}_tickets_" . date('Y-m-d_H-i-s') . ".xlsx";
            
            Log::info("Exporting {$stateConfig['name']} tickets", [
                'total_tickets' => count($finalResults),
                'filename' => $filename
            ]);
            
            return Excel::download(new TicketsExport($grouped), $filename);
            
        } catch (\Exception $e) {
            Log::error("Error exporting state {$state}: " . $e->getMessage());
            return response()->json([
                'error' => 'Error exporting state: ' . $e->getMessage(),
                'state' => $state
            ], 500);
        }
    }

    public function getMultipleData()
    {
        set_time_limit(0); // remove time limit temporarily
        
        // Mixed URLs from different lottery sites
        $urls = [
            // DC Lottery URLs (existing - no change)
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
            
            // Virginia Lottery URLs (existing)
            'https://www.valottery.com/games/scratch-off-games/100x-cash',
            'https://www.valottery.com/games/scratch-off-games/50x-cash',
            'https://www.valottery.com/games/scratch-off-games/20x-cash',
            'https://www.valottery.com/games/scratch-off-games/10x-cash',
            'https://www.valottery.com/games/scratch-off-games/5x-cash',
            
            // Arkansas Lottery URLs
            'https://www.myarkansaslottery.com/games/1000000-jackpot',
            'https://www.myarkansaslottery.com/games/scratch-off-games/100x-cash',
            'https://www.myarkansaslottery.com/games/scratch-off-games/50x-cash',
            'https://www.myarkansaslottery.com/games/scratch-off-games/20x-cash',
            
            // California Lottery URLs
            'https://www.calottery.com/Scratchers/100x-cash',
            'https://www.calottery.com/Scratchers/50x-cash',
            'https://www.calottery.com/Scratchers/20x-cash',
            
            // Connecticut Lottery URLs
            'https://www.ctlottery.org/Scratch-Games/100x-cash',
            'https://www.ctlottery.org/Scratch-Games/50x-cash',
            'https://www.ctlottery.org/Scratch-Games/20x-cash',
            
            // Indiana Lottery URLs
            'https://www.hoosierlottery.com/games/scratch-off-games/100x-cash',
            'https://www.hoosierlottery.com/games/scratch-off-games/50x-cash',
            'https://www.hoosierlottery.com/games/scratch-off-games/20x-cash',
            
            // Kansas Lottery URLs
            'https://www.kslottery.com/Games/Scratch-Games/100x-cash',
            'https://www.kslottery.com/Games/Scratch-Games/50x-cash',
            'https://www.kslottery.com/Games/Scratch-Games/20x-cash',
            
            // Kentucky Lottery URLs
            'https://www.kylottery.com/games/scratch-off-games/100x-cash',
            'https://www.kylottery.com/games/scratch-off-games/50x-cash',
            'https://www.kylottery.com/games/scratch-off-games/20x-cash',
            
            // Louisiana Lottery URLs
            'https://www.louisianalottery.com/games/scratch-off-games/100x-cash',
            'https://www.louisianalottery.com/games/scratch-off-games/50x-cash',
            'https://www.louisianalottery.com/games/scratch-off-games/20x-cash',
            
            // Mississippi Lottery URLs
            'https://www.mslotteryhome.com/games/scratch-off-games/100x-cash',
            'https://www.mslotteryhome.com/games/scratch-off-games/50x-cash',
            'https://www.mslotteryhome.com/games/scratch-off-games/20x-cash',
            
            // New Jersey Lottery URLs
            'https://www.njlottery.com/games/scratch-off-games/100x-cash',
            'https://www.njlottery.com/games/scratch-off-games/50x-cash',
            'https://www.njlottery.com/games/scratch-off-games/20x-cash',
            
            // North Carolina Lottery URLs
            'https://www.nclottery.com/Games/Scratch-Offs/100x-cash',
            'https://www.nclottery.com/Games/Scratch-Offs/50x-cash',
            'https://www.nclottery.com/Games/Scratch-Offs/20x-cash',
            
            // South Carolina Lottery URLs
            'https://www.sceducationlottery.com/Games/Scratch-Offs/100x-cash',
            'https://www.sceducationlottery.com/Games/Scratch-Offs/50x-cash',
            'https://www.sceducationlottery.com/Games/Scratch-Offs/20x-cash',
            
            // Texas Lottery URLs
            'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/100x-cash',
            'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/50x-cash',
            'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/20x-cash',
            
            // West Virginia Lottery URLs
            'https://www.wvlottery.com/Games/Scratch-Offs/100x-cash',
            'https://www.wvlottery.com/Games/Scratch-Offs/50x-cash',
            'https://www.wvlottery.com/Games/Scratch-Offs/20x-cash',
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
    
    /**
     * Get all configured states and their URLs
     */
    public function getConfiguredStates()
    {
        $states = $this->urlService->getActiveStates();
        $stats = $this->urlService->getStateStats();
        
        return response()->json([
            'states' => $states,
            'statistics' => $stats,
            'supported_domains' => $this->urlService->getSupportedDomains()
        ]);
    }
    
    /**
     * Get games list URLs for all active states
     */
    public function getGamesListUrls()
    {
        $urls = $this->urlService->getAllGamesListUrls();
        
        return response()->json([
            'games_list_urls' => $urls,
            'total_states' => count($urls)
        ]);
    }
    
    /**
     * Validate a URL and get state information
     */
    public function validateUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);
        
        $url = $request->input('url');
        $state = $this->urlService->validateUrl($url);
        
        if (!$state) {
            return response()->json([
                'valid' => false,
                'message' => 'URL does not belong to any configured state'
            ], 400);
        }
        
        return response()->json([
            'valid' => true,
            'state' => $state,
            'state_key' => $state['key'],
            'supported' => true
        ]);
    }
    


    public function scrapeSingleSite(Request $request)
    {
        $url = $request->get('url');
        
        if (!$url) {
            return response()->json(['error' => 'URL parameter is required'], 400);
        }
        
        try {
            $result = $this->scrapingService->scrapeSingleUrl($url);
            
            if ($result) {
                $processedResult = $this->calculateTicketMetrics($result);
                return response()->json($processedResult);
            } else {
                return response()->json(['error' => 'Failed to scrape data from URL'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error scraping single site: ' . $e->getMessage());
            return response()->json(['error' => 'Error scraping data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Scrape all tickets for a specific state
     * 
     * @param string $state The state key (e.g., 'arkansas', 'dc', 'missouri')
     * @return \Illuminate\Http\JsonResponse
     */
    public function scrapeState(string $state)
    {
        set_time_limit(0); // Remove time limit for large scraping operations
        
        try {
            // Validate state
            $stateConfig = $this->urlService->getStateConfig($state);
            if (!$stateConfig) {
                return response()->json([
                    'error' => 'Invalid state',
                    'available_states' => array_keys($this->urlService->getActiveStates())
                ], 400);
            }
            
            if (!($stateConfig['active'] ?? true)) {
                return response()->json([
                    'error' => 'State is not active',
                    'state' => $stateConfig['name']
                ], 400);
            }
            
            Log::info("Starting state scraping for: {$stateConfig['name']}");
            
            // Get state-specific URLs based on state
            $urls = $this->getStateUrls($state);
            
            if (empty($urls)) {
                return response()->json([
                    'error' => 'No URLs found for state',
                    'state' => $stateConfig['name']
                ], 404);
            }
            
            // Scrape all URLs for this state
            $results = $this->scrapingService->scrapeUrlsInParallel($urls);
            
            if (empty($results)) {
                return response()->json([
                    'error' => 'No data scraped for state',
                    'state' => $stateConfig['name']
                ], 404);
            }
            
            // Process and calculate metrics for each result
            $processedResults = [];
            foreach ($results as $result) {
                if ($result) {
                    $processedResult = $this->calculateTicketMetrics($result);
                    if ($processedResult) {
                        $processedResults[] = $processedResult;
                    }
                }
            }
            
            // Apply ranking algorithms
            $finalResults = $this->processAllTickets($processedResults);
            
            return response()->json([
                'state' => $stateConfig['name'],
                'state_key' => $state,
                'total_tickets' => count($finalResults),
                'tickets' => $finalResults
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error scraping state {$state}: " . $e->getMessage());
            return response()->json([
                'error' => 'Error scraping state: ' . $e->getMessage(),
                'state' => $state
            ], 500);
        }
    }

    /**
     * Get URLs for a specific state
     * 
     * @param string $state The state key
     * @return array Array of URLs to scrape
     */
    private function getStateUrls(string $state): array
    {
        switch ($state) {
            case 'arkansas':
                return [
                    'https://www.myarkansaslottery.com/games/1000000-jackpot',
                    'https://www.myarkansaslottery.com/games/triple-red-777',
                    'https://www.myarkansaslottery.com/games/diamond-bingo',
                    'https://www.myarkansaslottery.com/games/daily-crossword',
                    'https://www.myarkansaslottery.com/games/bingo-extra-0',
                    'https://www.myarkansaslottery.com/games/300-cash',
                    'https://www.myarkansaslottery.com/games/yahtzee%E2%84%A2-squared',
                    'https://www.myarkansaslottery.com/games/cah-pong',
                    'https://www.myarkansaslottery.com/games/50-blast',
                    'https://www.myarkansaslottery.com/games/cash-spot-0',
                    'https://www.myarkansaslottery.com/games/triple-payout',
                    'https://www.myarkansaslottery.com/games/lucky-7s-0',
                    'https://www.myarkansaslottery.com/games/multiplier-money-0',
                    'https://www.myarkansaslottery.com/games/money-rush',
                    'https://www.myarkansaslottery.com/games/casino-nights',
                    'https://www.myarkansaslottery.com/games/50000-frenzy',
                    'https://www.myarkansaslottery.com/games/1000000-jackpot',
                    'https://www.myarkansaslottery.com/games/jackpot-bucks',
                    'https://www.myarkansaslottery.com/games/200x-cash',
                    'https://www.myarkansaslottery.com/games/xtreme-money',
                    'https://www.myarkansaslottery.com/games/x50',
                    'https://www.myarkansaslottery.com/games/100x-cash',
                    'https://www.myarkansaslottery.com/games/50-or-100-2025-ed',
                    'https://www.myarkansaslottery.com/games/black-gold',
                    'https://www.myarkansaslottery.com/games/5000-frenzy-0',
                    'https://www.myarkansaslottery.com/games/50-or-100-2024-ed',
                    'https://www.myarkansaslottery.com/games/psychedelic-payout',
                    'https://www.myarkansaslottery.com/games/mega-bucks',
                    'https://www.myarkansaslottery.com/games/jurassic-world%E2%84%A2',
                    'https://www.myarkansaslottery.com/games/350000-payout',
                    'https://www.myarkansaslottery.com/games/100000-golden-ticket',
                    'https://www.myarkansaslottery.com/games/red-white-and-blue-7s',
                    'https://www.myarkansaslottery.com/games/500-loaded-1',
                    'https://www.myarkansaslottery.com/games/50x-cash',
                    'https://www.myarkansaslottery.com/games/multiplier-bonus',
                    'https://www.myarkansaslottery.com/games/500-frenzy-0',
                    'https://www.myarkansaslottery.com/games/hit-250-0',
                    'https://www.myarkansaslottery.com/games/diamond-dollars-0',
                    'https://www.myarkansaslottery.com/games/loteria%E2%84%A2-1',
                    'https://www.myarkansaslottery.com/games/lady-luck',
                    'https://www.myarkansaslottery.com/games/mystery-multiplier',
                    'https://www.myarkansaslottery.com/games/crossword-x-tra%C2%AE',
                    'https://www.myarkansaslottery.com/games/200x-win',
                    'https://www.myarkansaslottery.com/games/jewel-7s',
                    'https://www.myarkansaslottery.com/games/silver-gold',
                    'https://www.myarkansaslottery.com/games/20000-money-bag-0',
                    'https://www.myarkansaslottery.com/games/hot-500',
                    'https://www.myarkansaslottery.com/games/6-million-blowout',
                    'https://www.myarkansaslottery.com/games/300000-large',
                    'https://www.myarkansaslottery.com/games/ultimate-riches',
                    'https://www.myarkansaslottery.com/games/big-bucks',
                    'https://www.myarkansaslottery.com/games/x20',
                    'https://www.myarkansaslottery.com/games/multiplier-mania-0',
                    'https://www.myarkansaslottery.com/games/20x-cash',
                    'https://www.myarkansaslottery.com/games/double-match',
                    'https://www.myarkansaslottery.com/games/triple-cash-payout',
                    'https://www.myarkansaslottery.com/games/3-ways-win',
                    'https://www.myarkansaslottery.com/games/200-frenzy-0',
                    'https://www.myarkansaslottery.com/games/froty-doughman',
                    'https://www.myarkansaslottery.com/games/7',
                    'https://www.myarkansaslottery.com/games/jumbo-bucks-bingo-0',
                    'https://www.myarkansaslottery.com/games/money-bags-0',
                    'https://www.myarkansaslottery.com/games/neon-100',
                    'https://www.myarkansaslottery.com/games/x-money-0',
                    'https://www.myarkansaslottery.com/games/10x%C2%AE-cashword',
                    'https://www.myarkansaslottery.com/games/gold-rush-0',
                    'https://www.myarkansaslottery.com/games/5x-luck-0',
                    'https://www.myarkansaslottery.com/games/100x-win',
                    'https://www.myarkansaslottery.com/games/x10',
                    'https://www.myarkansaslottery.com/games/piggy-bank',
                    'https://www.myarkansaslottery.com/games/20x',
                    'https://www.myarkansaslottery.com/games/peppermint-payout-multiplier',
                    'https://www.myarkansaslottery.com/games/100-frenzy-0',
                    'https://www.myarkansaslottery.com/games/super-7s-0',
                    'https://www.myarkansaslottery.com/games/1000000-vip-club',
                    'https://www.myarkansaslottery.com/games/20x-win-0',
                    'https://www.myarkansaslottery.com/games/10x%C2%AE-cash',
                    'https://www.myarkansaslottery.com/games/x50-bonus',
                    'https://www.myarkansaslottery.com/games/777-0',
                ];
                
            case 'dc':
                return [
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
                ];
                
            case 'missouri':
                return [
                    'https://www.molottery.com/games/scratch-off-games/123',
                    'https://www.molottery.com/games/scratch-off-games/456',
                    'https://www.molottery.com/games/scratch-off-games/789',
                    'https://www.molottery.com/games/scratch-off-games/101',
                    'https://www.molottery.com/games/scratch-off-games/102',
                    'https://www.molottery.com/games/scratch-off-games/103',
                    'https://www.molottery.com/games/scratch-off-games/104',
                    'https://www.molottery.com/games/scratch-off-games/105',
                    'https://www.molottery.com/games/scratch-off-games/106',
                    'https://www.molottery.com/games/scratch-off-games/107',
                ];
                
            case 'virginia':
                return [
                    'https://www.valottery.com/games/scratch-off-games/va-001',
                    'https://www.valottery.com/games/scratch-off-games/va-002',
                    'https://www.valottery.com/games/scratch-off-games/va-003',
                    'https://www.valottery.com/games/scratch-off-games/va-004',
                    'https://www.valottery.com/games/scratch-off-games/va-005',
                    'https://www.valottery.com/games/scratch-off-games/va-006',
                    'https://www.valottery.com/games/scratch-off-games/va-007',
                    'https://www.valottery.com/games/scratch-off-games/va-008',
                    'https://www.valottery.com/games/scratch-off-games/va-009',
                    'https://www.valottery.com/games/scratch-off-games/va-010',
                ];
                
            case 'maryland':
                return [
                    'https://www.mdlottery.com/games/scratch-off-games/md-001',
                    'https://www.mdlottery.com/games/scratch-off-games/md-002',
                    'https://www.mdlottery.com/games/scratch-off-games/md-003',
                    'https://www.mdlottery.com/games/scratch-off-games/md-004',
                    'https://www.mdlottery.com/games/scratch-off-games/md-005',
                    'https://www.mdlottery.com/games/scratch-off-games/md-006',
                    'https://www.mdlottery.com/games/scratch-off-games/md-007',
                    'https://www.mdlottery.com/games/scratch-off-games/md-008',
                    'https://www.mdlottery.com/games/scratch-off-games/md-009',
                    'https://www.mdlottery.com/games/scratch-off-games/md-010',
                ];
                
            default:
                Log::warning("No URLs configured for state: {$state}");
                return [];
        }
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
                // Validate that end_date is a proper date format before parsing
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $data['end_date'])) {
                    $endDate = Carbon::createFromFormat('m/d/Y', $data['end_date']);
                    $today = Carbon::now();
                    
                    if ($endDate->isPast()) {
                        // Return null to indicate this ticket should be skipped
                        return null;
                    }
                }
            } catch (\Exception $e) {
                // If date parsing fails, continue with the ticket
                Log::warning('Failed to parse end date: ' . $data['end_date'], ['url' => $data['url']]);
            }
        }
        
        // Cache expensive calculations
        static $calculationCache = [];
        $prizes = $data['prizes'] ?? [];
        $cacheKey = md5($data['url'] . serialize($prizes));
        
        if (isset($calculationCache[$cacheKey])) {
            return $calculationCache[$cacheKey];
        }

        $remainingPrizes = 0;
        if (isset($prizes[0])) {
            $firstRemaining = (int) $prizes[0]['remaining'];
            $firstTotal = (int) $prizes[0]['total'];
    
            if ($firstRemaining >= 3 || $firstRemaining === $firstTotal) {
                $remainingPrizes = array_sum(array_column($prizes, 'remaining'));
            } else {
                $remainingPrizes = array_sum(array_column(array_slice($prizes, 1), 'remaining'));
            }
        }
    
        $data['remaining_prizes'] = $remainingPrizes;
    
        // Optimize array operations
        $sumProduct = 0;
        $sumOfAllCost = 0;
        $firstPrizeRemaining = $prizes[0]['remaining'] ?? 0;
        $firstPrizeTotal = $prizes[0]['total'] ?? 0;
        
        foreach ($prizes as $index => $prize) {
            $amount = floatval(preg_replace('/[^0-9.]/', '', $prize['amount']));
            $sumProduct += $amount * $prize['total'];
            
            // Calculate column1 based on Excel formula
            $remaining = $prize['remaining'];
            $total = $prize['total'];
            // Calculate ratio for Excel formula
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
            
            $prizes[$index]['column1'] = round($column1, 2);
            
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
    
        $probability = $data['probability'] ?? 0;
        $excelRatioV2 = null;
        if ($probability > 0 && $ticketPrice > 0) {
            $probabilityDecimal = $probability / 100;
            if ($probabilityDecimal > 0) {
                $initialPrizes = $data['initial_prizes'] ?? 0;
                $denominator = ($initialPrizes / $probabilityDecimal) * $ticketPrice;
                if ($denominator > 0) {
                    $excelRatioV2 = $sumProduct / $denominator;
                } else {
                    $excelRatioV2 = null;
                }
            } else {
                $excelRatioV2 = null;
            }
        } else {
            $excelRatioV2 = null;
        }
    
        $costToBuyAllRemaining = null;
        if ($probability > 0 && $ticketPrice > 0) {
            $probabilityDecimal = $probability / 100;
            if ($probabilityDecimal > 0) {
                $costToBuyAllRemaining = round(($data['remaining_prizes'] / $probabilityDecimal) * $ticketPrice);
            } else {
                $costToBuyAllRemaining = null;
            }
        }
    
        $totalRemainingTickets = null;
        if ($probability > 0) {
            $probabilityDecimal = $probability / 100;
            if ($probabilityDecimal > 0) {
                $totalRemainingTickets = round($data['remaining_prizes'] / $probabilityDecimal);
            } else {
                $totalRemainingTickets = null;
            }
        }
    
        $score = ($totalRemainingTickets && $costToBuyAllRemaining !== null && $totalRemainingTickets > 0)
            ? round(($sumOfAllCost - $costToBuyAllRemaining) / $totalRemainingTickets, 2)
            : null;
    
        $finalPercentage = null;
        if ($costToBuyAllRemaining > 0) {
            $finalPercentage = round(($sumOfAllCost / $costToBuyAllRemaining) * 100, 2);
        }

        // Update data with processed prizes
        $data['prizes'] = $prizes;

        // Optimize grand prize calculation
        $topGrandPrize = null;
        $initialGrandPrize = null;
        $currentGrandPrize = null;

        if (!empty($prizes)) {
            $highestAmount = 0;
            $highestPrize = null;
            
            foreach ($prizes as $prize) {
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

        // Debug: Log the price value
        Log::info('ScrapController price value: ' . ($data['price'] ?? 'null'));
        
        // Debug: Log prizes data
        Log::info('ScrapController prizes count: ' . count($prizes));
        if (!empty($prizes)) {
            Log::info('ScrapController first prize: ' . json_encode($prizes[0]));
        }
        
        // Debug: Log calculation variables
        Log::info('ScrapController remainingPrizes: ' . $remainingPrizes);
        Log::info('ScrapController sumProduct: ' . $sumProduct);
        Log::info('ScrapController sumOfAllCost: ' . $sumOfAllCost);
        Log::info('ScrapController firstPrizeRemaining: ' . $firstPrizeRemaining);
        Log::info('ScrapController firstPrizeTotal: ' . $firstPrizeTotal);
        
        // Debug: Log ticket price calculation
        $ticketPrice = $data['price'] ? floatval(preg_replace('/[^0-9.]/', '', $data['price'])) : 0;
        Log::info('ScrapController ticketPrice: ' . $ticketPrice);
        
        // Debug: Log probability
        $probability = $data['probability'] ?? 0;
        Log::info('ScrapController probability: ' . $probability);
        
        // Debug: Log excelRatioV2 calculation
        $probabilityDecimal = $probability > 0 ? $probability / 100 : 0;
        Log::info('ScrapController probabilityDecimal: ' . $probabilityDecimal);
        
        $initialPrizes = $data['initial_prizes'] ?? 0;
        $probabilityDecimal = $probability > 0 ? $probability / 100 : 0;
        $denominator = ($probabilityDecimal > 0) ? ($initialPrizes / $probabilityDecimal) * $ticketPrice : null;
        Log::info('ScrapController denominator: ' . ($denominator ?? 'null'));
        
        $excelRatioV2 = $denominator > 0 ? $sumProduct / $denominator : null;
        Log::info('ScrapController excelRatioV2: ' . ($excelRatioV2 ?? 'null'));
        
        // Debug: Log costToBuyAllRemaining calculation
        $costToBuyAllRemaining = $probabilityDecimal > 0 ? round(($data['remaining_prizes'] / $probabilityDecimal) * $ticketPrice) : null;
        Log::info('ScrapController costToBuyAllRemaining: ' . ($costToBuyAllRemaining ?? 'null'));
        
        // Debug: Log totalRemainingTickets calculation
        $totalRemainingTickets = $probabilityDecimal > 0 ? round($data['remaining_prizes'] / $probabilityDecimal) : null;
        Log::info('ScrapController totalRemainingTickets: ' . ($totalRemainingTickets ?? 'null'));
        
        // Debug: Log score calculation
        $score = ($totalRemainingTickets && $costToBuyAllRemaining !== null) ? round(($sumOfAllCost - $costToBuyAllRemaining) / $totalRemainingTickets, 2) : null;
        Log::info('ScrapController score: ' . ($score ?? 'null'));
        
        // Debug: Log finalPercentage calculation
        $finalPercentage = $costToBuyAllRemaining > 0 ? round(($sumOfAllCost / $costToBuyAllRemaining) * 100, 2) : null;
        Log::info('ScrapController finalPercentage: ' . ($finalPercentage ?? 'null'));

        $result = [
            'title' => $data['title']." #".$data['game_no'],
            'image' => $data['image'],
            'price' => $data['price'],
            'game_no' => $data['game_no'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'odds' => $data['odds'] ?? null,
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
            'grand_prize_left' =>  ($initialGrandPrize > 0) ? ($currentGrandPrize / $initialGrandPrize) * 100 : null,
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

