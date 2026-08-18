<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class TicketsExport implements FromView, ShouldAutoSize
{
    public function __construct(
        protected $tickets,
        protected ?string $startDate = null,
        protected ?string $endDate = null
    ) {}

    public function view(): View
    {
        return view('reports.tickets-excel', [
            'tickets' => $this->tickets,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
