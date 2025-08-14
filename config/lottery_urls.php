<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lottery URLs Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains URLs for different lottery states.
    | Each state can have multiple URL patterns for different purposes.
    |
    */

    'states' => [
        'arkansas' => [
            'name' => 'Arkansas Lottery',
            'base_url' => 'https://www.myarkansaslottery.com',
            'domains' => ['myarkansaslottery.com', 'www.myarkansaslottery.com'],
            'urls' => [
                'games_list' => 'https://www.myarkansaslottery.com/games/scratch-off-games',
                // Use generic games path to cover all scratch-off routes
                'game_detail_pattern' => 'https://www.myarkansaslottery.com/games/{game_id}',
                'api_endpoint' => 'https://www.myarkansaslottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'california' => [
            'name' => 'California Lottery',
            'base_url' => 'https://www.calottery.com',
            'domains' => ['calottery.com', 'www.calottery.com'],
            'urls' => [
                'games_list' => 'https://www.calottery.com/scratchers',
                'game_detail_pattern' => 'https://www.calottery.com/scratchers/{game_id}',
                'api_endpoint' => 'https://www.calottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'connecticut' => [
            'name' => 'Connecticut Lottery',
            'base_url' => 'https://www.ctlottery.org',
            'domains' => ['ctlottery.org', 'www.ctlottery.org'],
            'urls' => [
                'games_list' => 'https://www.ctlottery.org/ScratchGames',
                'game_detail_pattern' => 'https://www.ctlottery.org/ScratchGames/{game_id}',
                'api_endpoint' => 'https://www.ctlottery.org/api/games',
            ],
            'active' => true,
        ],
        
        'dc' => [
            'name' => 'DC Lottery',
            'base_url' => 'https://dclottery.com',
            'domains' => ['dclottery.com', 'www.dclottery.com'],
            'urls' => [
                'games_list' => 'https://dclottery.com/dc-scratchers',
                'game_detail_pattern' => 'https://dclottery.com/dc-scratchers/{game_id}',
                'api_endpoint' => 'https://dclottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'indiana' => [
            'name' => 'Indiana Lottery',
            'base_url' => 'https://www.hoosierlottery.com',
            'domains' => ['hoosierlottery.com', 'www.hoosierlottery.com'],
            'urls' => [
                'games_list' => 'https://hoosierlottery.com/games/scratch-off',
                'game_detail_pattern' => 'https://hoosierlottery.com/games/scratch-off/{game_id}',
                'api_endpoint' => 'https://hoosierlottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'kansas' => [
            'name' => 'Kansas Lottery',
            'base_url' => 'https://www.kslottery.com',
            'domains' => ['kslottery.com', 'www.kslottery.com'],
            'urls' => [
                'games_list' => 'https://www.kslottery.com/Games/Scratch-Games',
                'game_detail_pattern' => 'https://www.kslottery.com/Games/Scratch-Games/{game_id}',
                'api_endpoint' => 'https://www.kslottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'kentucky' => [
            'name' => 'Kentucky Lottery',
            'base_url' => 'https://www.kylottery.com',
            'domains' => ['kylottery.com', 'www.kylottery.com'],
            'urls' => [
                'games_list' => 'https://www.kylottery.com/games/scratch-off-games',
                'game_detail_pattern' => 'https://www.kylottery.com/games/scratch-off-games/{game_id}',
                'api_endpoint' => 'https://www.kylottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'louisiana' => [
            'name' => 'Louisiana Lottery',
            'base_url' => 'https://www.louisianalottery.com',
            'domains' => ['louisianalottery.com', 'www.louisianalottery.com'],
            'urls' => [
                'games_list' => 'https://www.louisianalottery.com/scratch-offs',
                'game_detail_pattern' => 'https://www.louisianalottery.com/game/{game_id}',
                'api_endpoint' => 'https://www.louisianalottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'mississippi' => [
            'name' => 'Mississippi Lottery',
            'base_url' => 'https://www.mslotteryhome.com',
            'domains' => ['mslotteryhome.com', 'www.mslotteryhome.com'],
            'urls' => [
                'games_list' => 'https://www.mslotteryhome.com/games/scratch-off-games',
                'game_detail_pattern' => 'https://www.mslotteryhome.com/games/scratch-off-games/{game_id}',
                'api_endpoint' => 'https://www.mslotteryhome.com/api/games',
            ],
            'active' => true,
        ],
        
        'new_jersey' => [
            'name' => 'New Jersey Lottery',
            'base_url' => 'https://www.njlottery.com',
            'domains' => ['njlottery.com', 'www.njlottery.com'],
            'urls' => [
                // Matches controller/live usage: https://www.njlottery.com/en-us/scratch-offs/{game_id}.html
                'games_list' => 'https://www.njlottery.com/en-us/scratch-offs',
                'game_detail_pattern' => 'https://www.njlottery.com/en-us/scratch-offs/{game_id}.html',
                'api_endpoint' => 'https://www.njlottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'north_carolina' => [
            'name' => 'North Carolina Lottery',
            'base_url' => 'https://www.nclottery.com',
            'domains' => ['nclottery.com', 'www.nclottery.com'],
            'urls' => [
                'games_list' => 'https://www.nclottery.com/Games/Scratch-Offs',
                'game_detail_pattern' => 'https://www.nclottery.com/Games/Scratch-Offs/{game_id}',
                'api_endpoint' => 'https://www.nclottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'south_carolina' => [
            'name' => 'South Carolina Lottery',
            'base_url' => 'https://www.sceducationlottery.com',
            'domains' => ['sceducationlottery.com', 'www.sceducationlottery.com'],
            'urls' => [
                'games_list' => 'https://www.sceducationlottery.com/Games/Scratch-Offs',
                'game_detail_pattern' => 'https://www.sceducationlottery.com/Games/Scratch-Offs/{game_id}',
                'api_endpoint' => 'https://www.sceducationlottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'texas' => [
            'name' => 'Texas Lottery',
            'base_url' => 'https://www.texaslottery.com',
            'domains' => ['texaslottery.com', 'www.texaslottery.com'],
            'urls' => [
                'games_list' => 'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs',
                'game_detail_pattern' => 'https://www.texaslottery.com/export/sites/lottery/Games/Scratch_Offs/{game_id}',
                'api_endpoint' => 'https://www.texaslottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'virginia' => [
            'name' => 'Virginia Lottery',
            'base_url' => 'https://www.valottery.com',
            'domains' => ['valottery.com', 'www.valottery.com'],
            'urls' => [
                'games_list' => 'https://www.valottery.com/games/scratch-off-games',
                'game_detail_pattern' => 'https://www.valottery.com/games/scratch-off-games/{game_id}',
                'api_endpoint' => 'https://www.valottery.com/api/games',
            ],
            'active' => true,
        ],
        
        'west_virginia' => [
            'name' => 'West Virginia Lottery',
            'base_url' => 'https://www.wvlottery.com',
            'domains' => ['wvlottery.com', 'www.wvlottery.com'],
            'urls' => [
                'games_list' => 'https://www.wvlottery.com/Games/Scratch-Offs',
                'game_detail_pattern' => 'https://www.wvlottery.com/Games/Scratch-Offs/{game_id}',
                'api_endpoint' => 'https://www.wvlottery.com/api/games',
            ],
            'active' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Management Settings
    |--------------------------------------------------------------------------
    |
    | Global settings for URL management and scraping.
    |
    */

    'settings' => [
        'default_timeout' => 30,
        'max_concurrent_requests' => 10,
        'retry_attempts' => 3,
        'delay_between_requests' => 100000, // microseconds
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Patterns
    |--------------------------------------------------------------------------
    |
    | Common URL patterns that can be used across different states.
    |
    */

    'patterns' => [
        'game_id' => '[A-Za-z0-9\-_]+',
        'date_format' => 'Y-m-d',
        'cache_key_prefix' => 'lottery_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Active States
    |--------------------------------------------------------------------------
    |
    | List of states that are currently active for scraping.
    | You can disable states by setting 'active' => false in the state config.
    |
    */

    'active_states' => function() {
        $states = config('lottery_urls.states');
        return array_filter($states, function($state) {
            return $state['active'] ?? true;
        });
    },
]; 