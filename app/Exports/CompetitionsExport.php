<?php

namespace App\Exports;

use App\Models\Competition;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CompetitionsExport implements FromQuery, WithHeadings
{
    public function __construct(public array $ids)
    {}

    public function query()
    {
        return Competition::query()
            ->whereIn('id', $this->ids)
            ->withTrashed();
    }

    public function headings(): array
    {
        return Schema::getColumnListing('competitions');
    }

}
