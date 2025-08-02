<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class OverallTicketsSheet implements FromArray, WithTitle
{
    protected $allTickets;

    public function __construct(array $allTickets)
    {
        $this->allTickets = $allTickets;
    }

    public function array(): array
    {
        $rows = [[
            'Ranking',
            'Title',
            'Price',
            'Game No',
            'Start Date',
            'End Date',
            'Initial ROI',
            'Current ROI',
            'Score',
            'Type',
            'State',
            'URL',
            'Image',
            'Top Grand Prize',
            'Initial Grand Prize',
            'Current Grand Prize',
            'Grand Prize Left',
        ]];
    
        foreach ($this->allTickets as $ticket) {
            // Debug: Log specific tickets for export
            if (str_contains($ticket['title'] ?? '', '300X') || str_contains($ticket['title'] ?? '', 'Money Maker')) {
                \Log::info('Export Debug', [
                    'title' => $ticket['title'],
                    'ranking' => $ticket['ranking'],
                    'type' => $ticket['type'],
                    'grand_ranking' => $ticket['ranking']['grand'] ?? null,
                    'has_grand_type' => is_array($ticket['type']) ? in_array('grand', $ticket['type']) : false
                ]);
            }
            
            $rows[] = [
                $ticket['ranking'] ?? '',
                $ticket['title'] ?? '',
                '$' . ($ticket['price'] ?? ''),
                $ticket['game_no'] ?? '',
                $ticket['start_date'] ?? '',
                $ticket['end_date'] ?? '',
                $ticket['initial_ROI'] ?? '',
                $ticket['current_ROI'] ?? '',
                '$' . ($ticket['score'] ?? ''),
                is_array($ticket['type']) ? implode(', ', $ticket['type']) : $ticket['type'],
                $ticket['state'] ?? '',
                $ticket['url'] ?? '',
                $ticket['image'] ?? '',
                $ticket['top_grand_prize'] ?? '',
                $ticket['initial_grand_prize'] ?? '',
                $ticket['current_grand_prize'] ?? '',
                $ticket['grand_prize_left'] ?? '',
            ];
        }
    
        return $rows;
    }
    

    public function title(): string
    {
        return 'Overall';
    }
}
