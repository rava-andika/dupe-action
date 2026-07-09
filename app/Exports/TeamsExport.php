<?php

namespace App\Exports;

use App\Models\Team;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeamsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(public array $ids)
    {}

    public function query()
    {
        return Team::query()
            ->with('competition', 'members', 'leader')
            ->whereIn('id', $this->ids)
            ->withTrashed();
    }

    public function headings(): array
    {
        return [
            'id',
            'name',
            'invite_code',
            'leader',
            'competition',
            'members',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    public function map($team): array
    {
        return [
            $team->id,
            $team->name,
            $team->invite_code,
            $team->leader->name ?? null,
            $team->competition->name ?? null,
            $team->members->pluck('name')->implode(', '),
            $team->created_at,
            $team->updated_at,
            $team->deleted_at,
        ];
    }
}