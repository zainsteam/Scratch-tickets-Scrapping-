<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class GrandPrizeSheet implements FromArray, WithTitle
{
    protected $allTickets;

    public function __construct(array $allTickets)
    {
        $this->allTickets = $allTickets;
    }

    public function array(): array
    {
        $rows = [[
            'Grand Prize Rank',
            'Title',
            'Price',
            'Game No',
            'Start Date',
            'End Date',
            'Initial ROI',
            'Current ROI',
            'Score',
            'State',
            'URL',
            'Image',
            'Top Grand Prize',
            'Initial Grand Prize',
            'Current Grand Prize',
            'Grand Prize Left %',
            'Grand Prize Type',
        ]];
    
        // Filter only tickets with grand prize ranking
        $grandPrizeTickets = collect($this->allTickets)->filter(function ($ticket) {
            return isset($ticket['ranking']['grand']);
        })->sortBy('ranking.grand');
        
        foreach ($grandPrizeTickets as $ticket) {
            $rows[] = [
                $ticket['ranking']['grand'] ?? '',
                $ticket['title'] ?? '',
                '$' . ($ticket['price'] ?? ''),
                $ticket['game_no'] ?? '',
                $ticket['start_date'] ?? '',
                $ticket['end_date'] ?? '',
                $ticket['initial_ROI'] ?? '',
                $ticket['current_ROI'] ?? '',
                '$' . ($ticket['score'] ?? ''),
                $ticket['state'] ?? '',
                $ticket['url'] ?? '',
                $ticket['image'] ?? '',
                $ticket['top_grand_prize'] ?? '',
                $ticket['initial_grand_prize'] ?? '',
                $ticket['current_grand_prize'] ?? '',
                $ticket['grand_prize_left'] ?? '',
                'Grand Prize',
            ];
        }
    
        return $rows;
    }

    public function title(): string
    {
        return 'Grand Prize Rankings';
    }
} 