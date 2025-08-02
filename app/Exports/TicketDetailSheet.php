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
        $rows = [[
            'Title', 'Price', 'Game No', 'Start Date', 'End Date', 'Initial ROI', 'Current ROI', 'Score',
            'Types', 'State', 'URL', 'Image',
            'Prize Amount', 'Total Prizes', 'Prizes Paid', 'Prizes Remaining', 'Column1'
        ]];

        foreach ($this->tickets as $ticket) {
            $base = [
                $ticket['title'] ?? '',
                $ticket['price'] ?? '',
                $ticket['game_no'] ?? '',
                $ticket['start_date'] ?? '',
                $ticket['end_date'] ?? '',
                $ticket['initial_ROI'] ?? '',
                $ticket['current_ROI'] ?? '',
                $ticket['score'] ?? '',
                is_array($ticket['type']) ? implode(', ', $ticket['type']) : '',
                $ticket['state'] ?? '',
                $ticket['url'] ?? '',
                $ticket['image'] ?? '',
            ];

            if (!empty($ticket['prizes'])) {
                foreach ($ticket['prizes'] as $prize) {
                    $rows[] = array_merge($base, [
                        $prize['amount'] ?? '',
                        $prize['total'] ?? '',
                        $prize['paid'] ?? '',
                        $prize['remaining'] ?? '',
                        $prize['column1'] ?? '',
                    ]);
                }
            } else {
                $rows[] = $base;
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return '$' . $this->price;
    }
}
