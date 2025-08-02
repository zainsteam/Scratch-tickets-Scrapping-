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
        
        'mdlottery' => [
            'name' => 'Maryland Lottery',
            'domain' => 'mdlottery.com',
            'selectors' => [
                'title' => '.game-title h2',
                'image' => '.game-image img',
                'price' => '.game-price .value',
                'game_no' => '.game-number .value',
                'start_date' => '.game-date .value',
                'prize_table' => '.prize-table tr',
                'odds' => '.odds-info .value',
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