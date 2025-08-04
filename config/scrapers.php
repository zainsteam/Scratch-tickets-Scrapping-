<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scraper Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for different lottery site scrapers.
    | Each scraper can have its own selectors and settings.
    |
    */

    'sites' => [
        'arkansaslottery' => [
            'name' => 'Arkansas Lottery',
            'domain' => 'myarkansaslottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'calottery' => [
            'name' => 'California Lottery',
            'domain' => 'calottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'ctlottery' => [
            'name' => 'Connecticut Lottery',
            'domain' => 'ctlottery.org',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'dclottery' => [
            'name' => 'DC Lottery',
            'domain' => 'dclottery.com',
            'selectors' => [
                'title' => '.pageheader__title h1',
                'image' => '.ticket-image',
                'basic_info' => '.pageheader--game__info .field__label',
                'prize_table' => 'table.views-table tbody tr',
                'odds' => '.pageheader--game__info .field__label:contains("Odds")',
            ],
            'fields' => ['Price', 'Game No', 'Start Date', 'Odds', 'Top Prize Odds'],
        ],
        
        'hoosierlottery' => [
            'name' => 'Indiana Lottery',
            'domain' => 'hoosierlottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'kslottery' => [
            'name' => 'Kansas Lottery',
            'domain' => 'kslottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'kylottery' => [
            'name' => 'Kentucky Lottery',
            'domain' => 'kylottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'louisianalottery' => [
            'name' => 'Louisiana Lottery',
            'domain' => 'louisianalottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'mslotteryhome' => [
            'name' => 'Mississippi Lottery',
            'domain' => 'mslotteryhome.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'njlottery' => [
            'name' => 'New Jersey Lottery',
            'domain' => 'njlottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'nclottery' => [
            'name' => 'North Carolina Lottery',
            'domain' => 'nclottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'sceducationlottery' => [
            'name' => 'South Carolina Lottery',
            'domain' => 'sceducationlottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'texaslottery' => [
            'name' => 'Texas Lottery',
            'domain' => 'texaslottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
        
        'valottery' => [
            'name' => 'Virginia Lottery',
            'domain' => 'valottery.com',
            'selectors' => [
                'title' => '.scratch-off-title',
                'image' => '.ticket-image img',
                'price' => '.ticket-price',
                'game_no' => '.game-id',
                'start_date' => '.release-date',
                'prize_table' => '.prize-breakdown tr',
                'odds' => '.winning-odds',
            ],
        ],
        
        'wvlottery' => [
            'name' => 'West Virginia Lottery',
            'domain' => 'wvlottery.com',
            'selectors' => [
                'title' => 'h1, .game-title, .ticket-title',
                'image' => '.ticket-image img, .game-image img',
                'price' => '.price, .ticket-price, .game-price',
                'game_no' => '.game-number, .ticket-number',
                'start_date' => '.start-date, .release-date',
                'end_date' => '.end-date, .claim-deadline',
                'prize_table' => 'table tbody tr',
                'odds' => '.overall-odds, .total-odds',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Scraping Settings
    |--------------------------------------------------------------------------
    |
    | These settings apply to all scrapers.
    |
    */

    'settings' => [
        'concurrency' => 10,
        'delay_between_requests' => 100000, // microseconds
        'timeout' => 30, // seconds
        'retry_attempts' => 3,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Processing Settings
    |--------------------------------------------------------------------------
    |
    | Settings for processing scraped data.
    |
    */

    'processing' => [
        'cache_duration' => 3600, // 1 hour
        'min_remaining_prizes' => 3,
        'default_state' => 'Unknown',
    ],
]; 