<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TicketsExport implements WithMultipleSheets
{
    protected $groupedTickets;

    public function __construct($groupedTickets)
    {
        $this->groupedTickets = $groupedTickets;
    }

    public function sheets(): array
    {
        $sheets = [];

        $allTickets = [];

        foreach ($this->groupedTickets as $price => $tickets) {
            $ticketsArray = $tickets->toArray();
            $sheets[] = new TicketsByPriceSheet($ticketsArray, $price);

            // Merge all tickets for "Overall"
            $allTickets = array_merge($allTickets, $ticketsArray);
        }

        // Add the "Overall" sheet
        $sheets[] = new OverallTicketsSheet($allTickets);

        return $sheets;
    }
}
