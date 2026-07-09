<?php

namespace App\Exports;

use App\Models\Submission;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SubmissionsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(public array $ids)
    {}

    public function query()
    {
        return Submission::query()
            ->with('team', 'competition', 'reviewer')
            ->whereIn('id', $this->ids);
    }

    public function headings(): array
    {
        return [
            'id',
            'team',
            'competition',
            'submission',
            'status',
            'feedback',
            'reviewed_by',
            'submitted_at',
            'created_at',
            'updated_at',
        ];
    }

    public function map($registration): array
    {
        return [
            $registration->id,
            $registration->team->name ?? null,
            $registration->competition->name ?? null,
            $registration->submission,
            $registration->status,
            $registration->feedback,
            $registration->reviewer->name ?? null,
            $registration->submitted_at,
            $registration->created_at,
            $registration->updated_at,
        ];
    }
}
