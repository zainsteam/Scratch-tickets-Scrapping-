<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class TicketsByPriceSheet implements FromArray, WithTitle
{
    protected $tickets;
    protected $price;

    public function __construct(array $tickets, $price)
    {
        $this->tickets = $tickets;
        $this->price = $price;
    }

    public function array(): array
    {
        $rows = [];
    
        // Updated header
        $rows[] = [
            'Ranking',
            'Title',
            'Price',
            'Game No',
            'Start Date',
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
            'Prize Amount',
            'Total Prizes',
            'Prizes Paid',
            'Prizes Remaining',
            'Column 1'
        ];
    
        foreach ($this->tickets as $ticket) {
            // Base ticket row
            $rows[] = [
                $ticket['ranking'] ?? '',
                $ticket['title'] ?? '',
                '$' . ($ticket['price'] ?? ''),
                $ticket['game_no'] ?? '',
                $ticket['start_date'] ?? '',
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
                '', '', '', '', ''
            ];
    
            // Prizes section
            if (!empty($ticket['prizes']) && is_array($ticket['prizes'])) {
                foreach ($ticket['prizes'] as $prize) {
                    $rows[] = [
                        '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                        '$' . $prize['amount'],
                        $prize['total'],
                        $prize['paid'],
                        $prize['remaining'],
                        '$' . number_format($prize['column1'], 2)
                    ];
                }
            } else {
                $rows[] = [
                    '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                    'No prize data available', '', '', '', ''
                ];
            }
    
            // Optional blank row
            $rows[] = [''];
        }
    
        return $rows;
    }
    

    public function title(): string
    {
        return '$' . $this->price;
    }
}
