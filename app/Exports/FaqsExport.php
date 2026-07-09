<?php

namespace App\Exports;

use App\Models\Faq;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FaqsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(public array $ids)
    {}

    public function query()
    {
        return Faq::query()
            ->with('competition')
            ->whereIn('id', $this->ids)
            ->withTrashed();
    }

    public function headings(): array
    {
        return [
            'id',
            'question',
            'competition',
            'answer',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    public function map($faq): array
    {
        return [
            $faq->id,
            $faq->question,
            $faq->competition->name ?? null,
            $faq->answer,
            $faq->created_at,
            $faq->updated_at,
            $faq->deleted_at,
        ];
    }
}
