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
            'https://www.myarkansaslottery.com/games/bonus-multiplier',
'https://www.myarkansaslottery.com/games/50-or-100-2023-ed',
'https://www.myarkansaslottery.com/games/200000-max',
'https://www.myarkansaslottery.com/games/jumbo-bucks-extra',
'https://www.myarkansaslottery.com/games/diamond-bingo',
'https://www.myarkansaslottery.com/games/75000-jewels',
'https://www.myarkansaslottery.com/games/lots-500s',
'https://www.myarkansaslottery.com/games/200x',
'https://www.myarkansaslottery.com/games/50-or-100-2024-ed',
'https://www.myarkansaslottery.com/games/10k-stacks',
'https://www.myarkansaslottery.com/games/200x-win',
'https://www.myarkansaslottery.com/games/350000-payout',
'https://www.myarkansaslottery.com/games/money-rush',
'https://www.myarkansaslottery.com/games/1000000-jackpot',
'https://www.myarkansaslottery.com/games/silver-gold',
'https://www.myarkansaslottery.com/games/hit-250-0',
'https://www.myarkansaslottery.com/games/1000000-vip-club',
'https://www.myarkansaslottery.com/games/hot-500',
'https://www.myarkansaslottery.com/games/rose-gold%C2%AE',
'https://www.myarkansaslottery.com/games/ultimate-riches',
'https://www.myarkansaslottery.com/games/bonu-buck',
'https://www.myarkansaslottery.com/games/full-250s',
'https://www.myarkansaslottery.com/games/multiplier-mania-0',
'https://www.myarkansaslottery.com/games/casino-nights',
'https://www.myarkansaslottery.com/games/triple-red-777',
'https://www.myarkansaslottery.com/games/300000-large',
'https://www.myarkansaslottery.com/games/500-cash',
'https://www.myarkansaslottery.com/games/mystery-multiplier',
'https://www.myarkansaslottery.com/games/x50',
'https://www.myarkansaslottery.com/games/6-million-blowout',
'https://www.myarkansaslottery.com/games/peppermint-payout-multiplier',
'https://www.myarkansaslottery.com/games/300-cash',  
'https://www.myarkansaslottery.com/games/black-pearls',
'https://www.myarkansaslottery.com/games/200000-bonus-multiplier-0',
'https://www.myarkansaslottery.com/games/multiplier-money-0',
'https://www.myarkansaslottery.com/games/triple-cash-payout',
'https://www.myarkansaslottery.com/games/froty-doughman',
'https://www.myarkansaslottery.com/games/gold-rush-0',
'https://www.myarkansaslottery.com/games/100000-payout',
'https://www.myarkansaslottery.com/games/more-money',
'https://www.myarkansaslottery.com/games/100-loaded-0',
'https://www.myarkansaslottery.com/games/200-cash',
'https://www.myarkansaslottery.com/games/bingo-extra-0',
'https://www.myarkansaslottery.com/games/20000-money-bag-0',
'https://www.myarkansaslottery.com/games/jumbo-bucks-bingo-0',
'https://www.myarkansaslottery.com/games/5000-cash',
'https://www.myarkansaslottery.com/games/big-777',
'https://www.myarkansaslottery.com/games/cash-lines',
'https://www.myarkansaslottery.com/games/daily-crossword',
'https://www.myarkansaslottery.com/games/x20',
'https://www.myarkansaslottery.com/games/100x-win',
'https://www.myarkansaslottery.com/games/double-platinum',
'https://www.myarkansaslottery.com/games/burning-hot-7s',   
'https://www.myarkansaslottery.com/games/3-ways-win',
'https://www.myarkansaslottery.com/games/white-elephant-0',
'https://www.myarkansaslottery.com/games/50x-win-0',
'https://www.myarkansaslottery.com/games/jumbo-bucks-premium-edition',
'https://www.myarkansaslottery.com/games/piggy-bank',
'https://www.myarkansaslottery.com/games/cah-pong',
'https://www.myarkansaslottery.com/games/x20-bonus',
'https://www.myarkansaslottery.com/games/x10',
'https://www.myarkansaslottery.com/games/cash-spot-0',  
'https://www.myarkansaslottery.com/games/yahtzee%E2%84%A2-squared',
'https://www.myarkansaslottery.com/games/100-cash-0',
'https://www.myarkansaslottery.com/games/beat-house',
'https://www.myarkansaslottery.com/games/20x',  
'https://www.myarkansaslottery.com/games/50-blast',
'https://www.myarkansaslottery.com/games/10x%C2%AE-win',
'https://www.myarkansaslottery.com/games/lucky-7s-0',
'https://www.myarkansaslottery.com/games/x10-bonus',
'https://www.myarkansaslottery.com/games/20x-win-0',
'https://www.myarkansaslottery.com/games/triple-payout',
'https://www.myarkansaslottery.com/games/quick-50',
'https://www.myarkansaslottery.com/games/50000-blast',
'https://www.myarkansaslottery.com/games/big-x',
'https://www.myarkansaslottery.com/games/extra-extra-crossword-0',
'https://www.myarkansaslottery.com/games/x50-bonus',
'https://www.myarkansaslottery.com/games/777-0',
'https://www.myarkansaslottery.com/games/777-0',
'https://www.calottery.com/scratchers/$5/high-5-1619',
'https://www.calottery.com/scratchers/$2/set-for-life-1639',
'https://www.calottery.com/scratchers/$5/the-big-spin-1623',
'https://www.calottery.com/scratchers/$5/lucky-7-1636',
'https://www.calottery.com/scratchers/$5/red-hot-poker-1640',
'https://www.calottery.com/scratchers/$5/ms-pac-man-1614',
'https://www.calottery.com/scratchers/$5/peppermint-payout-1644',
'https://www.calottery.com/scratchers/$5/mega-crossword-1628',
'https://www.calottery.com/scratchers/$5/winner-winner-chicken-dinner-1632',
'https://www.calottery.com/scratchers/$2/ms-pac-man-1613',
'https://www.calottery.com/scratchers/$3/ka-pow-1631',
'https://www.calottery.com/scratchers/$2/double-match-1609',
'https://www.calottery.com/scratchers/$2/pumpkin-patch-cash-1635',
'https://www.calottery.com/scratchers/$3/toad-ally-awesome-crossword-1622',
'https://www.calottery.com/scratchers/$2/dominoes-1621',
'https://www.calottery.com/scratchers/$3/loteria-1610',
'https://www.calottery.com/scratchers/$1/merry-wishes-1643',
'https://www.calottery.com/scratchers/$5/mega-crossword-1583',
'https://www.calottery.com/scratchers/$2/pinball-payout-1630',
'https://www.calottery.com/scratchers/$3/tripling-bonus-crossword-1596',
'https://www.calottery.com/scratchers/$2/beach-bucks-1627',
'https://www.calottery.com/scratchers/$1/lucky-333-1608',
'https://www.calottery.com/scratchers/$1/the-lucky-spot-1555',
'https://www.calottery.com/scratchers/$3/prize-box-bingo-1579',
'https://www.calottery.com/scratchers/$1/crossword-express-1617',
'https://www.calottery.com/scratchers/$5/loteria-extra-1602',
'https://www.calottery.com/scratchers/$1/taco-tripler-1626',
'https://www.calottery.com/scratchers/$30/california-200x-1532',
'https://www.calottery.com/scratchers/$30/$400-million-money-mania-1408',
'https://www.calottery.com/scratchers/$20/lucky-7s-multiplier-1603',
'https://www.calottery.com/scratchers/$20/2024-1594',
'https://www.calottery.com/scratchers/$10/the-perfect-gift-1540',
'https://www.calottery.com/scratchers/$10/ice-cool-1584',
'https://www.calottery.com/scratchers/$5/triple-play-1571',
'https://www.calottery.com/scratchers/$5/5-spot-1575',
'https://www.calottery.com/scratchers/$5/deuces-wild-poker-1605',
'https://www.calottery.com/scratchers/$5/spicy-hot-cash-1580',
'https://www.calottery.com/scratchers/$3/top-secret-crossword-1516',
'https://www.calottery.com/scratchers/$3/tripling-bonus-crossword-1543',
'https://www.calottery.com/scratchers/$3/bee-lucky-crossword-1570',
'https://www.calottery.com/scratchers/$3/15x-1601',
'https://www.calottery.com/scratchers/$2/power-2s-1618',
'https://www.calottery.com/scratchers/$2/$200-frenzy-1604',
'https://www.calottery.com/scratchers/$2/tic-tac-multiplier-1578',
'https://www.calottery.com/scratchers/$30/200x-1638',
'https://www.calottery.com/scratchers/$30/$10000000-super-bonus-1586',
'https://www.calottery.com/scratchers/$25/2025-1646',
'https://www.calottery.com/scratchers/$20/california-state-riches-1612',
'https://www.calottery.com/scratchers/$30/royal-riches-1625',
'https://www.calottery.com/scratchers/$20/instant-prize-crossword-1634',
'https://www.calottery.com/scratchers/$10/$100-million-mega-cash-1637',
'https://www.calottery.com/scratchers/$20/millionaire-maker-1585',
'https://www.calottery.com/scratchers/$20/instant-prize-crossword-1590',
'https://www.calottery.com/scratchers/$20/$100-or-$200-1642',
'https://www.calottery.com/scratchers/$20/win-$100-or-$200-1616',
'https://www.calottery.com/scratchers/$10/loteria-grande-1641',
'https://www.calottery.com/scratchers/$10/mystery-crossword-1598',
'https://www.calottery.com/scratchers/$10/single-double-triple-1633',
'https://www.calottery.com/scratchers/$10/red-hot-cash-1606',
'https://www.calottery.com/scratchers/$10/$1-million-ultimate-cash-1624',
'https://www.calottery.com/scratchers/$10/$50-or-$100-1629',
'https://www.calottery.com/scratchers/$10/winter-magic-multiplier-1645',
'https://www.calottery.com/scratchers/$10/power-10s-1615',
'https://www.calottery.com/scratchers/$20/100x-1577',
'https://www.calottery.com/scratchers/$20/year-of-fortune-1599',
'https://www.calottery.com/scratchers/$10/multiplier-craze-1620',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames/1621',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames/1607',
'https://www.ctlottery.org/ScratchGames/1651',
'https://www.ctlottery.org/ScratchGames/1651',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames/1629',
'https://www.ctlottery.org/ScratchGames/1524',
'https://www.ctlottery.org/ScratchGames/1628',
'https://www.ctlottery.org/ScratchGames/1642',
'https://www.ctlottery.org/ScratchGames/1651',
'https://www.ctlottery.org/ScratchGames/1651',
'https://www.ctlottery.org/ScratchGames/1650',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames/1607',
'https://www.ctlottery.org/ScratchGames/1654',
'https://www.ctlottery.org/ScratchGames/1651',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames',
'https://www.ctlottery.org/ScratchGames/1651',
'https://www.ctlottery.org/ScratchGames/1613',
'https://hoosierlottery.com/games/scratch-off/4000000-fortune/',
'https://hoosierlottery.com/games/scratch-off/extreme-cash/',
'https://hoosierlottery.com/games/scratch-off/treasure-hunt/',
'https://hoosierlottery.com/games/scratch-off/skee-ball/',
'https://hoosierlottery.com/games/scratch-off/winfall-(1)/',
'https://hoosierlottery.com/games/scratch-off/extreme-green-(1)/',
'https://hoosierlottery.com/games/scratch-off/platinum-payout/',
'https://hoosierlottery.com/games/scratch-off/supreme-cash/',
'https://hoosierlottery.com/games/scratch-off/black-diamond-dazzler/',
'https://hoosierlottery.com/games/scratch-off/very-merry-crossword/',
'https://hoosierlottery.com/games/scratch-off/2024/',
'https://hoosierlottery.com/games/scratch-off/double-sided-dollars/',
'https://hoosierlottery.com/games/scratch-off/colossal-cash/',
'https://hoosierlottery.com/games/scratch-off/200x-the-cash/',
'https://hoosierlottery.com/games/scratch-off/cash-extravaganza/',
'https://hoosierlottery.com/games/scratch-off/500-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/electric-7s-(1)/',
'https://hoosierlottery.com/games/scratch-off/10000-loaded/',
'https://hoosierlottery.com/games/scratch-off/wild-cherry-crossword-tri-(2)/',
'https://hoosierlottery.com/games/scratch-off/7-(1)/',
'https://hoosierlottery.com/games/scratch-off/emerald-mine/',
'https://hoosierlottery.com/games/scratch-off/super-cash-blowout/',
'https://hoosierlottery.com/games/scratch-off/plus-the-money/',
'https://hoosierlottery.com/games/scratch-off/mega-money-(1)/',
'https://hoosierlottery.com/games/scratch-off/500000-fortune/',
'https://hoosierlottery.com/games/scratch-off/holiday-cash-blowout-(2)/',
'https://hoosierlottery.com/games/scratch-off/indiana-cash-blowout-(2)/',
'https://hoosierlottery.com/games/scratch-off/lady-luck-(2)/',
'https://hoosierlottery.com/games/scratch-off/wild-cherry-crossword-5x/',
'https://hoosierlottery.com/games/scratch-off/bonus-multiplier/',
'https://hoosierlottery.com/games/scratch-off/wild-cherry-crossword-10x/',
'https://hoosierlottery.com/games/scratch-off/premium-play/',
'https://hoosierlottery.com/games/scratch-off/pink-diamond-doubler/',
'https://hoosierlottery.com/games/scratch-off/double-red-77/',
'https://hoosierlottery.com/games/scratch-off/red-hot-millions/',
'https://hoosierlottery.com/games/scratch-off/merry-multiplier/',
'https://hoosierlottery.com/games/scratch-off/300000-jumbo-cash/',
'https://hoosierlottery.com/games/scratch-off/chrome/',
'https://hoosierlottery.com/games/scratch-off/cash-vault/',
'https://hoosierlottery.com/games/scratch-off/200-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/neon-9s-crossword/',
'https://hoosierlottery.com/games/scratch-off/fat-wallet/',
'https://hoosierlottery.com/games/scratch-off/20x-the-cash-(1)/',
'https://hoosierlottery.com/games/scratch-off/white-ice-(1)/',
'https://hoosierlottery.com/games/scratch-off/scorching-7s/',
'https://hoosierlottery.com/games/scratch-off/poker-night-(1)/',
'https://hoosierlottery.com/games/scratch-off/gold-bar-7s/',
'https://hoosierlottery.com/games/scratch-off/jokers-wild-poker/',
'https://hoosierlottery.com/games/scratch-off/triple-golden-cherries/',
'https://hoosierlottery.com/games/scratch-off/high-roller/',
'https://hoosierlottery.com/games/scratch-off/six-figures/',
'https://hoosierlottery.com/games/scratch-off/gold-mine/',
'https://hoosierlottery.com/games/scratch-off/linked-wins/',
'https://hoosierlottery.com/games/scratch-off/bonus-money/',
'https://hoosierlottery.com/games/scratch-off/money-bags-(2)/',
'https://hoosierlottery.com/games/scratch-off/double-the-money-(2)/',
'https://hoosierlottery.com/games/scratch-off/power-5s/',
'https://hoosierlottery.com/games/scratch-off/titanium-tripler/',
'https://hoosierlottery.com/games/scratch-off/triple-red-777/',
'https://hoosierlottery.com/games/scratch-off/holiday-crossword-doubler/',
'https://hoosierlottery.com/games/scratch-off/in-the-green/',
'https://hoosierlottery.com/games/scratch-off/sapphire-7s/',
'https://hoosierlottery.com/games/scratch-off/elf/',
'https://hoosierlottery.com/games/scratch-off/moola/',
'https://hoosierlottery.com/games/scratch-off/ruby-red-tripler/',
'https://hoosierlottery.com/games/scratch-off/50-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/super-7s-(1)/',
'https://hoosierlottery.com/games/scratch-off/team-usa/',
'https://hoosierlottery.com/games/scratch-off/emerald-7s/',
'https://hoosierlottery.com/games/scratch-off/10x-the-money/',
'https://hoosierlottery.com/games/scratch-off/loteria/',
'https://hoosierlottery.com/games/scratch-off/bingo-frenzy/',
'https://hoosierlottery.com/games/scratch-off/jaws/',
'https://hoosierlottery.com/games/scratch-off/monster-mah/',
'https://hoosierlottery.com/games/scratch-off/supreme-gold/',
'https://hoosierlottery.com/games/scratch-off/50-frenzy/',
'https://hoosierlottery.com/games/scratch-off/thunder-struck/',
'https://hoosierlottery.com/games/scratch-off/20-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/triple-tripler/',
'https://hoosierlottery.com/games/scratch-off/hot-100s/',
'https://hoosierlottery.com/games/scratch-off/diamond-dash/',
'https://hoosierlottery.com/games/scratch-off/triple-diamond-payout/',
'https://www.kslottery.com/games/instants/?gameid=290',
'https://www.kslottery.com/games/instants/?gameid=291',
'https://www.kslottery.com/games/instants/?gameid=329',
'https://www.kslottery.com/games/instants/?gameid=319',
'https://www.kslottery.com/games/instants/?gameid=375',
'https://www.kslottery.com/games/instants/?gameid=350',
'https://www.kslottery.com/games/instants/?gameid=378',
'https://www.kslottery.com/games/instants/?gameid=379',
'https://www.kslottery.com/games/instants/?gameid=262',
'https://www.kslottery.com/games/instants/?gameid=371',
'https://www.kslottery.com/games/instants/?gameid=432',
'https://www.kslottery.com/games/instants/?gameid=347',
'https://www.kslottery.com/games/instants/?gameid=346',
'https://www.kslottery.com/games/instants/?gameid=332',
'https://www.kslottery.com/games/instants/?gameid=331',
'https://www.kslottery.com/games/instants/?gameid=399',
'https://www.kslottery.com/games/instants/?gameid=431',
'https://www.kslottery.com/games/instants/?gameid=239',
'https://www.kslottery.com/games/instants/?gameid=374',
'https://www.kslottery.com/games/instants/?gameid=398',
'https://www.kslottery.com/games/instants/?gameid=370',
'https://www.kslottery.com/games/instants/?gameid=427',
'https://www.kslottery.com/games/instants/?gameid=397',
'https://www.kslottery.com/games/instants/?gameid=333',
'https://www.kslottery.com/games/instants/?gameid=265',
'https://www.kslottery.com/games/instants/?gameid=245',
'https://www.kslottery.com/games/instants/?gameid=255',
'https://www.kslottery.com/games/instants/?gameid=344',
'https://www.kslottery.com/games/instants/?gameid=386',
'https://www.kslottery.com/games/instants/?gameid=430',
'https://www.kslottery.com/games/instants/?gameid=429',
'https://www.kslottery.com/games/instants/?gameid=263',
'https://www.kslottery.com/games/instants/?gameid=330',
'https://www.kslottery.com/games/instants/?gameid=395',
'https://www.kslottery.com/games/instants/?gameid=409',
'https://www.kslottery.com/games/instants/?gameid=387',
'https://www.kslottery.com/games/instants/?gameid=393',
'https://www.kslottery.com/games/instants/?gameid=388',
'https://www.kslottery.com/games/instants/?gameid=312',
'https://www.kslottery.com/games/instants/?gameid=394',
'https://www.kslottery.com/games/instants/?gameid=358',
'https://www.kslottery.com/games/instants/?gameid=377',
'https://www.kslottery.com/games/instants/?gameid=334',
'https://www.kslottery.com/games/instants/?gameid=428',
'https://www.kslottery.com/games/instants/?gameid=256',
'https://www.kslottery.com/games/instants/?gameid=266',
'https://www.kslottery.com/games/instants/?gameid=310',
'https://www.kslottery.com/games/instants/?gameid=323',
'https://www.kslottery.com/games/instants/?gameid=392',
'https://www.kslottery.com/games/instants/?gameid=321',
'https://www.kslottery.com/games/instants/?gameid=311',
'https://www.kslottery.com/games/instants/?gameid=376',
'https://www.kslottery.com/games/instants/?gameid=308',
'https://www.kslottery.com/games/instants/?gameid=384',
'https://www.kslottery.com/games/instants/?gameid=349',
'https://www.kslottery.com/games/instants/?gameid=229',
'https://www.kslottery.com/games/instants/?gameid=307',
'https://www.kslottery.com/games/instants/?gameid=306',
'https://www.kylottery.com/apps/scratch_offs/games/Max-A-Million_747',
'https://www.kylottery.com/apps/scratch_offs/games/MegaMultiplier_879',
'https://www.kylottery.com/apps/scratch_offs/games/JackpotFortune_809',
'https://www.kylottery.com/apps/scratch_offs/games/BreakFortKnox_714',
'https://www.kylottery.com/apps/scratch_offs/games/50000Cash_896',
'https://www.kylottery.com/apps/scratch_offs/games/DiamondDazzler_810',
'https://www.kylottery.com/apps/scratch_offs/games/FastestRoadto$3Million_757',
'https://www.kylottery.com/apps/scratch_offs/games/GoldRush_761',
'https://www.kylottery.com/apps/scratch_offs/games/PreciousMetalsGold_889',
'https://www.kylottery.com/apps/scratch_offs/games/24KaratGold_817',
'https://www.kylottery.com/apps/scratch_offs/games/WildNumbers20X',
'https://www.kylottery.com/apps/scratch_offs/games/Crossword_758',
'https://www.kylottery.com/apps/scratch_offs/games/GoForTheGreen_876',
'https://www.kylottery.com/apps/scratch_offs/games/Fast5X_753',
'https://www.kylottery.com/apps/scratch_offs/games/100sInAFlash_790',
'https://www.kylottery.com/apps/scratch_offs/games/WildNumbers10X',
'https://www.kylottery.com/apps/scratch_offs/games/20X_773',
'https://www.kylottery.com/apps/scratch_offs/games/Ultimate7s_844',
'https://www.kylottery.com/apps/scratch_offs/games/WhiteElephant_862',
'https://www.kylottery.com/apps/scratch_offs/games/Fast50_881',
'https://www.kylottery.com/apps/scratch_offs/games/PreciousMetalsCopper_880',
'https://www.kylottery.com/apps/scratch_offs/games/DidIWin_805',
'https://www.kylottery.com/apps/scratch_offs/games/WildNumbers5X',
'https://www.kylottery.com/apps/scratch_offs/games/50Loaded_838',
'https://www.kylottery.com/apps/scratch_offs/games/GrillinAndChillin_885',
'https://www.kylottery.com/apps/scratch_offs/games/Vegas_866',
'https://www.kylottery.com/apps/scratch_offs/games/GraveyardGreen_898',
'https://www.kylottery.com/apps/scratch_offs/games/WildNumbers50X',
'https://www.kylottery.com/apps/scratch_offs/games/Frankenbucks_857',
'https://www.kylottery.com/apps/scratch_offs/games/500Loaded_840',
'https://www.kylottery.com/apps/scratch_offs/games/CashPlus_884',
'https://www.kylottery.com/apps/scratch_offs/games/Lucky13_897',
'https://www.kylottery.com/apps/scratch_offs/games/MillionaireClub_904',
'https://www.kylottery.com/apps/scratch_offs/games/Bingo_759',
'https://www.kylottery.com/apps/scratch_offs/games/$5000000Fortune_953',
'https://www.kylottery.com/apps/scratch_offs/games/50000Cash_896',
'https://www.kylottery.com/apps/scratch_offs/games/Hit5000_941',
'https://www.kylottery.com/apps/scratch_offs/games/250CashBlowout_952',
'https://www.kylottery.com/apps/scratch_offs/games/GoldenCasino_937',
'https://www.kylottery.com/apps/scratch_offs/games/JackpotFrost_960',
'https://www.kylottery.com/apps/scratch_offs/games/HighRoller_832',
'https://www.kylottery.com/apps/scratch_offs/games/100or200_934',
'https://www.kylottery.com/apps/scratch_offs/games/Hit1000_942',
'https://www.kylottery.com/apps/scratch_offs/games/$30MillionPayoutSpectacular_792',
'https://www.kylottery.com/apps/scratch_offs/games/BonusBlowout_930',
'https://www.kylottery.com/apps/scratch_offs/games/1000Loaded_841',
'https://www.kylottery.com/apps/scratch_offs/games/PayoutParty_831',
'https://www.kylottery.com/apps/scratch_offs/games/HIT600_887',
'https://www.kylottery.com/apps/scratch_offs/games/$50$100$500Blowout_788',
'https://www.kylottery.com/apps/scratch_offs/games/Win50_100_500_959',
'https://www.kylottery.com/apps/scratch_offs/games/200KNestEgg_793',
'https://www.kylottery.com/apps/scratch_offs/games/TripleJackpot_746',
'https://www.kylottery.com/apps/scratch_offs/games/ThePerfectGift_911',
'https://www.kylottery.com/apps/scratch_offs/games/WinterCash_963',
'https://www.kylottery.com/apps/scratch_offs/games/50_100_500_828',
'https://www.kylottery.com/apps/scratch_offs/games/GoldRush10_779',
'https://www.kylottery.com/apps/scratch_offs/games/Hit600_943',
'https://www.kylottery.com/apps/scratch_offs/games/RedCherryTripler_925',
'https://www.kylottery.com/apps/scratch_offs/games/KentuckyCelebrationLimitedEdition_932',
'https://www.kylottery.com/apps/scratch_offs/games/NutcrackerCash_908',
'https://www.kylottery.com/apps/scratch_offs/games/Festive250sFrosty250s_957',
'https://www.kylottery.com/apps/scratch_offs/games/StockingStuffer_958',
'https://www.kylottery.com/apps/scratch_offs/games/MultiplierCraze_807',
'https://www.kylottery.com/apps/scratch_offs/games/HolidayGiftPack_535',
'https://www.kylottery.com/apps/scratch_offs/games/2024Doubler_914',
'https://www.kylottery.com/apps/scratch_offs/games/2024Doubler_914',
'https://www.kylottery.com/apps/scratch_offs/games/HitTheJackpot_868',
'https://www.kylottery.com/apps/scratch_offs/games/Hit250_944',
'https://www.kylottery.com/apps/scratch_offs/games/Power10s_806',
'https://www.kylottery.com/apps/scratch_offs/games/TheBig10Ticket_940',
'https://www.kylottery.com/apps/scratch_offs/games/SixFigures_894',
'https://www.kylottery.com/apps/scratch_offs/games/1000000Luck_848',
'https://www.kylottery.com/apps/scratch_offs/games/GoldMine9X_933',
'https://www.kylottery.com/apps/scratch_offs/games/WhenItsGoldOutside_915',
'https://www.kylottery.com/apps/scratch_offs/games/PreciousMetalsTitanium_891',
'https://www.kylottery.com/apps/scratch_offs/games/MoneyBagMultiplier_878',
'https://www.kylottery.com/apps/scratch_offs/games/Fullof$500sLimitedEdition_851',
'https://www.kylottery.com/apps/scratch_offs/games/CashBlast_927',
'https://www.kylottery.com/apps/scratch_offs/games/25DaysofWinningRed_910',
'https://www.kylottery.com/apps/scratch_offs/games/$500FrostyFrenzy_909',
'https://www.kylottery.com/apps/scratch_offs/games/$500HolidayFrenzy_909',
'https://www.kylottery.com/apps/scratch_offs/games/WIldLuckHD_850',
'https://www.kylottery.com/apps/scratch_offs/games/$500Frenzy_926',
'https://www.kylottery.com/apps/scratch_offs/games/Funky5s_837',
'https://www.kylottery.com/apps/scratch_offs/games/RingOfFire_949',
'https://www.kylottery.com/apps/scratch_offs/games/Ghostbusters_555',
'https://www.kylottery.com/apps/scratch_offs/games/SpicyHotCrossword-705',
'https://www.kylottery.com/apps/scratch_offs/games/BlazingHot7s_951',
'https://www.kylottery.com/apps/scratch_offs/games/FirewolfCrossword_950',
'https://www.kylottery.com/apps/scratch_offs/games/Mega7sLimited_846',
'https://www.kylottery.com/apps/scratch_offs/games/MegaMillionaire_808',
'https://www.kylottery.com/apps/scratch_offs/games/Precious7s_554',
'https://www.kylottery.com/apps/scratch_offs/games/BlackPearl_836',
'https://www.kylottery.com/apps/scratch_offs/games/CloverCash_924',
'https://www.kylottery.com/apps/scratch_offs/games/ThePriceIsRight_931',
'https://www.kylottery.com/apps/scratch_offs/games/$100000CasinoNights_935',
'https://www.kylottery.com/apps/scratch_offs/games/MoodMoney_936',
'https://www.kylottery.com/apps/scratch_offs/games/MoneyMultiplierBonus_804',
'https://www.kylottery.com/apps/scratch_offs/games/100XTheCash_920',
'https://www.kylottery.com/apps/scratch_offs/games/NothingButCash_901',
'https://www.kylottery.com/apps/scratch_offs/games/SweetheartCash_912',
'https://www.kylottery.com/apps/scratch_offs/games/CornerCashCrossword_938',
'https://www.kylottery.com/apps/scratch_offs/games/PinkDiamond_903',
'https://www.kylottery.com/apps/scratch_offs/games/VIPPlatinum_534',
'https://www.kylottery.com/apps/scratch_offs/games/WildCashMultiplier_883',
'https://www.kylottery.com/apps/scratch_offs/games/5BreakFortKnox_712',
'https://www.kylottery.com/apps/scratch_offs/games/WinnerWinnerChickenDinner_892',
'https://www.kylottery.com/apps/scratch_offs/games/CashDoubleDoubler_886',
'https://www.kylottery.com/apps/scratch_offs/games/$1000000CasinoNights_939',
'https://www.kylottery.com/apps/scratch_offs/games/300XTheCash_922',
'https://www.kylottery.com/apps/scratch_offs/games/WinWinWin_834',
'https://www.kylottery.com/apps/scratch_offs/games/Block_O_847',
'https://www.kylottery.com/apps/scratch_offs/games/CashEruption_893',
'https://www.kylottery.com/apps/scratch_offs/games/CrazyLuckHD_849',
'https://www.kylottery.com/apps/scratch_offs/games/TwelveElves_962',
'https://www.kylottery.com/apps/scratch_offs/games/Monopoly_770',
'https://www.kylottery.com/apps/scratch_offs/games/200XTheCash_921',
'https://www.kylottery.com/apps/scratch_offs/games/Boom_843',
'https://www.kylottery.com/apps/scratch_offs/games/TheCashWheel_829',
'https://www.kylottery.com/apps/scratch_offs/games/777_916',
'https://www.kylottery.com/apps/scratch_offs/games/50XTheCash_919',
'https://www.kylottery.com/apps/scratch_offs/games/WordGames_929',
'https://www.kylottery.com/apps/scratch_offs/games/TreasureHunt_803',
'https://www.kylottery.com/apps/scratch_offs/games/HolidayCash_961',
'https://www.kylottery.com/apps/scratch_offs/games/ICE_913',
'https://www.kylottery.com/apps/scratch_offs/games/WildNumbers15X',
'https://www.kylottery.com/apps/scratch_offs/games/90000TriplePlay_900',
'https://www.kylottery.com/apps/scratch_offs/games/Hit100_945',
'https://www.kylottery.com/apps/scratch_offs/games/2BreakFortKnox_711',
'https://www.kylottery.com/apps/scratch_offs/games/1BreakFortKnox_710',
'https://www.kylottery.com/apps/scratch_offs/games/Wild8s_696',
'https://www.kylottery.com/apps/scratch_offs/games/30XTheCash_918',
'https://www.kylottery.com/apps/scratch_offs/games/SugarSkullCash_902',
'https://www.kylottery.com/apps/scratch_offs/games/PreciousMetalsSilver_888',
'https://www.kylottery.com/apps/scratch_offs/games/NaughtyorNiceCashword_907',
'https://www.kylottery.com/apps/scratch_offs/games/BoltBucks_852',
'https://www.kylottery.com/apps/scratch_offs/games/HalloweenCash_899',
'https://www.kylottery.com/apps/scratch_offs/games/TriplePlay_821',
'https://www.kylottery.com/apps/scratch_offs/games/CountDeMoney_947',
'https://www.kylottery.com/apps/scratch_offs/games/LuckyClovers_923',
'https://www.kylottery.com/apps/scratch_offs/games/GiftTagCash_906',
'https://www.kylottery.com/apps/scratch_offs/games/100Loaded_839',
'https://www.kylottery.com/apps/scratch_offs/games/BigMoneySpectacular_928',
'https://www.kylottery.com/apps/scratch_offs/games/JackOLanternJackpot_948',
'https://www.kylottery.com/apps/scratch_offs/games/20XTheCash_917',
'https://www.kylottery.com/apps/scratch_offs/games/Loose_Change_-_680_680.html',
'https://www.kylottery.com/apps/scratch_offs/games/BettyBoop_905',
'https://www.kylottery.com/apps/scratch_offs/games/7.11.21_895',
'https://www.kylottery.com/apps/scratch_offs/games/GroovySlingoTrio_818',
'https://www.kylottery.com/apps/scratch_offs/games/SlingoPopTripler_946',
'https://www.louisianalottery.com/scratch-offs/1506/250-000-money-bags',
'https://www.louisianalottery.com/scratch-offs/1521/power-10x',
'https://www.louisianalottery.com/scratch-offs/1528/quick-ca-h',
'https://www.louisianalottery.com/scratch-offs/1536/king-cake-krewe',
'https://www.louisianalottery.com/scratch-offs/1525/snow-much-fun',
'https://www.louisianalottery.com/scratch-offs/1537/my-mardi-krewe',
'https://www.louisianalottery.com/scratch-offs/1529/make-my-week',
'https://www.louisianalottery.com/scratch-offs/1526/snow-much-cash',
'https://www.louisianalottery.com/scratch-offs/1511/wild-10s',
'https://www.louisianalottery.com/scratch-offs/1533/crawfish-crossword',
'https://www.louisianalottery.com/scratch-offs/1524/power-100x',
'https://www.louisianalottery.com/scratch-offs/1561/code-word-crossword',
'https://www.louisianalottery.com/scratch-offs/1539/cash-pin',
'https://www.louisianalottery.com/scratch-offs/1566/it-takes-2',
'https://www.louisianalottery.com/scratch-offs/1535/wild-numbers',
'https://www.louisianalottery.com/scratch-offs/1572/triple-777',
'https://louisianalottery.com/scratch-offs/1581/100x-2/',
'https://louisianalottery.com/scratch-offs/1579/20x-3/',
'https://www.louisianalottery.com/scratch-offs/1573/saints-score',
'https://louisianalottery.com/scratch-offs/1584/jingle-all-the-way/',
'https://louisianalottery.com/scratch-offs/1580/50x-4/',
'https://louisianalottery.com/scratch-offs/1551/match-3-tripler-6/',
'https://louisianalottery.com/scratch-offs/1578/10x-4/',
'https://louisianalottery.com/scratch-offs/1583/more-jingle/',
'https://www.louisianalottery.com/scratch-offs/1576/crossword-cash',
'https://www.louisianalottery.com/scratch-offs/1571/taco-bout-bingo',
'https://www.louisianalottery.com/scratch-offs/1575/add-it-up',
'https://louisianalottery.com/scratch-offs/1582/holiday-jingle/',
'https://louisianalottery.com/scratch-offs/1577/5x-4/',
'https://www.louisianalottery.com/scratch-offs/1569/beginners-luck',
'https://www.louisianalottery.com/scratch-offs/1574/wild-9s',
'https://www.louisianalottery.com/scratch-offs/1570/lucky-13-seasons',
'https://louisianalottery.com/scratch-offs/1617/piggy-bank/',
'https://www.louisianalottery.com/scratch-offs/1554/all-cash',
'https://www.louisianalottery.com/scratch-offs/1619/cool-cat',
'https://www.louisianalottery.com/scratch-offs/1567/50-000-lucky-dog',
'https://www.louisianalottery.com/scratch-offs/1565/red-hot-riches',
'https://louisianalottery.com/scratch-offs/1549/golden-nugget-five/',
'https://www.louisianalottery.com/scratch-offs/1563/super-hot',
'https://louisianalottery.com/scratch-offs/1559/double-it-5/',
'https://www.louisianalottery.com/scratch-offs/tab/1-dollar',
'https://louisianalottery.com/scratch-offs/1531/333-3/',
'https://www.louisianalottery.com/scratch-offs/1550/golden-nugget-200-000',
'https://www.louisianalottery.com/scratch-offs/1548/golden-nugget-14-000',
'https://www.louisianalottery.com/scratch-offs/1557/big-easy-bingo',
'https://www.louisianalottery.com/scratch-offs/1562/250-000-bankroll',
'https://www.louisianalottery.com/scratch-offs/1507/triple-match',
'https://louisianalottery.com/scratch-offs/1543/heads-or-tails-3/',
'https://www.louisianalottery.com/scratch-offs/1530/cash-line-bingo',
'https://www.louisianalottery.com/scratch-offs/1552/7-11-21',
'https://www.louisianalottery.com/scratch-offs/1544/casino-games',
'https://www.louisianalottery.com/scratch-offs/1540/get-100',
'https://www.louisianalottery.com/scratch-offs/1541/find-200',
'https://www.louisianalottery.com/scratch-offs/1546/high-roller',
'https://www.mslotteryhome.com/instantgames/400000-multiplier-mania/',
'https://www.mslotteryhome.com/instantgames/triple-diamond-payout/',
'https://www.mslotteryhome.com/instantgames/100-million-extravaganza/',
'https://www.mslotteryhome.com/instantgames/500000-bonus-multiplier/',
'https://www.mslotteryhome.com/instantgames/mega-money/',
'https://www.mslotteryhome.com/instantgames/cash-winfall/',
'https://www.mslotteryhome.com/instantgames/blistering-hot-7s/',
'https://www.mslotteryhome.com/instantgames/win-it-all/',
'https://www.mslotteryhome.com/instantgames/15000000-blowout/',
'https://www.mslotteryhome.com/instantgames/power-10x/',
'https://www.mslotteryhome.com/instantgames/200000-fortune-2/',
'https://www.mslotteryhome.com/instantgames/platinum-7s/',
'https://www.mslotteryhome.com/instantgames/200000-jackpot/',
'https://www.mslotteryhome.com/instantgames/10000-payout/',
'https://www.mslotteryhome.com/instantgames/200000-multiplier-mania/',
'https://www.mslotteryhome.com/instantgames/jumbo-bucks-3/',
'https://www.mslotteryhome.com/instantgames/gold-7s/',
'https://www.mslotteryhome.com/instantgames/cash-in-a-flash/',
'https://www.mslotteryhome.com/instantgames/double-diamond/',
'https://www.mslotteryhome.com/instantgames/hit-it-big/',
'https://www.mslotteryhome.com/instantgames/power-5s/',
'https://www.mslotteryhome.com/instantgames/ruby-7/',
'https://www.mslotteryhome.com/instantgames/lucky-break/',
'https://www.mslotteryhome.com/instantgames/100000-jackpot-2/',
'https://www.mslotteryhome.com/instantgames/lady-luck/',
'https://www.mslotteryhome.com/instantgames/jumbo-bucks-bonus/',
'https://www.mslotteryhome.com/instantgames/100000-cash/',
'https://www.mslotteryhome.com/instantgames/triple-777-3/',
'https://www.mslotteryhome.com/instantgames/the-addams-family-fortune/',
'https://www.mslotteryhome.com/instantgames/wheel-of-fortune/',
'https://www.mslotteryhome.com/instantgames/bonus-bonanza/',
'https://www.mslotteryhome.com/instantgames/winter-green/',
'https://www.mslotteryhome.com/instantgames/elvis/',
'https://www.mslotteryhome.com/instantgames/blackout-bingo/',
'https://www.mslotteryhome.com/instantgames/sizzling-hot-7s/',
'https://www.mslotteryhome.com/instantgames/100000-multiplier-mania/',
'https://www.mslotteryhome.com/instantgames/power-5x/',
'https://www.mslotteryhome.com/instantgames/150000-big-money/',
'https://www.mslotteryhome.com/instantgames/bingo-3/',
'https://www.mslotteryhome.com/instantgames/skee-ball/',
'https://www.mslotteryhome.com/instantgames/crossword-5/',
'https://www.mslotteryhome.com/instantgames/aces-high/',
'https://www.mslotteryhome.com/instantgames/summer-lucky-times-5/',
'https://www.mslotteryhome.com/instantgames/20000-jackpot/',
'https://www.mslotteryhome.com/instantgames/25000-spectacular/',
'https://www.mslotteryhome.com/instantgames/fat-wallet/',
'https://www.mslotteryhome.com/instantgames/money-multiplier/',
'https://www.mslotteryhome.com/instantgames/tic-tac-bonus/',
'https://www.mslotteryhome.com/instantgames/double-match-2/',
'https://www.mslotteryhome.com/instantgames/9s-in-a-line/',
'https://www.mslotteryhome.com/instantgames/lucky-holiday-bucks/',
'https://www.mslotteryhome.com/instantgames/fast-money/',
'https://www.mslotteryhome.com/instantgames/20000-multiplier-mania/',
'https://www.mslotteryhome.com/instantgames/cah-craze/',
'https://www.mslotteryhome.com/instantgames/win-win-win/',
'https://www.mslotteryhome.com/instantgames/bronze-7s/',
'https://www.mslotteryhome.com/instantgames/double-doubler-2/',
'https://www.mslotteryhome.com/instantgames/beat-the-heat/',
'https://www.mslotteryhome.com/instantgames/mad-money/',
'https://www.mslotteryhome.com/instantgames/festive-50s/',
'https://www.mslotteryhome.com/instantgames/3-times-lucky-2/',
'https://www.mslotteryhome.com/instantgames/triple-it/',
'https://www.mslotteryhome.com/instantgames/lucky-stars/',
'https://www.mslotteryhome.com/instantgames/did-i-win/',
'https://www.njlottery.com/en-us/scratch-offs/01880.html',
'https://www.njlottery.com/en-us/scratch-offs/01879.html',
'https://www.njlottery.com/en-us/scratch-offs/01875.html',
'https://www.njlottery.com/en-us/scratch-offs/01863.html',
'https://www.njlottery.com/en-us/scratch-offs/01871.html',
'https://www.njlottery.com/en-us/scratch-offs/01858.html',
'https://www.njlottery.com/en-us/scratch-offs/01876.html',
'https://www.njlottery.com/en-us/scratch-offs/01862.html',
'https://www.njlottery.com/en-us/scratch-offs/01869.html',
'https://www.njlottery.com/en-us/scratch-offs/01882.html',
'https://www.njlottery.com/en-us/scratch-offs/01890.html',
'https://www.njlottery.com/en-us/scratch-offs/01872.html',
'https://www.njlottery.com/en-us/scratch-offs/01868.html',
'https://www.njlottery.com/en-us/scratch-offs/01889.html',
'https://www.njlottery.com/en-us/scratch-offs/01864.html',
'https://www.njlottery.com/en-us/scratch-offs/01845.html',
'https://www.njlottery.com/en-us/scratch-offs/01878.html',
'https://www.njlottery.com/en-us/scratch-offs/01686.html',
'https://www.njlottery.com/en-us/scratch-offs/01820.html',
'https://www.njlottery.com/en-us/scratch-offs/01840.html',
'https://www.njlottery.com/en-us/scratch-offs/01839.html',
'https://www.njlottery.com/en-us/scratch-offs/01867.html',
'https://www.njlottery.com/en-us/scratch-offs/01877.html',
'https://www.njlottery.com/en-us/scratch-offs/01896.html',
'https://www.njlottery.com/en-us/scratch-offs/01874.html',
'https://www.njlottery.com/en-us/scratch-offs/01816.html',
'https://www.njlottery.com/en-us/scratch-offs/01891.html',
'https://www.njlottery.com/en-us/scratch-offs/01866.html',
'https://www.njlottery.com/en-us/scratch-offs/01812.html',
'https://www.njlottery.com/en-us/scratch-offs/01873.html',
'https://www.njlottery.com/en-us/scratch-offs/01881.html',
'https://www.njlottery.com/en-us/scratch-offs/01844.html',
'https://www.njlottery.com/en-us/scratch-offs/01797.html',
'https://www.njlottery.com/en-us/scratch-offs/01786.html',
'https://www.njlottery.com/en-us/scratch-offs/01785.html',
'https://www.njlottery.com/en-us/scratch-offs/01870.html',
'https://www.njlottery.com/en-us/scratch-offs/01758.html',
'https://www.njlottery.com/en-us/scratch-offs/01811.html',
'https://www.njlottery.com/en-us/scratch-offs/01848.html',
'https://www.njlottery.com/en-us/scratch-offs/01850.html',
'https://www.njlottery.com/en-us/scratch-offs/01801.html',
'https://www.njlottery.com/en-us/scratch-offs/01792.html',
'https://www.njlottery.com/en-us/scratch-offs/01843.html',
'https://www.njlottery.com/en-us/scratch-offs/01751.html',
'https://www.njlottery.com/en-us/scratch-offs/01886.html',
'https://www.njlottery.com/en-us/scratch-offs/01810.html',
'https://www.njlottery.com/en-us/scratch-offs/01851.html',
'https://www.njlottery.com/en-us/scratch-offs/01795.html',
'https://www.njlottery.com/en-us/scratch-offs/01809.html',
'https://www.njlottery.com/en-us/scratch-offs/01854.html',
'https://www.njlottery.com/en-us/scratch-offs/01885.html',
'https://www.njlottery.com/en-us/scratch-offs/01825.html',
'https://www.njlottery.com/en-us/scratch-offs/01856.html',
'https://www.njlottery.com/en-us/scratch-offs/01821.html',
'https://www.njlottery.com/en-us/scratch-offs/01779.html',
'https://www.njlottery.com/en-us/scratch-offs/01841.html',
'https://www.njlottery.com/en-us/scratch-offs/01853.html',
'https://www.njlottery.com/en-us/scratch-offs/01823.html',
'https://www.njlottery.com/en-us/scratch-offs/01861.html',
'https://www.njlottery.com/en-us/scratch-offs/01750.html',
'https://www.njlottery.com/en-us/scratch-offs/01819.html',
'https://www.njlottery.com/en-us/scratch-offs/01857.html',
'https://www.njlottery.com/en-us/scratch-offs/01789.html',
'https://www.njlottery.com/en-us/scratch-offs/01837.html',
'https://www.njlottery.com/en-us/scratch-offs/01846.html',
'https://www.njlottery.com/en-us/scratch-offs/01842.html',
'https://www.njlottery.com/en-us/scratch-offs/01865.html',
'https://www.njlottery.com/en-us/scratch-offs/01834.html',
'https://www.njlottery.com/en-us/scratch-offs/01852.html',
'https://www.njlottery.com/en-us/scratch-offs/01815.html',
'https://www.njlottery.com/en-us/scratch-offs/01847.html',
'https://www.njlottery.com/en-us/scratch-offs/01829.html',
'https://www.njlottery.com/en-us/scratch-offs/01859.html',
'https://www.njlottery.com/en-us/scratch-offs/01817.html',
'https://www.njlottery.com/en-us/scratch-offs/01780.html',
'https://www.njlottery.com/en-us/scratch-offs/01836.html',
'https://www.njlottery.com/en-us/scratch-offs/01832.html',
'https://www.njlottery.com/en-us/scratch-offs/01835.html',
'https://www.njlottery.com/en-us/scratch-offs/01860.html',
'https://www.njlottery.com/en-us/scratch-offs/01855.html',
'https://nclottery.com/scratch-off/936/2000-loaded',
'https://nclottery.com/scratch-off/935/500-loaded',
'https://nclottery.com/scratch-off/934/100-loaded',
'https://nclottery.com/scratch-off/933/50-loaded',
'https://nclottery.com/scratch-off/872/20x-the-cash',
'https://nclottery.com/scratch-off/871/15x-cashword',
'https://nclottery.com/scratch-off/907/nutcracker-cash',
'https://nclottery.com/scratch-off/878/double-match',
'https://nclottery.com/scratch-off/870/10x-the-cash',
'https://nclottery.com/scratch-off/882/100-in-a-flash',
'https://nclottery.com/scratch-off/730/junior-big-ol-bucks',
'https://nclottery.com/scratch-off/897/double-it',
'https://nclottery.com/scratch-off/875/vip-platinum',
'https://nclottery.com/scratch-off/898/lucky-7s-cashword',
'https://nclottery.com/scratch-off/913/10-million-spectacular',
'https://nclottery.com/scratch-off/917/2000000-diamond-deluxe',
'https://nclottery.com/scratch-off/876/5000000-ultimate',
'https://nclottery.com/scratch-off/952/millionaire-bucks',
'https://nclottery.com/scratch-off/925/200x-the-cash',
'https://nclottery.com/scratch-off/774/millionaire-maker',
'https://nclottery.com/scratch-off/951/win-big',
'https://nclottery.com/scratch-off/932/instant-millions',
'https://nclottery.com/scratch-off/904/power-20s',
'https://nclottery.com/scratch-off/948/jumbo-bucks',
'https://nclottery.com/scratch-off/830/scorching-hot-7s',
'https://nclottery.com/scratch-off/943/1000000-cashword',
'https://nclottery.com/scratch-off/944/50000-payout',
'https://nclottery.com/scratch-off/940/supreme-7s',
'https://nclottery.com/scratch-off/892/black-titanium',
'https://nclottery.com/scratch-off/958/merry-multiplier',
'https://nclottery.com/scratch-off/957/holiday-cash-blowout',
'https://nclottery.com/scratch-off/939/80-million-cash-blowout',
'https://nclottery.com/scratch-off/895/80000000-cash-blowout',
'https://nclottery.com/scratch-off/912/emerald-8s',
'https://nclottery.com/scratch-off/868/100x-the-cash',
'https://nclottery.com/scratch-off/896/big-cash-payout',
'https://nclottery.com/scratch-off/928/400000-jackpot',
'https://nclottery.com/scratch-off/805/spectacular-riches',
'https://nclottery.com/scratch-off/884/2000000-riches',
'https://nclottery.com/scratch-off/942/game-of-thrones',
'https://nclottery.com/scratch-off/937/super-loteria',
'https://nclottery.com/scratch-off/718/extreme-cash',
'https://nclottery.com/scratch-off/956/holiday-cash-50x',
'https://nclottery.com/scratch-off/916/money-bag',
'https://nclottery.com/scratch-off/923/bankroll',
'https://nclottery.com/scratch-off/921/20x-the-cash',
'https://nclottery.com/scratch-off/950/cash-plus',
'https://nclottery.com/scratch-off/922/50x-the-cash',
'https://nclottery.com/scratch-off/947/mega-bucks',
'https://nclottery.com/scratch-off/949/xtreme-cashword',
'https://nclottery.com/scratch-off/903/power-cashword',
'https://nclottery.com/scratch-off/846/Platinum',
'https://nclottery.com/scratch-off/900/red-hot-millions',
'https://nclottery.com/scratch-off/929/ultimate-dash',
'https://nclottery.com/scratch-off/888/jumbo-bucks',
'https://nclottery.com/scratch-off/891/multiplier-mania',
'https://nclottery.com/scratch-off/955/holiday-cash-30x',
'https://nclottery.com/scratch-off/826/200x-the-cash',
'https://nclottery.com/scratch-off/924/1-million-loteria',
'https://nclottery.com/scratch-off/954/holiday-cash-20x',
'https://nclottery.com/scratch-off/887/mega-bucks',
'https://nclottery.com/scratch-off/946/junior-big-ol-bucks',
'https://nclottery.com/scratch-off/919/10x-the-cash',
'https://nclottery.com/scratch-off/931/red-hot-slots',
'https://nclottery.com/scratch-off/911/loteria',
'https://nclottery.com/scratch-off/902/power-5s',
'https://nclottery.com/scratch-off/920/15x-cashword',
'https://nclottery.com/scratch-off/930/skee-ball',
'https://nclottery.com/scratch-off/927/payday',
'https://nclottery.com/scratch-off/918/5x-the-cash',
'https://nclottery.com/scratch-off/941/tic-tac-bonus',
'https://nclottery.com/scratch-off/953/holiday-cash-10x',
'https://nclottery.com/scratch-off/938/triple-red-777s',
'https://nclottery.com/scratch-off/914/triple-winning-7s',
'https://nclottery.com/scratch-off/915/pot-of-gold',
'https://nclottery.com/scratch-off/926/lucky-7s',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1423',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1545',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1476',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1522',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1576',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1564',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1557',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1552',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1570',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1575',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1554',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1586',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1539',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1591',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1434',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1537',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1585',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1546',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1509',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1527',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1605',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1577',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1590',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1568',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1584',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1589',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1486',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1541',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1567',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1538',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1536',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1533',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1572',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1556',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1560',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1588',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1583',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1563',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1574',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1573',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1399',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1518',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1565',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1503',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1534',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1559',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1551',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1540',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1471',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1532',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1465',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1520',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1562',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1553',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1517',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1550',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1561',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1504',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252702364.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700633.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700663.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/index.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701564.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701621.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700442.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700566.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700599.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700441.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700659.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701433.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700662.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700481.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701561.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699734.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700444.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700418.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700447.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700660.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701499.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700505.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701465.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700666.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700476.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701562.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701659.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700413.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700664.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252705345.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699730.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699704.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700569.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700411.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700448.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700509.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700511.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701652.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700506.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700477.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699731.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699672.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700574.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700570.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700445.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700503.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699735.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700446.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700478.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700542.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700414.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699732.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700538.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701371.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700479.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700417.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701470.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700415.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700601.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700571.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700692.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700568.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700690.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701495.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700694.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699736.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700696.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701467.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700572.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700596.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700507.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700693.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700450.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700603.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699733.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701374.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700472.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700541.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700573.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252702557.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700416.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700508.html',
'https://www.valottery.com/scratchers/2203',
'https://www.valottery.com/scratchers/2249',
'https://www.valottery.com/scratchers/2232',
'https://www.valottery.com/scratchers/2245',
'https://www.valottery.com/scratchers/2254',
'https://www.valottery.com/scratchers/2129',
'https://www.valottery.com/scratchers/2248',
'https://www.valottery.com/scratchers/2283',
'https://www.valottery.com/scratchers/2240',
'https://www.valottery.com/scratchers/2244',
'https://www.valottery.com/scratchers/2219',
'https://www.valottery.com/scratchers/2266',
'https://www.valottery.com/scratchers/2189',
'https://www.valottery.com/scratchers/2258',
'https://www.valottery.com/scratchers/2117',
'https://www.valottery.com/scratchers/2257',
'https://www.valottery.com/scratchers/2211',
'https://www.valottery.com/scratchers/2181',
'https://www.valottery.com/scratchers/2289',
'https://www.valottery.com/scratchers/2287',
'https://www.valottery.com/scratchers/2264',
'https://www.valottery.com/scratchers/2270',
'https://www.valottery.com/scratchers/2260',
'https://www.valottery.com/scratchers/2226',
'https://www.valottery.com/scratchers/2309',
'https://www.valottery.com/scratchers/2300',
'https://www.valottery.com/scratchers/2198',
'https://www.valottery.com/scratchers/2320',
'https://www.valottery.com/scratchers/2299',
'https://www.valottery.com/scratchers/2150',
'https://www.valottery.com/scratchers/2267',
'https://www.valottery.com/scratchers/2193',
'https://www.valottery.com/scratchers/2292',
'https://www.valottery.com/scratchers/2187',
'https://www.valottery.com/scratchers/2241',
'https://www.valottery.com/scratchers/2143',
'https://www.valottery.com/scratchers/2149',
'https://www.valottery.com/scratchers/2200',
'https://www.valottery.com/scratchers/2196',
'https://www.valottery.com/scratchers/2285',
'https://www.valottery.com/scratchers/2151',
'https://www.valottery.com/scratchers/2297',
'https://www.valottery.com/scratchers/2202',
'https://www.valottery.com/scratchers/2195',
'https://www.valottery.com/scratchers/2175',
'https://www.valottery.com/scratchers/2161',
'https://www.valottery.com/scratchers/2236',
'https://www.valottery.com/scratchers/2263',
'https://www.valottery.com/scratchers/2303',
'https://www.valottery.com/scratchers/2148',
'https://www.valottery.com/scratchers/2140',
'https://www.valottery.com/scratchers/2220',
'https://www.valottery.com/scratchers/2185',
'https://www.valottery.com/scratchers/2126',
'https://www.valottery.com/scratchers/2255',
'https://www.valottery.com/scratchers/2318',
'https://www.valottery.com/scratchers/2308',
'https://www.valottery.com/scratchers/2147',
'https://www.valottery.com/scratchers/2146',
'https://www.valottery.com/scratchers/2199',
'https://www.valottery.com/scratchers/2184',
'https://www.valottery.com/scratchers/2188',
'https://www.valottery.com/scratchers/2259',
'https://www.valottery.com/scratchers/2272',
'https://www.valottery.com/scratchers/2182',
'https://www.valottery.com/scratchers/2280',
'https://www.valottery.com/scratchers/2174',
'https://www.valottery.com/scratchers/2136',
'https://www.valottery.com/scratchers/2307',
'https://www.valottery.com/scratchers/2302',
'https://www.valottery.com/scratchers/2304',
'https://www.valottery.com/scratchers/2288',
'https://www.valottery.com/scratchers/2194',
'https://www.valottery.com/scratchers/2274',
'https://www.valottery.com/scratchers/2157',
'https://www.valottery.com/scratchers/2296',
'https://www.valottery.com/scratchers/2291',
'https://www.valottery.com/scratchers/2290',
'https://www.valottery.com/scratchers/2234',
'https://www.valottery.com/scratchers/2279',
'https://www.valottery.com/scratchers/2282',
'https://www.valottery.com/scratchers/2230',
'https://www.valottery.com/scratchers/2269',
'https://www.valottery.com/scratchers/2306',
'https://www.valottery.com/scratchers/2284',
'https://www.valottery.com/scratchers/2271',
'https://www.valottery.com/scratchers/2281',
'https://www.valottery.com/scratchers/2276',
'https://www.valottery.com/scratchers/2265',
'https://www.valottery.com/scratchers/2301',
'https://www.valottery.com/scratchers/2156',
'https://www.valottery.com/scratchers/2295',
'https://www.valottery.com/scratchers/2227',
'https://dclottery.com/loaded',
'https://dclottery.com/dc-scratchers/200x',
'https://dclottery.com/dc-scratchers/king-cash-multiplier', 
'https://dclottery.com/dc-scratchers/20-roaring-cash',
'https://dclottery.com/dc-scratchers/electric-diamonds',
'https://dclottery.com/dc-scratchers/50-or-100',
'https://dclottery.com/dc-scratchers/1500-loaded',
'https://dclottery.com/dc-scratchers/power-cash-10x',
'https://dclottery.com/dc-scratchers/mystery-multiplier',
'https://dclottery.com/dc-scratchers/10-40th-anniversary',
'https://dclottery.com/dc-scratchers/big-10',
'https://dclottery.com/dc-scratchers/win-big',
'https://dclottery.com/dc-scratchers/x-ten',
'https://dclottery.com/dc-scratchers/cash-time',
'https://dclottery.com/dc-scratchers/cash-money',
'https://dclottery.com/dc-scratchers/71121',
'https://dclottery.com/dc-scratchers/300x',
'https://dclottery.com/dc-scratchers/200x-0',
'https://dclottery.com/dc-scratchers/1000000-money-maker',
'https://dclottery.com/dc-scratchers/cash-eruption',
'https://dclottery.com/dc-scratchers/strike-it-rich',
'https://dclottery.com/dc-scratchers/hit-1000',
'https://dclottery.com/dc-scratchers/monopoly-1',
'https://dclottery.com/dc-scratchers/one-word-crossword',
'https://dclottery.com/dc-scratchers/massive-money-blowout',
'https://dclottery.com/dc-scratchers/hit-500',
'https://dclottery.com/dc-scratchers/500-frenzy',
'https://dclottery.com/dc-scratchers/holiday-riches',
'https://dclottery.com/dc-scratchers/loteria',
'https://dclottery.com/dc-scratchers/ultimate-riches',
'https://dclottery.com/dc-scratchers/full-5000s',
'https://dclottery.com/dc-scratchers/loteria-1',
'https://dclottery.com/dc-scratchers/platinum-diamond-spectacular',
'https://dclottery.com/dc-scratchers/100x-cash',
'https://dclottery.com/dc-scratchers/tic-tac-multiplier',
'https://dclottery.com/dc-scratchers/snow-much-fun',
'https://dclottery.com/dc-scratchers/winter-winnings',
'https://dclottery.com/dc-scratchers/monopoly',
'https://dclottery.com/dc-scratchers/100-grand',
'https://dclottery.com/dc-scratchers/50x-0',
'https://dclottery.com/dc-scratchers/dc-payout',
'https://dclottery.com/dc-scratchers/did-i-win',
'https://dclottery.com/dc-scratchers/100-loaded',
'https://dclottery.com/dc-scratchers/red-hot-riches',
'https://dclottery.com/dc-scratchers/triple-777-0',
'https://dclottery.com/dc-scratchers/fat-wallet',
'https://dclottery.com/dc-scratchers/100x-1',
'https://dclottery.com/dc-scratchers/hit-100',
'https://dclottery.com/dc-scratchers/25x-cash',
'https://dclottery.com/dc-scratchers/lucky-loot-hd',
'https://dclottery.com/dc-scratchers/red-hot-cash',
'https://dclottery.com/dc-scratchers/triple-333-0',
'https://dclottery.com/dc-scratchers/10x-cash',
'https://dclottery.com/dc-scratchers/win-it-all-jackpot',
'https://dclottery.com/dc-scratchers/202-2nd-edition',
'https://dclottery.com/dc-scratchers/100-frenzy',
'https://dclottery.com/dc-scratchers/double-cash-doubler',
'https://dclottery.com/dc-scratchers/uno',
'https://dclottery.com/dc-scratchers/2024-make-my-year',
'https://dclottery.com/dc-scratchers/triple-555',
'https://dclottery.com/dc-scratchers/monopoly-0',   
'https://dclottery.com/dc-scratchers/50000-vip-cashword',
'https://dclottery.com/dc-scratchers/diamonds',
'https://dclottery.com/dc-scratchers/power-cash-2x',
'https://dclottery.com/dc-scratchers/jumbo-bucks-supreme',
'https://dclottery.com/dc-scratchers/ruby-red-7s',
'https://dclottery.com/dc-scratchers/fireball-5s',
'https://dclottery.com/dc-scratchers/double-sided-dollar',
'https://dclottery.com/dc-scratchers/twisted-treasure',
'https://dclottery.com/dc-scratchers/win-it-all',
'https://dclottery.com/dc-scratchers/aces-8s',
'https://dclottery.com/dc-scratchers/stocking-stuffer',
'https://dclottery.com/dc-scratchers/double-deuces',
'https://dclottery.com/dc-scratchers/1-40th-anniversary',
'https://dclottery.com/dc-scratchers/lucky-letter-crossword',
'https://dclottery.com/dc-scratchers/dc-cah',
'https://dclottery.com/dc-scratchers/bingo-plus',
'https://dclottery.com/dc-scratchers/triple-333',
'https://dclottery.com/dc-scratchers/power-cash',
'https://dclottery.com/dc-scratchers/holiday-wishes',
'https://dclottery.com/dc-scratchers/match-2-win',
'https://dclottery.com/dc-scratchers/electric-8s',
'https://dclottery.com/dc-scratchers/5x-cash',
'https://dclottery.com/dc-scratchers/5-star-crossword',
'https://dclottery.com/dc-scratchers/easy-money',
'https://dclottery.com/dc-scratchers/20x-0',
'https://dclottery.com/dc-scratchers/money-talks',
'https://dclottery.com/dc-scratchers/capital-fortune',
'https://dclottery.com/dc-scratchers/lucky-7s',
'https://dclottery.com/dc-scratchers/merry-money-multiplier',
'https://dclottery.com/dc-scratchers/emerald-8s',
'https://dclottery.com/dc-scratchers/red-hot-double-doubler-1',
'https://dclottery.com/dc-scratchers/topaz-7s',
'https://dclottery.com/dc-scratchers/triple-777',
'https://dclottery.com/dc-scratchers/10x-0',
'https://dclottery.com/dc-scratchers/lady-luck-0',
'https://dclottery.com/dc-scratchers/mad-money',
'https://dclottery.com/dc-scratchers/hot-8',
'https://dclottery.com/dc-scratchers/hit-50',
'https://dclottery.com/dc-scratchers/10-million-cash-extravaganza',
'https://dclottery.com/dc-scratchers/sapphire-6s',
'https://wvlottery.com/instant-games/supreme-cash-golden/',
'https://wvlottery.com/instant-games/200000-jackpot/',
'https://wvlottery.com/instant-games/50-or-100-1105/',
'https://wvlottery.com/instant-games/camo-cw-crossword/',
'https://wvlottery.com/instant-games/big-halloween-treat-big-package/',
'https://wvlottery.com/instant-games/candy-corn-chocolate-cw/',
'https://wvlottery.com/instant-games/extra-joker-crossword/',
'https://wvlottery.com/instant-games/untapped-fortune-roll-for-riches/',
'https://wvlottery.com/instant-games/electric-luck-lucky-shamrock/',
'https://wvlottery.com/instant-games/dragons-hoard/',
'https://wvlottery.com/instant-games/high-hand/',
'https://wvlottery.com/instant-games/colossal-cash/',
'https://wvlottery.com/instant-games/hit-the-jackpot/',
'https://wvlottery.com/instant-games/wizards-keep/',
'https://wvlottery.com/instant-games/mega-hot/',
'https://wvlottery.com/instant-games/bonus-vip-blowout-club-spin/',
'https://wvlottery.com/games/scratch-offs/green-seeing-dog-mountain',
'https://wvlottery.com/instant-games/speedrun/',
'https://wvlottery.com/games/scratch-offs/xxxxx',
'https://wvlottery.com/games/scratch-offs/sizzlin-hot',
'https://wvlottery.com/games/scratch-offs/cashferatu-holiday-wishes',
'https://wvlottery.com/instant-games/cashferatu-holiday-wishes/',
'https://wvlottery.com/instant-games/jumbo-stacks-bonus-dash/',
'https://wvlottery.com/instant-games/slots-of-cash/',
'https://wvlottery.com/instant-games/xxxx/',
'https://wvlottery.com/instant-games/lady-luck/',
'https://wvlottery.com/instant-games/centipede-frogger-galaga/',
'https://wvlottery.com/instant-games/king-future-gold-neon-keno/',
'https://wvlottery.com/instant-games/big-7s-1195/',
'https://wvlottery.com/instant-games/dark-woods-trove/',
'https://wvlottery.com/instant-games/high-roller-jackpot/',
'https://wvlottery.com/instant-games/lucky-gem-overload-keno/',
'https://wvlottery.com/instant-games/big-sweet-big-gems/',
'https://wvlottery.com/instant-games/mega-lucky-7s/',
'https://wvlottery.com/instant-games/fruit-explosion-lucky-lines/',
'https://wvlottery.com/instant-games/20x-lucky/',
'https://wvlottery.com/instant-games/xxx/',
'https://wvlottery.com/instant-games/cah-pop/',
'https://wvlottery.com/instant-games/cosmic-groovy-alien-winner-keno/',
'https://wvlottery.com/instant-games/shreddin-watercolors-cw/',
'https://wvlottery.com/instant-games/sevens-1166/',
'https://wvlottery.com/instant-games/10k-cash/',
'https://wvlottery.com/instant-games/bear-sweet-burger-water-bingo/',
'https://wvlottery.com/instant-games/viva-las-keno-1190/',
'https://wvlottery.com/instant-games/cash-adventures-3/',
'https://wvlottery.com/instant-games/really-hot/',
'https://wvlottery.com/instant-games/swamp-money-holiday-sevens/',
'https://wvlottery.com/instant-games/cash-frenzy-2/',
'https://wvlottery.com/instant-games/2x-lucky-fab-4/',
'https://wvlottery.com/instant-games/royal-deuces-rollin-blackjack/',
'https://wvlottery.com/instant-games/xx-2/',
'https://wvlottery.com/instant-games/more-money-money-money/',
'https://wvlottery.com/instant-games/lights-lemon-captain-treasure-cw/',
'https://wvlottery.com/instant-games/50000-cash-2/',
'https://wvlottery.com/games/scratch-offs/swamp-money-holiday-sevens',
'https://wvlottery.com/instant-games/neferkitty-holiday-greetings/',
'https://wvlottery.com/instant-games/fat-5/',
'https://wvlottery.com/instant-games/heart-pinball-luck-red-keno/',
'https://wvlottery.com/games/scratch-offs/neferkitty-holiday-greetings',
'https://wvlottery.com/instant-games/jungle-night-popcorn-spot-bingo/',
'https://wvlottery.com/instant-games/big-7s-1162/',
'https://wvlottery.com/instant-games/honey-corgi-cap-banana/',
'https://wvlottery.com/instant-games/lucky-3-in-a-row/',
'https://wvlottery.com/instant-games/x-2/',
'https://wvlottery.com/instant-games/sudden-victory/',
'https://wvlottery.com/instant-games/hot-rains-smore-salsa/',
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
        // Allow long-running single scrape without PHP timing out
        set_time_limit(0);

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
            
            case 'california':
                // California Scratchers detail pages
                return [
             'https://www.calottery.com/scratchers/$5/high-5-1619',
'https://www.calottery.com/scratchers/$2/set-for-life-1639',
'https://www.calottery.com/scratchers/$5/the-big-spin-1623',
'https://www.calottery.com/scratchers/$5/lucky-7-1636',
'https://www.calottery.com/scratchers/$5/red-hot-poker-1640',
'https://www.calottery.com/scratchers/$5/ms-pac-man-1614',
'https://www.calottery.com/scratchers/$5/peppermint-payout-1644',
'https://www.calottery.com/scratchers/$5/mega-crossword-1628',
'https://www.calottery.com/scratchers/$5/winner-winner-chicken-dinner-1632',
'https://www.calottery.com/scratchers/$2/ms-pac-man-1613',
'https://www.calottery.com/scratchers/$3/ka-pow-1631',
'https://www.calottery.com/scratchers/$2/double-match-1609',
'https://www.calottery.com/scratchers/$2/pumpkin-patch-cash-1635',
'https://www.calottery.com/scratchers/$3/toad-ally-awesome-crossword-1622',
'https://www.calottery.com/scratchers/$2/dominoes-1621',
'https://www.calottery.com/scratchers/$3/loteria-1610',
'https://www.calottery.com/scratchers/$1/merry-wishes-1643',
'https://www.calottery.com/scratchers/$5/mega-crossword-1583',
'https://www.calottery.com/scratchers/$2/pinball-payout-1630',
'https://www.calottery.com/scratchers/$3/tripling-bonus-crossword-1596',
'https://www.calottery.com/scratchers/$2/beach-bucks-1627',
'https://www.calottery.com/scratchers/$1/lucky-333-1608',
'https://www.calottery.com/scratchers/$1/the-lucky-spot-1555',
'https://www.calottery.com/scratchers/$3/prize-box-bingo-1579',
'https://www.calottery.com/scratchers/$1/crossword-express-1617',
'https://www.calottery.com/scratchers/$5/loteria-extra-1602',
'https://www.calottery.com/scratchers/$1/taco-tripler-1626',
'https://www.calottery.com/scratchers/$30/california-200x-1532',
'https://www.calottery.com/scratchers/$30/$400-million-money-mania-1408',
'https://www.calottery.com/scratchers/$20/lucky-7s-multiplier-1603',
'https://www.calottery.com/scratchers/$20/2024-1594',
'https://www.calottery.com/scratchers/$10/the-perfect-gift-1540',
'https://www.calottery.com/scratchers/$10/ice-cool-1584',
'https://www.calottery.com/scratchers/$5/triple-play-1571',
'https://www.calottery.com/scratchers/$5/5-spot-1575',
'https://www.calottery.com/scratchers/$5/deuces-wild-poker-1605',
'https://www.calottery.com/scratchers/$5/spicy-hot-cash-1580',
'https://www.calottery.com/scratchers/$3/top-secret-crossword-1516',
'https://www.calottery.com/scratchers/$3/tripling-bonus-crossword-1543',
'https://www.calottery.com/scratchers/$3/bee-lucky-crossword-1570',
'https://www.calottery.com/scratchers/$3/15x-1601',
'https://www.calottery.com/scratchers/$2/power-2s-1618',
'https://www.calottery.com/scratchers/$2/$200-frenzy-1604',
'https://www.calottery.com/scratchers/$2/tic-tac-multiplier-1578',
'https://www.calottery.com/scratchers/$30/200x-1638',
'https://www.calottery.com/scratchers/$30/$10000000-super-bonus-1586',
'https://www.calottery.com/scratchers/$25/2025-1646',
'https://www.calottery.com/scratchers/$20/california-state-riches-1612',
'https://www.calottery.com/scratchers/$30/royal-riches-1625',
'https://www.calottery.com/scratchers/$20/instant-prize-crossword-1634',
'https://www.calottery.com/scratchers/$10/$100-million-mega-cash-1637',
'https://www.calottery.com/scratchers/$20/millionaire-maker-1585',
'https://www.calottery.com/scratchers/$20/instant-prize-crossword-1590',
'https://www.calottery.com/scratchers/$20/$100-or-$200-1642',
'https://www.calottery.com/scratchers/$20/win-$100-or-$200-1616',
'https://www.calottery.com/scratchers/$10/loteria-grande-1641',
'https://www.calottery.com/scratchers/$10/mystery-crossword-1598',
'https://www.calottery.com/scratchers/$10/single-double-triple-1633',
'https://www.calottery.com/scratchers/$10/red-hot-cash-1606',
'https://www.calottery.com/scratchers/$10/$1-million-ultimate-cash-1624',
'https://www.calottery.com/scratchers/$10/$50-or-$100-1629',
'https://www.calottery.com/scratchers/$10/winter-magic-multiplier-1645',
'https://www.calottery.com/scratchers/$10/power-10s-1615',
'https://www.calottery.com/scratchers/$20/100x-1577',
'https://www.calottery.com/scratchers/$20/year-of-fortune-1599',
'https://www.calottery.com/scratchers/$10/multiplier-craze-1620',
                ];
                
            case 'dc':
                return [
                    'https://dclottery.com/loaded',
'https://dclottery.com/dc-scratchers/200x',
'https://dclottery.com/dc-scratchers/king-cash-multiplier', 
'https://dclottery.com/dc-scratchers/20-roaring-cash',
'https://dclottery.com/dc-scratchers/electric-diamonds',
'https://dclottery.com/dc-scratchers/50-or-100',
'https://dclottery.com/dc-scratchers/1500-loaded',
'https://dclottery.com/dc-scratchers/power-cash-10x',
'https://dclottery.com/dc-scratchers/mystery-multiplier',
'https://dclottery.com/dc-scratchers/10-40th-anniversary',
'https://dclottery.com/dc-scratchers/big-10',
'https://dclottery.com/dc-scratchers/win-big',
'https://dclottery.com/dc-scratchers/x-ten',
'https://dclottery.com/dc-scratchers/cash-time',
'https://dclottery.com/dc-scratchers/cash-money',
'https://dclottery.com/dc-scratchers/71121',
'https://dclottery.com/dc-scratchers/300x',
'https://dclottery.com/dc-scratchers/200x-0',
'https://dclottery.com/dc-scratchers/1000000-money-maker',
'https://dclottery.com/dc-scratchers/cash-eruption',
'https://dclottery.com/dc-scratchers/strike-it-rich',
'https://dclottery.com/dc-scratchers/hit-1000',
'https://dclottery.com/dc-scratchers/monopoly-1',
'https://dclottery.com/dc-scratchers/one-word-crossword',
'https://dclottery.com/dc-scratchers/massive-money-blowout',
'https://dclottery.com/dc-scratchers/hit-500',
'https://dclottery.com/dc-scratchers/500-frenzy',
'https://dclottery.com/dc-scratchers/holiday-riches',
'https://dclottery.com/dc-scratchers/loteria',
'https://dclottery.com/dc-scratchers/ultimate-riches',
'https://dclottery.com/dc-scratchers/full-5000s',
'https://dclottery.com/dc-scratchers/loteria-1',
'https://dclottery.com/dc-scratchers/platinum-diamond-spectacular',
'https://dclottery.com/dc-scratchers/100x-cash',
'https://dclottery.com/dc-scratchers/tic-tac-multiplier',
'https://dclottery.com/dc-scratchers/snow-much-fun',
'https://dclottery.com/dc-scratchers/winter-winnings',
'https://dclottery.com/dc-scratchers/monopoly',
'https://dclottery.com/dc-scratchers/100-grand',
'https://dclottery.com/dc-scratchers/50x-0',
'https://dclottery.com/dc-scratchers/dc-payout',
'https://dclottery.com/dc-scratchers/did-i-win',
'https://dclottery.com/dc-scratchers/100-loaded',
'https://dclottery.com/dc-scratchers/red-hot-riches',
'https://dclottery.com/dc-scratchers/triple-777-0',
'https://dclottery.com/dc-scratchers/fat-wallet',
'https://dclottery.com/dc-scratchers/100x-1',
'https://dclottery.com/dc-scratchers/hit-100',
'https://dclottery.com/dc-scratchers/25x-cash',
'https://dclottery.com/dc-scratchers/lucky-loot-hd',
'https://dclottery.com/dc-scratchers/red-hot-cash',
'https://dclottery.com/dc-scratchers/triple-333-0',
'https://dclottery.com/dc-scratchers/10x-cash',
'https://dclottery.com/dc-scratchers/win-it-all-jackpot',
'https://dclottery.com/dc-scratchers/202-2nd-edition',
'https://dclottery.com/dc-scratchers/100-frenzy',
'https://dclottery.com/dc-scratchers/double-cash-doubler',
'https://dclottery.com/dc-scratchers/uno',
'https://dclottery.com/dc-scratchers/2024-make-my-year',
'https://dclottery.com/dc-scratchers/triple-555',
'https://dclottery.com/dc-scratchers/monopoly-0',   
'https://dclottery.com/dc-scratchers/50000-vip-cashword',
'https://dclottery.com/dc-scratchers/diamonds',
'https://dclottery.com/dc-scratchers/power-cash-2x',
'https://dclottery.com/dc-scratchers/jumbo-bucks-supreme',
'https://dclottery.com/dc-scratchers/ruby-red-7s',
'https://dclottery.com/dc-scratchers/fireball-5s',
'https://dclottery.com/dc-scratchers/double-sided-dollar',
'https://dclottery.com/dc-scratchers/twisted-treasure',
'https://dclottery.com/dc-scratchers/win-it-all',
'https://dclottery.com/dc-scratchers/aces-8s',
'https://dclottery.com/dc-scratchers/stocking-stuffer',
'https://dclottery.com/dc-scratchers/double-deuces',
'https://dclottery.com/dc-scratchers/1-40th-anniversary',
'https://dclottery.com/dc-scratchers/lucky-letter-crossword',
'https://dclottery.com/dc-scratchers/dc-cah',
'https://dclottery.com/dc-scratchers/bingo-plus',
'https://dclottery.com/dc-scratchers/triple-333',
'https://dclottery.com/dc-scratchers/power-cash',
'https://dclottery.com/dc-scratchers/holiday-wishes',
'https://dclottery.com/dc-scratchers/match-2-win',
'https://dclottery.com/dc-scratchers/electric-8s',
'https://dclottery.com/dc-scratchers/5x-cash',
'https://dclottery.com/dc-scratchers/5-star-crossword',
'https://dclottery.com/dc-scratchers/easy-money',
'https://dclottery.com/dc-scratchers/20x-0',
'https://dclottery.com/dc-scratchers/money-talks',
'https://dclottery.com/dc-scratchers/capital-fortune',
'https://dclottery.com/dc-scratchers/lucky-7s',
'https://dclottery.com/dc-scratchers/merry-money-multiplier',
'https://dclottery.com/dc-scratchers/emerald-8s',
'https://dclottery.com/dc-scratchers/red-hot-double-doubler-1',
'https://dclottery.com/dc-scratchers/topaz-7s',
'https://dclottery.com/dc-scratchers/triple-777',
'https://dclottery.com/dc-scratchers/10x-0',
'https://dclottery.com/dc-scratchers/lady-luck-0',
'https://dclottery.com/dc-scratchers/mad-money',
'https://dclottery.com/dc-scratchers/hot-8',
'https://dclottery.com/dc-scratchers/hit-50',
'https://dclottery.com/dc-scratchers/10-million-cash-extravaganza',
'https://dclottery.com/dc-scratchers/sapphire-6s',
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
                    'https://www.valottery.com/scratchers/2203',
'https://www.valottery.com/scratchers/2249',
'https://www.valottery.com/scratchers/2232',
'https://www.valottery.com/scratchers/2245',
'https://www.valottery.com/scratchers/2254',
'https://www.valottery.com/scratchers/2129',
'https://www.valottery.com/scratchers/2248',
'https://www.valottery.com/scratchers/2283',
'https://www.valottery.com/scratchers/2240',
'https://www.valottery.com/scratchers/2244',
'https://www.valottery.com/scratchers/2219',
'https://www.valottery.com/scratchers/2266',
'https://www.valottery.com/scratchers/2189',
'https://www.valottery.com/scratchers/2258',
'https://www.valottery.com/scratchers/2117',
'https://www.valottery.com/scratchers/2257',
'https://www.valottery.com/scratchers/2211',
'https://www.valottery.com/scratchers/2181',
'https://www.valottery.com/scratchers/2289',
'https://www.valottery.com/scratchers/2287',
'https://www.valottery.com/scratchers/2264',
'https://www.valottery.com/scratchers/2270',
'https://www.valottery.com/scratchers/2260',
'https://www.valottery.com/scratchers/2226',
'https://www.valottery.com/scratchers/2309',
'https://www.valottery.com/scratchers/2300',
'https://www.valottery.com/scratchers/2198',
'https://www.valottery.com/scratchers/2320',
'https://www.valottery.com/scratchers/2299',
'https://www.valottery.com/scratchers/2150',
'https://www.valottery.com/scratchers/2267',
'https://www.valottery.com/scratchers/2193',
'https://www.valottery.com/scratchers/2292',
'https://www.valottery.com/scratchers/2187',
'https://www.valottery.com/scratchers/2241',
'https://www.valottery.com/scratchers/2143',
'https://www.valottery.com/scratchers/2149',
'https://www.valottery.com/scratchers/2200',
'https://www.valottery.com/scratchers/2196',
'https://www.valottery.com/scratchers/2285',
'https://www.valottery.com/scratchers/2151',
'https://www.valottery.com/scratchers/2297',
'https://www.valottery.com/scratchers/2202',
'https://www.valottery.com/scratchers/2195',
'https://www.valottery.com/scratchers/2175',
'https://www.valottery.com/scratchers/2161',
'https://www.valottery.com/scratchers/2236',
'https://www.valottery.com/scratchers/2263',
'https://www.valottery.com/scratchers/2303',
'https://www.valottery.com/scratchers/2148',
'https://www.valottery.com/scratchers/2140',
'https://www.valottery.com/scratchers/2220',
'https://www.valottery.com/scratchers/2185',
'https://www.valottery.com/scratchers/2126',
'https://www.valottery.com/scratchers/2255',
'https://www.valottery.com/scratchers/2318',
'https://www.valottery.com/scratchers/2308',
'https://www.valottery.com/scratchers/2147',
'https://www.valottery.com/scratchers/2146',
'https://www.valottery.com/scratchers/2199',
'https://www.valottery.com/scratchers/2184',
'https://www.valottery.com/scratchers/2188',
'https://www.valottery.com/scratchers/2259',
'https://www.valottery.com/scratchers/2272',
'https://www.valottery.com/scratchers/2182',
'https://www.valottery.com/scratchers/2280',
'https://www.valottery.com/scratchers/2174',
'https://www.valottery.com/scratchers/2136',
'https://www.valottery.com/scratchers/2307',
'https://www.valottery.com/scratchers/2302',
'https://www.valottery.com/scratchers/2304',
'https://www.valottery.com/scratchers/2288',
'https://www.valottery.com/scratchers/2194',
'https://www.valottery.com/scratchers/2274',
'https://www.valottery.com/scratchers/2157',
'https://www.valottery.com/scratchers/2296',
'https://www.valottery.com/scratchers/2291',
'https://www.valottery.com/scratchers/2290',
'https://www.valottery.com/scratchers/2234',
'https://www.valottery.com/scratchers/2279',
'https://www.valottery.com/scratchers/2282',
'https://www.valottery.com/scratchers/2230',
'https://www.valottery.com/scratchers/2269',
'https://www.valottery.com/scratchers/2306',
'https://www.valottery.com/scratchers/2284',
'https://www.valottery.com/scratchers/2271',
'https://www.valottery.com/scratchers/2281',
'https://www.valottery.com/scratchers/2276',
'https://www.valottery.com/scratchers/2265',
'https://www.valottery.com/scratchers/2301',
'https://www.valottery.com/scratchers/2156',
'https://www.valottery.com/scratchers/2295',
'https://www.valottery.com/scratchers/2227',
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
            case 'south_carolina':
                return [
                    'https://www.sceducationlottery.com/Games/InstantGame?gameId=1423',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1545',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1476',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1522',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1576',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1564',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1557',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1552',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1570',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1575',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1554',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1586',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1539',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1591',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1434',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1537',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1585',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1546',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1509',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1527',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1605',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1577',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1590',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1568',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1584',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1589',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1486',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1541',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1567',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1538',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1536',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1533',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1572',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1556',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1560',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1588',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1583',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1563',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1574',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1573',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1399',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1518',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1565',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1503',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1534',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1559',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1551',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1540',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1471',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1532',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1465',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1520',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1562',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1553',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1517',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1550',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1561',
'https://www.sceducationlottery.com/Games/InstantGame?gameId=1504',
                ];
            case 'louisiana':
                return [
                    'https://www.louisianalottery.com/scratch-offs/1506/250-000-money-bags',
'https://www.louisianalottery.com/scratch-offs/1521/power-10x',
'https://www.louisianalottery.com/scratch-offs/1528/quick-ca-h',
'https://www.louisianalottery.com/scratch-offs/1536/king-cake-krewe',
'https://www.louisianalottery.com/scratch-offs/1525/snow-much-fun',
'https://www.louisianalottery.com/scratch-offs/1537/my-mardi-krewe',
'https://www.louisianalottery.com/scratch-offs/1529/make-my-week',
'https://www.louisianalottery.com/scratch-offs/1526/snow-much-cash',
'https://www.louisianalottery.com/scratch-offs/1511/wild-10s',
'https://www.louisianalottery.com/scratch-offs/1533/crawfish-crossword',
'https://www.louisianalottery.com/scratch-offs/1524/power-100x',
'https://www.louisianalottery.com/scratch-offs/1561/code-word-crossword',
'https://www.louisianalottery.com/scratch-offs/1539/cash-pin',
'https://www.louisianalottery.com/scratch-offs/1566/it-takes-2',
'https://www.louisianalottery.com/scratch-offs/1535/wild-numbers',
'https://www.louisianalottery.com/scratch-offs/1572/triple-777',
'https://louisianalottery.com/scratch-offs/1581/100x-2/',
'https://louisianalottery.com/scratch-offs/1579/20x-3/',
'https://www.louisianalottery.com/scratch-offs/1573/saints-score',
'https://louisianalottery.com/scratch-offs/1584/jingle-all-the-way/',
'https://louisianalottery.com/scratch-offs/1580/50x-4/',
'https://louisianalottery.com/scratch-offs/1551/match-3-tripler-6/',
'https://louisianalottery.com/scratch-offs/1578/10x-4/',
'https://louisianalottery.com/scratch-offs/1583/more-jingle/',
'https://www.louisianalottery.com/scratch-offs/1576/crossword-cash',
'https://www.louisianalottery.com/scratch-offs/1571/taco-bout-bingo',
'https://www.louisianalottery.com/scratch-offs/1575/add-it-up',
'https://louisianalottery.com/scratch-offs/1582/holiday-jingle/',
'https://louisianalottery.com/scratch-offs/1577/5x-4/',
'https://www.louisianalottery.com/scratch-offs/1569/beginners-luck',
'https://www.louisianalottery.com/scratch-offs/1574/wild-9s',
'https://www.louisianalottery.com/scratch-offs/1570/lucky-13-seasons',
'https://louisianalottery.com/scratch-offs/1617/piggy-bank/',
'https://www.louisianalottery.com/scratch-offs/1554/all-cash',
'https://www.louisianalottery.com/scratch-offs/1619/cool-cat',
'https://www.louisianalottery.com/scratch-offs/1567/50-000-lucky-dog',
'https://www.louisianalottery.com/scratch-offs/1565/red-hot-riches',
'https://louisianalottery.com/scratch-offs/1549/golden-nugget-five/',
'https://www.louisianalottery.com/scratch-offs/1563/super-hot',
'https://louisianalottery.com/scratch-offs/1559/double-it-5/',
'https://www.louisianalottery.com/scratch-offs/tab/1-dollar',
'https://louisianalottery.com/scratch-offs/1531/333-3/',
'https://www.louisianalottery.com/scratch-offs/1550/golden-nugget-200-000',
'https://www.louisianalottery.com/scratch-offs/1548/golden-nugget-14-000',
'https://www.louisianalottery.com/scratch-offs/1557/big-easy-bingo',
'https://www.louisianalottery.com/scratch-offs/1562/250-000-bankroll',
'https://www.louisianalottery.com/scratch-offs/1507/triple-match',
'https://louisianalottery.com/scratch-offs/1543/heads-or-tails-3/',
'https://www.louisianalottery.com/scratch-offs/1530/cash-line-bingo',
'https://www.louisianalottery.com/scratch-offs/1552/7-11-21',
'https://www.louisianalottery.com/scratch-offs/1544/casino-games',
'https://www.louisianalottery.com/scratch-offs/1540/get-100',
'https://www.louisianalottery.com/scratch-offs/1541/find-200',
'https://www.louisianalottery.com/scratch-offs/1546/high-roller',
                ];

                case 'indiana':
                    return [
                        'https://hoosierlottery.com/games/scratch-off/4000000-fortune/',
'https://hoosierlottery.com/games/scratch-off/extreme-cash/',
'https://hoosierlottery.com/games/scratch-off/treasure-hunt/',
'https://hoosierlottery.com/games/scratch-off/skee-ball/',
'https://hoosierlottery.com/games/scratch-off/winfall-(1)/',
'https://hoosierlottery.com/games/scratch-off/extreme-green-(1)/',
'https://hoosierlottery.com/games/scratch-off/platinum-payout/',
'https://hoosierlottery.com/games/scratch-off/supreme-cash/',
'https://hoosierlottery.com/games/scratch-off/black-diamond-dazzler/',
'https://hoosierlottery.com/games/scratch-off/very-merry-crossword/',
'https://hoosierlottery.com/games/scratch-off/2024/',
'https://hoosierlottery.com/games/scratch-off/double-sided-dollars/',
'https://hoosierlottery.com/games/scratch-off/colossal-cash/',
'https://hoosierlottery.com/games/scratch-off/200x-the-cash/',
'https://hoosierlottery.com/games/scratch-off/cash-extravaganza/',
'https://hoosierlottery.com/games/scratch-off/500-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/electric-7s-(1)/',
'https://hoosierlottery.com/games/scratch-off/10000-loaded/',
'https://hoosierlottery.com/games/scratch-off/wild-cherry-crossword-tri-(2)/',
'https://hoosierlottery.com/games/scratch-off/7-(1)/',
'https://hoosierlottery.com/games/scratch-off/emerald-mine/',
'https://hoosierlottery.com/games/scratch-off/super-cash-blowout/',
'https://hoosierlottery.com/games/scratch-off/plus-the-money/',
'https://hoosierlottery.com/games/scratch-off/mega-money-(1)/',
'https://hoosierlottery.com/games/scratch-off/500000-fortune/',
'https://hoosierlottery.com/games/scratch-off/holiday-cash-blowout-(2)/',
'https://hoosierlottery.com/games/scratch-off/indiana-cash-blowout-(2)/',
'https://hoosierlottery.com/games/scratch-off/lady-luck-(2)/',
'https://hoosierlottery.com/games/scratch-off/wild-cherry-crossword-5x/',
'https://hoosierlottery.com/games/scratch-off/bonus-multiplier/',
'https://hoosierlottery.com/games/scratch-off/wild-cherry-crossword-10x/',
'https://hoosierlottery.com/games/scratch-off/premium-play/',
'https://hoosierlottery.com/games/scratch-off/pink-diamond-doubler/',
'https://hoosierlottery.com/games/scratch-off/double-red-77/',
'https://hoosierlottery.com/games/scratch-off/red-hot-millions/',
'https://hoosierlottery.com/games/scratch-off/merry-multiplier/',
'https://hoosierlottery.com/games/scratch-off/300000-jumbo-cash/',
'https://hoosierlottery.com/games/scratch-off/chrome/',
'https://hoosierlottery.com/games/scratch-off/cash-vault/',
'https://hoosierlottery.com/games/scratch-off/200-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/neon-9s-crossword/',
'https://hoosierlottery.com/games/scratch-off/fat-wallet/',
'https://hoosierlottery.com/games/scratch-off/20x-the-cash-(1)/',
'https://hoosierlottery.com/games/scratch-off/white-ice-(1)/',
'https://hoosierlottery.com/games/scratch-off/scorching-7s/',
'https://hoosierlottery.com/games/scratch-off/poker-night-(1)/',
'https://hoosierlottery.com/games/scratch-off/gold-bar-7s/',
'https://hoosierlottery.com/games/scratch-off/jokers-wild-poker/',
'https://hoosierlottery.com/games/scratch-off/triple-golden-cherries/',
'https://hoosierlottery.com/games/scratch-off/high-roller/',
'https://hoosierlottery.com/games/scratch-off/six-figures/',
'https://hoosierlottery.com/games/scratch-off/gold-mine/',
'https://hoosierlottery.com/games/scratch-off/linked-wins/',
'https://hoosierlottery.com/games/scratch-off/bonus-money/',
'https://hoosierlottery.com/games/scratch-off/money-bags-(2)/',
'https://hoosierlottery.com/games/scratch-off/double-the-money-(2)/',
'https://hoosierlottery.com/games/scratch-off/power-5s/',
'https://hoosierlottery.com/games/scratch-off/titanium-tripler/',
'https://hoosierlottery.com/games/scratch-off/triple-red-777/',
'https://hoosierlottery.com/games/scratch-off/holiday-crossword-doubler/',
'https://hoosierlottery.com/games/scratch-off/in-the-green/',
'https://hoosierlottery.com/games/scratch-off/sapphire-7s/',
'https://hoosierlottery.com/games/scratch-off/elf/',
'https://hoosierlottery.com/games/scratch-off/moola/',
'https://hoosierlottery.com/games/scratch-off/ruby-red-tripler/',
'https://hoosierlottery.com/games/scratch-off/50-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/super-7s-(1)/',
'https://hoosierlottery.com/games/scratch-off/team-usa/',
'https://hoosierlottery.com/games/scratch-off/emerald-7s/',
'https://hoosierlottery.com/games/scratch-off/10x-the-money/',
'https://hoosierlottery.com/games/scratch-off/loteria/',
'https://hoosierlottery.com/games/scratch-off/bingo-frenzy/',
'https://hoosierlottery.com/games/scratch-off/jaws/',
'https://hoosierlottery.com/games/scratch-off/monster-mah/',
'https://hoosierlottery.com/games/scratch-off/supreme-gold/',
'https://hoosierlottery.com/games/scratch-off/50-frenzy/',
'https://hoosierlottery.com/games/scratch-off/thunder-struck/',
'https://hoosierlottery.com/games/scratch-off/20-cash-blitz/',
'https://hoosierlottery.com/games/scratch-off/triple-tripler/',
'https://hoosierlottery.com/games/scratch-off/hot-100s/',
'https://hoosierlottery.com/games/scratch-off/diamond-dash/',
'https://hoosierlottery.com/games/scratch-off/triple-diamond-payout/',
                    ];
                
            case 'texas':
                return [
                    'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252702364.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700633.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700663.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/index.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701564.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701621.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700442.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700566.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700599.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700441.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700659.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701433.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700662.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700481.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701561.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699734.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700444.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700418.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700447.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700660.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701499.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700505.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701465.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700666.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700476.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701562.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701659.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700413.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700664.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252705345.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699730.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699704.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700569.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700411.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700448.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700509.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700511.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701652.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700506.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700477.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699731.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699672.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700574.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700570.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700445.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700503.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699735.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700446.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700478.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700542.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700414.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699732.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700538.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701371.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700479.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700417.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701470.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700415.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700601.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700571.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700692.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700568.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700690.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701495.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700694.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699736.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700696.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701467.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700572.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700596.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700507.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700693.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700450.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700603.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252699733.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252701374.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700472.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700541.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700573.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252702557.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700416.html',
'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/details.html_252700508.html',
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
    
        $rawPrice = $data['price'] ?? null;
        $data['price'] = $rawPrice ? preg_replace('/[^0-9.]/', '', (string) $rawPrice) : null;
        $ticketPrice = $data['price'] !== null ? (float) $data['price'] : 0;
    
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
        $ticketPrice = isset($data['price']) ? (float) preg_replace('/[^0-9.]/', '', (string) $data['price']) : 0;
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

        $safeTitle = trim(($data['title'] ?? 'Unknown') . (isset($data['game_no']) && $data['game_no'] !== '' ? (' #' . $data['game_no']) : ''));
        $result = [
            'title' => $safeTitle,
            'image' => $data['image'] ?? null,
            'price' => $data['price'] ?? null,
            'game_no' => $data['game_no'] ?? null,
            'start_date' => $data['start_date'] ?? null,
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

